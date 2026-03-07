<?php
require_once __DIR__ . '/../../includes/oauth.php';
require_once __DIR__ . '/../../includes/stripe.php';
require_once __DIR__ . '/../../includes/membership.php';

$state = request_str('state');
$code = request_str('code');
if (!oauth_verify_state('google', $state)) {
    http_response_code(400);
    exit('Invalid OAuth state.');
}
if ($code === '') {
    http_response_code(400);
    exit('Google OAuth code missing.');
}

$cfg = app_config()['oauth']['google'] ?? [];
$token = oauth_http_post_form('https://oauth2.googleapis.com/token', [
    'code' => $code,
    'client_id' => (string) ($cfg['client_id'] ?? ''),
    'client_secret' => (string) ($cfg['client_secret'] ?? ''),
    'redirect_uri' => (string) ($cfg['redirect_uri'] ?? ''),
    'grant_type' => 'authorization_code',
]);

$accessToken = (string) ($token['access_token'] ?? '');
if ($accessToken === '') {
    http_response_code(400);
    exit('Google access token missing.');
}

$userInfo = oauth_http_get_json('https://openidconnect.googleapis.com/v1/userinfo?access_token=' . urlencode($accessToken));
$email = strtolower(trim((string) ($userInfo['email'] ?? '')));
if ($email === '' || empty($userInfo['email_verified'])) {
    http_response_code(400);
    exit('Google account email not verified.');
}

$user = oauth_upsert_user(db(), 'google', (string) ($userInfo['sub'] ?? ''), $email, (string) ($userInfo['name'] ?? ''));
mark_user_logged_in((int) $user['id']);

$subscription = fetch_active_subscription_for_user(db(), (int) $user['id']);
if ($subscription) {
    set_user_account_status_from_subscription(db(), (int) $user['id']);
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

start_session_if_needed();
$plan = strtoupper((string) ($_SESSION['oauth_selected_plan'] ?? 'MEMBER_BASIC'));
if (!in_array($plan, membership_plan_codes(), true)) {
    $plan = 'MEMBER_BASIC';
}

$session = create_checkout_session(db(), (int) $user['id'], $plan);
$checkoutUrl = (string) ($session['url'] ?? '');
header('Location: ' . ($checkoutUrl !== '' ? $checkoutUrl : app_url('pricing.php')));
exit;
