<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/membership.php';

function start_session_if_needed(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $cfg = app_config();
    $sessionCfg = $cfg['security'] ?? [];
    $name = (string) ($sessionCfg['session_name'] ?? 'patriotcontracts_session');
    $lifetime = (int) ($sessionCfg['session_lifetime'] ?? 86400);

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((int) ($_SERVER['SERVER_PORT'] ?? 80) === 443);
    $secureCookies = (bool) ($sessionCfg['secure_cookies'] ?? $isHttps);

    session_name($name);
    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path' => '/',
        'domain' => '',
        'secure' => $secureCookies,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function current_user(): ?array
{
    start_session_if_needed();
    $uid = (int) ($_SESSION['user_id'] ?? 0);
    if ($uid <= 0) {
        return null;
    }

    $stmt = db()->prepare('SELECT id, email, full_name, provider, provider_id, email_verified, account_status, role, stripe_customer_id, is_active, created_at, updated_at, last_login_at
        FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $uid]);
    $user = $stmt->fetch();

    if (!$user || (int) ($user['is_active'] ?? 1) !== 1) {
        unset($_SESSION['user_id']);
        return null;
    }

    return $user;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        $base = rtrim((string) (app_config()['app']['base_url'] ?? ''), '/');
        $next = urlencode((string) ($_SERVER['REQUEST_URI'] ?? ''));
        header('Location: ' . $base . '/login.php?next=' . $next);
        exit;
    }
    return $user;
}

function require_active_member(): array
{
    $user = require_login();
    $role = strtolower(trim((string) ($user['role'] ?? '')));
    if (in_array($role, ['admin', 'super_admin'], true)) {
        return $user;
    }

    if (($user['account_status'] ?? '') !== 'active') {
        $base = rtrim((string) (app_config()['app']['base_url'] ?? ''), '/');
        header('Location: ' . $base . '/pricing.php?upgrade=member');
        exit;
    }
    return $user;
}

function require_feature(string $featureCode): array
{
    $user = require_active_member();
    if (!user_has_feature(db(), (int) $user['id'], $featureCode)) {
        $base = rtrim((string) (app_config()['app']['base_url'] ?? ''), '/');
        header('Location: ' . $base . '/pricing.php?upgrade=' . urlencode($featureCode));
        exit;
    }
    return $user;
}

function require_api_plan(): array
{
    return require_feature('api_access');
}

function mark_user_logged_in(int $userId): void
{
    start_session_if_needed();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;

    $stmt = db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
    $stmt->execute(['id' => $userId]);
}

function login_user(string $email, string $password): array
{
    $pdo = db();
    $email = strtolower(trim($email));

    if ($email === '' || $password === '') {
        return [false, 'Email and password are required'];
    }

    if (is_login_rate_limited($pdo, $email)) {
        return [false, 'Too many login attempts. Try again later.'];
    }

    $stmt = $pdo->prepare('SELECT id, password_hash, account_status, provider, email_verified, is_active FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user || (string) ($user['provider'] ?? 'email') !== 'email') {
        record_login_attempt($pdo, $email, false);
        return [false, 'Invalid login credentials'];
    }

    if ((int) ($user['is_active'] ?? 1) !== 1 || !password_verify($password, (string) ($user['password_hash'] ?? ''))) {
        record_login_attempt($pdo, $email, false);
        return [false, 'Invalid login credentials'];
    }

    record_login_attempt($pdo, $email, true);
    mark_user_logged_in((int) $user['id']);

    return [true, 'Logged in'];
}

function generate_email_verification(int $userId): array
{
    $pdo = db();
    $token = generate_raw_token(32);
    $hash = token_hash($token);

    $stmt = $pdo->prepare('INSERT INTO email_verifications (user_id, token_hash, expires_at, created_at)
        VALUES (:user_id, :token_hash, DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW())');
    $stmt->execute([
        'user_id' => $userId,
        'token_hash' => $hash,
    ]);

    return [$token, $hash];
}

function send_user_verification_email(int $userId): bool
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT email FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $userId]);
    $email = (string) ($stmt->fetchColumn() ?: '');
    if ($email === '') {
        return false;
    }

    [$token] = generate_email_verification($userId);
    $url = rtrim((string) app_config()['app']['base_url'], '/') . '/verify-email.php?token=' . urlencode($token);
    return send_verification_email($email, $url);
}

function register_email_user(string $email, string $password, string $fullName = ''): array
{
    $pdo = db();
    $email = strtolower(trim($email));
    $fullName = trim($fullName);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [false, 'Invalid email address'];
    }
    if (strlen($password) < 8) {
        return [false, 'Password must be at least 8 characters'];
    }

    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    if ($stmt->fetchColumn()) {
        return [false, 'Email already exists'];
    }

    $hash = password_hash($password, app_config()['security']['password_algo'] ?? PASSWORD_DEFAULT);

    $insert = $pdo->prepare('INSERT INTO users (email, password_hash, full_name, provider, email_verified, account_status, role, is_active, created_at, updated_at)
        VALUES (:email, :password_hash, :full_name, "email", 0, "pending_payment", "user", 1, NOW(), NOW())');
    $insert->execute([
        'email' => $email,
        'password_hash' => $hash,
        'full_name' => $fullName,
    ]);

    $userId = (int) $pdo->lastInsertId();
    send_user_verification_email($userId);

    return [true, 'Account created. Check your email for verification after payment.', $userId];
}

function register_user(string $email, string $password, string $fullName = ''): array
{
    return register_email_user($email, $password, $fullName);
}

function verify_email_token(string $token): array
{
    $hash = token_hash(trim($token));
    if ($hash === token_hash('')) {
        return [false, 'Invalid token'];
    }

    $pdo = db();
    $stmt = $pdo->prepare('SELECT ev.id, ev.user_id, ev.expires_at, ev.used_at, u.provider
        FROM email_verifications ev
        JOIN users u ON u.id = ev.user_id
        WHERE ev.token_hash = :token_hash
        LIMIT 1');
    $stmt->execute(['token_hash' => $hash]);
    $row = $stmt->fetch();

    if (!$row) {
        return [false, 'Verification token not found'];
    }
    if (!empty($row['used_at'])) {
        return [false, 'Verification token already used'];
    }
    if (strtotime((string) $row['expires_at']) < time()) {
        return [false, 'Verification token expired'];
    }

    $pdo->beginTransaction();
    try {
        $use = $pdo->prepare('UPDATE email_verifications SET used_at = NOW() WHERE id = :id');
        $use->execute(['id' => (int) $row['id']]);

        $updateUser = $pdo->prepare('UPDATE users SET email_verified = 1, updated_at = NOW() WHERE id = :id');
        $updateUser->execute(['id' => (int) $row['user_id']]);

        set_user_account_status_from_subscription($pdo, (int) $row['user_id']);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return [false, 'Unable to verify email'];
    }

    return [true, 'Email verified'];
}

function create_password_reset(string $email): bool
{
    $pdo = db();
    $email = strtolower(trim($email));

    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email AND is_active = 1 LIMIT 1');
    $stmt->execute(['email' => $email]);
    $userId = (int) ($stmt->fetchColumn() ?: 0);
    if ($userId <= 0) {
        return true;
    }

    $token = generate_raw_token(32);
    $hash = token_hash($token);

    $insert = $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at, created_at)
        VALUES (:user_id, :token_hash, DATE_ADD(NOW(), INTERVAL 1 HOUR), NOW())');
    $insert->execute([
        'user_id' => $userId,
        'token_hash' => $hash,
    ]);

    $url = rtrim((string) app_config()['app']['base_url'], '/') . '/reset-password.php?token=' . urlencode($token);
    send_password_reset_email($email, $url);

    return true;
}

function reset_password_with_token(string $token, string $newPassword): array
{
    if (strlen($newPassword) < 8) {
        return [false, 'Password must be at least 8 characters'];
    }

    $hash = token_hash(trim($token));
    $pdo = db();

    $stmt = $pdo->prepare('SELECT id, user_id, expires_at, used_at
        FROM password_resets
        WHERE token_hash = :token_hash
        LIMIT 1');
    $stmt->execute(['token_hash' => $hash]);
    $row = $stmt->fetch();

    if (!$row) {
        return [false, 'Reset token not found'];
    }
    if (!empty($row['used_at'])) {
        return [false, 'Reset token already used'];
    }
    if (strtotime((string) $row['expires_at']) < time()) {
        return [false, 'Reset token expired'];
    }

    $passwordHash = password_hash($newPassword, app_config()['security']['password_algo'] ?? PASSWORD_DEFAULT);

    $pdo->beginTransaction();
    try {
        $upUser = $pdo->prepare('UPDATE users SET password_hash = :hash, updated_at = NOW() WHERE id = :id');
        $upUser->execute([
            'hash' => $passwordHash,
            'id' => (int) $row['user_id'],
        ]);

        $upToken = $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = :id');
        $upToken->execute(['id' => (int) $row['id']]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return [false, 'Could not reset password'];
    }

    return [true, 'Password reset complete'];
}

function logout_user(): void
{
    start_session_if_needed();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }

    session_destroy();
}

function find_or_create_social_user(PDO $pdo, string $provider, string $providerId, string $email, string $fullName = ''): array
{
    $provider = strtolower(trim($provider));
    $providerId = trim($providerId);
    $email = strtolower(trim($email));
    $fullName = trim($fullName);

    if ($providerId === '' || $email === '') {
        throw new RuntimeException('Social login requires provider identity and email.');
    }

    $stmt = $pdo->prepare('SELECT * FROM users WHERE provider = :provider AND provider_id = :provider_id LIMIT 1');
    $stmt->execute(['provider' => $provider, 'provider_id' => $providerId]);
    $existing = $stmt->fetch();
    if ($existing) {
        return $existing;
    }

    $emailStmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $emailStmt->execute(['email' => $email]);
    $emailUser = $emailStmt->fetch();

    if ($emailUser) {
        if (!empty($emailUser['provider_id']) && $emailUser['provider'] !== $provider) {
            throw new RuntimeException('Email already linked to another provider.');
        }

        $update = $pdo->prepare('UPDATE users
            SET provider = :provider,
                provider_id = :provider_id,
                email_verified = 1,
                full_name = CASE WHEN full_name IS NULL OR full_name = "" THEN :full_name ELSE full_name END,
                updated_at = NOW()
            WHERE id = :id');
        $update->execute([
            'provider' => $provider,
            'provider_id' => $providerId,
            'full_name' => $fullName,
            'id' => (int) $emailUser['id'],
        ]);

        $reload = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $reload->execute(['id' => (int) $emailUser['id']]);
        return $reload->fetch() ?: $emailUser;
    }

    $passwordHash = password_hash(generate_raw_token(18), PASSWORD_DEFAULT);
    $insert = $pdo->prepare('INSERT INTO users (email, password_hash, full_name, provider, provider_id, email_verified, account_status, role, is_active, created_at, updated_at)
        VALUES (:email, :password_hash, :full_name, :provider, :provider_id, 1, "pending_payment", "user", 1, NOW(), NOW())');
    $insert->execute([
        'email' => $email,
        'password_hash' => $passwordHash,
        'full_name' => $fullName,
        'provider' => $provider,
        'provider_id' => $providerId,
    ]);

    $id = (int) $pdo->lastInsertId();
    $reload = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $reload->execute(['id' => $id]);
    return $reload->fetch() ?: [];
}

function resend_verification_for_current_user(): array
{
    $user = current_user();
    if (!$user) {
        return [false, 'Login required'];
    }
    if ((int) ($user['email_verified'] ?? 0) === 1) {
        return [false, 'Email already verified'];
    }

    $ok = send_user_verification_email((int) $user['id']);
    return [$ok, $ok ? 'Verification email sent' : 'Unable to send verification email'];
}

function generate_api_key_for_user(PDO $pdo, int $userId): array
{
    if (!user_has_feature($pdo, $userId, 'api_keys')) {
        return [false, 'API plan required'];
    }

    $rawKey = 'pc_' . bin2hex(random_bytes(24));
    $hash = api_key_hash($rawKey);
    $prefix = substr($rawKey, 0, 12);

    $sub = fetch_active_subscription_for_user($pdo, $userId);
    $dailyLimit = (int) ($sub['api_daily_limit'] ?? 1000);
    if ($dailyLimit <= 0) {
        $dailyLimit = 1000;
    }

    $insert = $pdo->prepare('INSERT INTO api_keys (user_id, api_key, api_key_hash, api_key_prefix, label, status, daily_limit, created_at)
        VALUES (:user_id, :api_key, :api_key_hash, :api_key_prefix, :label, :status, :daily_limit, NOW())');
    $insert->execute([
        'user_id' => $userId,
        'api_key' => $hash,
        'api_key_hash' => $hash,
        'api_key_prefix' => $prefix,
        'label' => 'default',
        'status' => 'active',
        'daily_limit' => $dailyLimit,
    ]);

    return [true, $rawKey, $prefix];
}

function list_api_keys_for_user(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT id, api_key_prefix, label, status, daily_limit, last_used_at, created_at
        FROM api_keys
        WHERE user_id = :user_id
        ORDER BY id DESC');
    $stmt->execute(['user_id' => $userId]);
    return $stmt->fetchAll();
}

function revoke_api_key_for_user(PDO $pdo, int $userId, int $keyId): bool
{
    $stmt = $pdo->prepare('UPDATE api_keys SET status = "revoked" WHERE id = :id AND user_id = :user_id');
    $stmt->execute([
        'id' => $keyId,
        'user_id' => $userId,
    ]);
    return $stmt->rowCount() > 0;
}
