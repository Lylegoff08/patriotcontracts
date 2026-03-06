<?php
require_once __DIR__ . '/../../includes/oauth.php';

$cfg = app_config()['oauth']['facebook'] ?? [];
$appId = trim((string) ($cfg['app_id'] ?? ''));
$redirectUri = trim((string) ($cfg['redirect_uri'] ?? ''));
$version = trim((string) ($cfg['graph_version'] ?? 'v19.0'));

if ($appId === '' || $redirectUri === '') {
    http_response_code(500);
    exit('Facebook OAuth is not configured.');
}

$plan = strtoupper(request_str('plan', 'MEMBER_BASIC'));
if (!in_array($plan, membership_plan_codes(), true)) {
    $plan = 'MEMBER_BASIC';
}
start_session_if_needed();
$_SESSION['oauth_selected_plan'] = $plan;

$state = oauth_start_state('facebook');
$params = [
    'client_id' => $appId,
    'redirect_uri' => $redirectUri,
    'state' => $state,
    'scope' => 'email,public_profile',
    'response_type' => 'code',
];

header('Location: https://www.facebook.com/' . rawurlencode($version) . '/dialog/oauth?' . http_build_query($params));
exit;
