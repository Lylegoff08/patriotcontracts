<?php
require_once __DIR__ . '/../../includes/oauth.php';
require_once __DIR__ . '/../../includes/stripe.php';
require_once __DIR__ . '/../../includes/membership.php';

$state = trim((string) ($_POST['state'] ?? $_GET['state'] ?? ''));
$code = trim((string) ($_POST['code'] ?? $_GET['code'] ?? ''));
if (!oauth_verify_state('apple', $state)) {
    http_response_code(400);
    exit('Invalid OAuth state.');
}
if ($code === '') {
    http_response_code(400);
    exit('Apple OAuth code missing.');
}

$cfg = app_config()['oauth']['apple'] ?? [];
$token = oauth_http_post_form('https://appleid.apple.com/auth/token', [
    'client_id' => (string) ($cfg['client_id'] ?? ''),
    'client_secret' => (string) ($cfg['client_secret'] ?? ''),
    'code' => $code,
    'grant_type' => 'authorization_code',
    'redirect_uri' => (string) ($cfg['redirect_uri'] ?? ''),
]);

$idToken = (string) ($token['id_token'] ?? '');
if ($idToken === '') {
    http_response_code(400);
    exit('Apple id_token missing.');
}

$claims = verify_apple_id_token($idToken, (string) ($cfg['client_id'] ?? ''));
if (!$claims) {
    http_response_code(400);
    exit('Unable to verify Apple id_token.');
}

$email = strtolower(trim((string) ($claims['email'] ?? '')));
if ($email === '') {
    http_response_code(400);
    exit('Apple account did not return an email.');
}

$providerId = (string) ($claims['sub'] ?? '');
$namePayload = json_decode((string) ($_POST['user'] ?? ''), true);
$fullName = '';
if (is_array($namePayload)) {
    $first = trim((string) ($namePayload['name']['firstName'] ?? ''));
    $last = trim((string) ($namePayload['name']['lastName'] ?? ''));
    $fullName = trim($first . ' ' . $last);
}

$user = oauth_upsert_user(db(), 'apple', $providerId, $email, $fullName);
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
