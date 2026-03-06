<?php
require_once __DIR__ . '/../../includes/oauth.php';

$cfg = app_config()['oauth']['google'] ?? [];
$clientId = trim((string) ($cfg['client_id'] ?? ''));
$redirectUri = trim((string) ($cfg['redirect_uri'] ?? ''));

if ($clientId === '' || $redirectUri === '') {
    http_response_code(500);
    exit('Google OAuth is not configured.');
}

$plan = strtoupper(request_str('plan', 'MEMBER_BASIC'));
if (!in_array($plan, membership_plan_codes(), true)) {
    $plan = 'MEMBER_BASIC';
}
start_session_if_needed();
$_SESSION['oauth_selected_plan'] = $plan;

$state = oauth_start_state('google');
$params = [
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'state' => $state,
    'prompt' => 'select_account',
];

header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params));
exit;
