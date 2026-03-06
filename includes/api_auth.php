<?php

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/membership.php';
require_once __DIR__ . '/security.php';

function api_key_from_request(): string
{
    $key = request_str('api_key');
    if ($key !== '') {
        return $key;
    }

    $headers = function_exists('getallheaders') ? getallheaders() : [];
    if (!empty($headers['X-API-Key'])) {
        return trim((string) $headers['X-API-Key']);
    }
    if (!empty($headers['Authorization']) && preg_match('/Bearer\s+([A-Za-z0-9_\-]+)/', (string) $headers['Authorization'], $m)) {
        return trim($m[1]);
    }

    return '';
}

function is_localhost_request(): bool
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return in_array($ip, ['127.0.0.1', '::1'], true);
}

function api_usage_count_today(PDO $pdo, int $apiKeyId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM api_usage WHERE api_key_id = :api_key_id AND DATE(requested_at) = CURDATE()');
    $stmt->execute(['api_key_id' => $apiKeyId]);
    return (int) $stmt->fetchColumn();
}

function api_authenticate(PDO $pdo, string $endpoint): ?array
{
    $cfg = app_config()['api'] ?? [];
    $requireKey = (bool) ($cfg['require_key'] ?? false);
    $allowLocal = (bool) ($cfg['dev_bypass_localhost'] ?? true);

    if (!$requireKey || ($allowLocal && is_localhost_request())) {
        return null;
    }

    $rawKey = api_key_from_request();
    if ($rawKey === '') {
        json_response(['error' => 'API key required'], 401);
    }

    $hash = api_key_hash($rawKey);
    $stmt = $pdo->prepare('SELECT k.id, k.user_id, k.daily_limit, k.status,
            u.account_status, u.is_active,
            p.plan_code, COALESCE(p.api_daily_limit, k.daily_limit) AS effective_limit,
            s.status AS subscription_status
        FROM api_keys k
        JOIN users u ON u.id = k.user_id
        LEFT JOIN subscriptions s ON s.user_id = u.id AND s.status IN ("active", "trialing")
        LEFT JOIN subscription_plans p ON p.id = s.plan_id
        WHERE k.api_key_hash = :hash
        ORDER BY s.id DESC
        LIMIT 1');
    $stmt->execute(['hash' => $hash]);
    $row = $stmt->fetch();

    if (!$row || (string) ($row['status'] ?? '') !== 'active') {
        json_response(['error' => 'Invalid API key'], 401);
    }

    if ((int) ($row['is_active'] ?? 0) !== 1 || (string) ($row['account_status'] ?? '') !== 'active') {
        json_response(['error' => 'Inactive account'], 403);
    }

    if ((string) ($row['subscription_status'] ?? '') !== 'active' && (string) ($row['subscription_status'] ?? '') !== 'trialing') {
        json_response(['error' => 'Inactive membership'], 403);
    }

    if (strtoupper((string) ($row['plan_code'] ?? '')) !== 'API_MEMBER') {
        json_response(['error' => 'API membership required'], 403);
    }

    $usedToday = api_usage_count_today($pdo, (int) $row['id']);
    $limit = max(1, (int) ($row['effective_limit'] ?? $row['daily_limit'] ?? 1000));
    if ($usedToday >= $limit) {
        json_response(['error' => 'Daily limit exceeded'], 429);
    }

    $insertUsage = $pdo->prepare('INSERT INTO api_usage (api_key_id, endpoint, request_count, usage_date, request_ip, requested_at)
        VALUES (:api_key_id, :endpoint, 1, CURDATE(), :ip, NOW())');
    $insertUsage->execute([
        'api_key_id' => (int) $row['id'],
        'endpoint' => $endpoint,
        'ip' => client_ip(),
    ]);

    $touch = $pdo->prepare('UPDATE api_keys SET last_used_at = NOW() WHERE id = :id');
    $touch->execute(['id' => (int) $row['id']]);

    return $row;
}
