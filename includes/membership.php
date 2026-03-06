<?php

require_once __DIR__ . '/db.php';

function membership_plan_codes(): array
{
    return ['MEMBER_BASIC', 'MEMBER_PRO', 'API_MEMBER'];
}

function fetch_active_subscription_for_user(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare('SELECT s.*, p.plan_code, p.name, p.price_monthly, p.feature_flags_json, p.api_daily_limit
        FROM subscriptions s
        JOIN subscription_plans p ON p.id = s.plan_id
        WHERE s.user_id = :user_id
          AND s.status IN ("active", "trialing")
        ORDER BY s.id DESC
        LIMIT 1');
    $stmt->execute(['user_id' => $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function feature_flags_for_plan_code(string $planCode): array
{
    $planCode = strtoupper($planCode);
    if ($planCode === 'API_MEMBER') {
        return [
            'member_tools' => true,
            'advanced_search' => true,
            'saved_searches' => true,
            'alerts' => true,
            'csv_export' => true,
            'api_access' => true,
            'api_keys' => true,
        ];
    }
    if ($planCode === 'MEMBER_PRO') {
        return [
            'member_tools' => true,
            'advanced_search' => true,
            'saved_searches' => true,
            'alerts' => true,
            'csv_export' => true,
            'api_access' => false,
            'api_keys' => false,
        ];
    }
    if ($planCode === 'MEMBER_BASIC') {
        return [
            'member_tools' => true,
            'advanced_search' => false,
            'saved_searches' => false,
            'alerts' => false,
            'csv_export' => false,
            'api_access' => false,
            'api_keys' => false,
        ];
    }
    return [
        'member_tools' => false,
        'advanced_search' => false,
        'saved_searches' => false,
        'alerts' => false,
        'csv_export' => false,
        'api_access' => false,
        'api_keys' => false,
    ];
}

function feature_flags_for_subscription(?array $subscription): array
{
    if (!$subscription) {
        return feature_flags_for_plan_code('');
    }

    $flags = feature_flags_for_plan_code((string) ($subscription['plan_code'] ?? ''));
    $json = (string) ($subscription['feature_flags_json'] ?? '');
    if ($json !== '') {
        $decoded = json_decode($json, true);
        if (is_array($decoded)) {
            foreach ($decoded as $key => $value) {
                $flags[(string) $key] = (bool) $value;
            }
        }
    }
    return $flags;
}

function user_feature_flags(PDO $pdo, int $userId): array
{
    return feature_flags_for_subscription(fetch_active_subscription_for_user($pdo, $userId));
}

function user_has_feature(PDO $pdo, int $userId, string $feature): bool
{
    $flags = user_feature_flags($pdo, $userId);
    return !empty($flags[$feature]);
}

function account_status_after_subscription(bool $hasActivePayment, bool $emailVerified, string $provider): string
{
    if (!$hasActivePayment) {
        return 'pending_payment';
    }
    if ($provider === 'email' && !$emailVerified) {
        return 'pending_email_verification';
    }
    return 'active';
}

function set_user_account_status_from_subscription(PDO $pdo, int $userId): void
{
    $userStmt = $pdo->prepare('SELECT provider, email_verified FROM users WHERE id = :id LIMIT 1');
    $userStmt->execute(['id' => $userId]);
    $user = $userStmt->fetch();
    if (!$user) {
        return;
    }

    $subscription = fetch_active_subscription_for_user($pdo, $userId);
    $hasActivePayment = $subscription !== null;
    $status = account_status_after_subscription($hasActivePayment, (int) ($user['email_verified'] ?? 0) === 1, (string) ($user['provider'] ?? 'email'));

    $update = $pdo->prepare('UPDATE users SET account_status = :status, updated_at = NOW() WHERE id = :id');
    $update->execute(['status' => $status, 'id' => $userId]);
}

function require_feature_or_403(PDO $pdo, int $userId, string $feature, string $message = 'Feature requires an upgraded plan'): void
{
    if (!user_has_feature($pdo, $userId, $feature)) {
        http_response_code(403);
        exit($message);
    }
}
