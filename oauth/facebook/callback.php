<?php
require_once __DIR__ . '/../../includes/oauth.php';
require_once __DIR__ . '/../../includes/stripe.php';
require_once __DIR__ . '/../../includes/membership.php';

$state = request_str('state');
$code = request_str('code');
if (!oauth_verify_state('facebook', $state)) {
    http_response_code(400);
    exit('Invalid OAuth state.');
}
if ($code === '') {
    http_response_code(400);
    exit('Facebook OAuth code missing.');
}

$cfg = app_config()['oauth']['facebook'] ?? [];
$version = trim((string) ($cfg['graph_version'] ?? 'v19.0'));
$token = oauth_http_get_json('https://graph.facebook.com/' . rawurlencode($version) . '/oauth/access_token?' . http_build_query([
    'client_id' => (string) ($cfg['app_id'] ?? ''),
    'client_secret' => (string) ($cfg['app_secret'] ?? ''),
    'redirect_uri' => (string) ($cfg['redirect_uri'] ?? ''),
    'code' => $code,
]));

$accessToken = (string) ($token['access_token'] ?? '');
if ($accessToken === '') {
    http_response_code(400);
    exit('Facebook access token missing.');
}

$userInfo = oauth_http_get_json('https://graph.facebook.com/me?' . http_build_query([
    'fields' => 'id,name,email',
    'access_token' => $accessToken,
]));

$email = strtolower(trim((string) ($userInfo['email'] ?? '')));
if ($email === '') {
    http_response_code(400);
    exit('Facebook account did not return an email address.');
}

$user = oauth_upsert_user(db(), 'facebook', (string) ($userInfo['id'] ?? ''), $email, (string) ($userInfo['name'] ?? ''));
mark_user_logged_in((int) $user['id']);

$subscription = fetch_active_subscription_for_user(db(), (int) $user['id']);
if ($subscription) {
    set_user_account_status_from_subscription(db(), (int) $user['id']);
    header('Location: ../../dashboard.php');
    exit;
}

start_session_if_needed();
$plan = strtoupper((string) ($_SESSION['oauth_selected_plan'] ?? 'MEMBER_BASIC'));
if (!in_array($plan, membership_plan_codes(), true)) {
    $plan = 'MEMBER_BASIC';
}

$session = create_checkout_session(db(), (int) $user['id'], $plan);
header('Location: ' . (string) ($session['url'] ?? '../../pricing.php'));
exit;
