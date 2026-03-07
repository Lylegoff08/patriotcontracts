<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

function oauth_http_post_form(string $url, array $data): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 40,
    ]);

    $resp = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($resp === false || $err) {
        throw new RuntimeException('OAuth HTTP error: ' . $err);
    }

    $decoded = json_decode($resp, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('OAuth response decode failed.');
    }

    if ($status < 200 || $status >= 300) {
        $msg = (string) ($decoded['error_description'] ?? $decoded['error']['message'] ?? $decoded['error'] ?? 'OAuth request failed');
        throw new RuntimeException($msg);
    }

    return $decoded;
}

function oauth_http_get_json(string $url): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
    ]);

    $resp = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($resp === false || $err) {
        throw new RuntimeException('OAuth HTTP error: ' . $err);
    }

    $decoded = json_decode($resp, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('OAuth response decode failed.');
    }

    if ($status < 200 || $status >= 300) {
        $msg = (string) ($decoded['error_description'] ?? $decoded['error']['message'] ?? $decoded['error'] ?? 'OAuth request failed');
        throw new RuntimeException($msg);
    }

    return $decoded;
}

function oauth_start_state(string $provider): string
{
    start_session_if_needed();
    $state = generate_raw_token(18);
    $_SESSION['oauth_state_' . $provider] = $state;
    $_SESSION['oauth_state_exp_' . $provider] = time() + 600;
    return $state;
}

function oauth_verify_state(string $provider, string $state): bool
{
    start_session_if_needed();
    $expected = (string) ($_SESSION['oauth_state_' . $provider] ?? '');
    $exp = (int) ($_SESSION['oauth_state_exp_' . $provider] ?? 0);
    unset($_SESSION['oauth_state_' . $provider], $_SESSION['oauth_state_exp_' . $provider]);
    if ($expected === '' || $exp < time()) {
        return false;
    }
    return hash_equals($expected, $state);
}

function oauth_base_url(): string
{
    return app_base_url();
}

function oauth_upsert_user(PDO $pdo, string $provider, string $providerId, string $email, string $fullName): array
{
    return find_or_create_social_user($pdo, $provider, $providerId, $email, $fullName);
}

function apple_jwk_to_pem(array $jwk): ?string
{
    if (($jwk['kty'] ?? '') !== 'RSA' || empty($jwk['n']) || empty($jwk['e'])) {
        return null;
    }

    $mod = base64_decode(strtr($jwk['n'], '-_', '+/'), true);
    $exp = base64_decode(strtr($jwk['e'], '-_', '+/'), true);
    if ($mod === false || $exp === false) {
        return null;
    }

    $components = [
        'modulus' => "\x02" . encode_der_length(strlen($mod)) . $mod,
        'publicExponent' => "\x02" . encode_der_length(strlen($exp)) . $exp,
    ];

    $rsa = "\x30" . encode_der_length(strlen($components['modulus']) + strlen($components['publicExponent']))
        . $components['modulus'] . $components['publicExponent'];

    $bitString = "\x03" . encode_der_length(strlen($rsa) + 1) . "\x00" . $rsa;
    $algo = hex2bin('300d06092a864886f70d0101010500');
    $sequence = "\x30" . encode_der_length(strlen($algo) + strlen($bitString)) . $algo . $bitString;

    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($sequence), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

function encode_der_length(int $length): string
{
    if ($length <= 0x7F) {
        return chr($length);
    }
    $temp = ltrim(pack('N', $length), "\x00");
    return chr(0x80 | strlen($temp)) . $temp;
}

function verify_apple_id_token(string $jwt, string $expectedAudience): ?array
{
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) {
        return null;
    }

    [$headerB64, $payloadB64, $sigB64] = $parts;
    $header = json_decode(base64_decode(strtr($headerB64, '-_', '+/'), true) ?: '', true);
    $payload = json_decode(base64_decode(strtr($payloadB64, '-_', '+/'), true) ?: '', true);
    if (!is_array($header) || !is_array($payload)) {
        return null;
    }

    if (($payload['aud'] ?? '') !== $expectedAudience) {
        return null;
    }
    if (($payload['iss'] ?? '') !== 'https://appleid.apple.com') {
        return null;
    }
    if ((int) ($payload['exp'] ?? 0) < time()) {
        return null;
    }

    $jwks = oauth_http_get_json('https://appleid.apple.com/auth/keys');
    $keys = $jwks['keys'] ?? [];
    $kid = (string) ($header['kid'] ?? '');
    $alg = (string) ($header['alg'] ?? '');
    if ($alg !== 'RS256' || $kid === '') {
        return null;
    }

    $key = null;
    foreach ($keys as $k) {
        if (($k['kid'] ?? '') === $kid) {
            $key = $k;
            break;
        }
    }
    if (!$key) {
        return null;
    }

    $pem = apple_jwk_to_pem($key);
    if ($pem === null) {
        return null;
    }

    $signedData = $headerB64 . '.' . $payloadB64;
    $signature = base64_decode(strtr($sigB64, '-_', '+/'), true);
    if ($signature === false) {
        return null;
    }

    $ok = openssl_verify($signedData, $signature, $pem, OPENSSL_ALGO_SHA256);
    if ($ok !== 1) {
        return null;
    }

    return $payload;
}
