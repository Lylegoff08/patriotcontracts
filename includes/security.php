<?php

require_once __DIR__ . '/db.php';

function security_config(): array
{
    return app_config()['security'] ?? [];
}

function client_ip(): string
{
    $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($keys as $key) {
        $value = trim((string) ($_SERVER[$key] ?? ''));
        if ($value === '') {
            continue;
        }
        if ($key === 'HTTP_X_FORWARDED_FOR') {
            $parts = explode(',', $value);
            return trim($parts[0]);
        }
        return $value;
    }
    return '0.0.0.0';
}

function secure_compare(string $a, string $b): bool
{
    return hash_equals($a, $b);
}

function token_hash(string $token): string
{
    $pepper = (string) (security_config()['token_pepper'] ?? '');
    return hash('sha256', $token . '|' . $pepper);
}

function api_key_hash(string $apiKey): string
{
    $pepper = (string) (security_config()['api_key_pepper'] ?? '');
    return hash('sha256', $apiKey . '|' . $pepper);
}

function generate_raw_token(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

function csrf_token(bool $rotate = false): string
{
    if (function_exists('start_session_if_needed')) {
        start_session_if_needed();
    } elseif (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $ttl = (int) (security_config()['csrf_ttl_seconds'] ?? 7200);
    $now = time();

    $current = $_SESSION['_csrf_token'] ?? '';
    $created = (int) ($_SESSION['_csrf_created'] ?? 0);
    if ($rotate || $current === '' || $created <= 0 || ($now - $created) > $ttl) {
        $current = generate_raw_token(24);
        $_SESSION['_csrf_token'] = $current;
        $_SESSION['_csrf_created'] = $now;
    }
    return $current;
}

function verify_csrf_token(?string $token): bool
{
    if (function_exists('start_session_if_needed')) {
        start_session_if_needed();
    } elseif (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (!isset($_SESSION['_csrf_token'], $_SESSION['_csrf_created'])) {
        return false;
    }
    $ttl = (int) (security_config()['csrf_ttl_seconds'] ?? 7200);
    $created = (int) $_SESSION['_csrf_created'];
    if ((time() - $created) > $ttl) {
        return false;
    }
    $token = (string) $token;
    if ($token === '') {
        return false;
    }
    return secure_compare((string) $_SESSION['_csrf_token'], $token);
}

function require_csrf_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    $token = (string) ($_POST['_csrf'] ?? '');
    if (!verify_csrf_token($token)) {
        http_response_code(419);
        exit('Invalid CSRF token');
    }
}

function login_rate_limit_config(): array
{
    $rate = security_config()['rate_limit'] ?? [];
    return [
        'window_minutes' => max(1, (int) ($rate['login_window_minutes'] ?? 15)),
        'max_attempts' => max(3, (int) ($rate['login_max_attempts'] ?? 8)),
    ];
}

function record_login_attempt(PDO $pdo, string $email, bool $success): void
{
    $stmt = $pdo->prepare('INSERT INTO login_attempts (email, ip_address, was_success, attempted_at) VALUES (:email, :ip, :success, NOW())');
    $stmt->execute([
        'email' => strtolower(trim($email)) ?: null,
        'ip' => client_ip(),
        'success' => $success ? 1 : 0,
    ]);
}

function is_login_rate_limited(PDO $pdo, string $email): bool
{
    $cfg = login_rate_limit_config();
    $minutes = $cfg['window_minutes'];
    $maxAttempts = $cfg['max_attempts'];

    $stmt = $pdo->prepare('SELECT COUNT(*)
        FROM login_attempts
        WHERE was_success = 0
          AND attempted_at >= DATE_SUB(NOW(), INTERVAL :minutes MINUTE)
          AND (ip_address = :ip OR email = :email)');
    $stmt->bindValue(':minutes', $minutes, PDO::PARAM_INT);
    $stmt->bindValue(':ip', client_ip());
    $stmt->bindValue(':email', strtolower(trim($email)));
    $stmt->execute();

    return (int) $stmt->fetchColumn() >= $maxAttempts;
}
