<?php
require_once __DIR__ . '/../../includes/oauth.php';

$cfg = app_config()['oauth']['apple'] ?? [];
$clientId = trim((string) ($cfg['client_id'] ?? ''));
$redirectUri = trim((string) ($cfg['redirect_uri'] ?? ''));

if ($clientId === '' || $redirectUri === '') {
    http_response_code(500);
    exit('Apple OAuth is not configured.');
}

$plan = strtoupper(request_str('plan', 'MEMBER_BASIC'));
if (!in_array($plan, membership_plan_codes(), true)) {
    $plan = 'MEMBER_BASIC';
}
start_session_if_needed();
$_SESSION['oauth_selected_plan'] = $plan;

$state = oauth_start_state('apple');
$params = [
    'response_type' => 'code id_token',
    'response_mode' => 'form_post',
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
    'scope' => 'name email',
    'state' => $state,
];

header('Location: https://appleid.apple.com/auth/authorize?' . http_build_query($params));
exit;
