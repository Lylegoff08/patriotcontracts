<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/membership.php';

function stripe_config(): array
{
    return app_config()['stripe'] ?? [];
}

function stripe_secret_key(): string
{
    return trim((string) (stripe_config()['secret_key'] ?? ''));
}

function stripe_api_request(string $method, string $path, array $params = []): array
{
    $secret = stripe_secret_key();
    if ($secret === '') {
        throw new RuntimeException('Stripe secret key not configured.');
    }

    $url = 'https://api.stripe.com/v1/' . ltrim($path, '/');
    $ch = curl_init($url);

    $headers = [
        'Authorization: Bearer ' . $secret,
    ];

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_HTTPHEADER => $headers,
    ];

    if (strtoupper($method) !== 'GET') {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = http_build_query($params);
    } elseif (!empty($params)) {
        $url .= '?' . http_build_query($params);
        $options[CURLOPT_URL] = $url;
    }

    curl_setopt_array($ch, $options);
    $resp = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($resp === false || $err) {
        throw new RuntimeException('Stripe request failed: ' . $err);
    }

    $json = json_decode($resp, true);
    if (!is_array($json)) {
        throw new RuntimeException('Stripe response decode failed.');
    }

    if ($status < 200 || $status >= 300) {
        $msg = (string) ($json['error']['message'] ?? ('Stripe HTTP ' . $status));
        throw new RuntimeException($msg);
    }

    return $json;
}

function stripe_price_id_for_plan(string $planCode): string
{
    $planCode = strtoupper(trim($planCode));
    $cfgPrice = trim((string) (app_config()['plans'][$planCode]['stripe_price_id'] ?? ''));
    if ($cfgPrice !== '') {
        return $cfgPrice;
    }

    $stmt = db()->prepare('SELECT stripe_price_id FROM subscription_plans WHERE plan_code = :plan_code LIMIT 1');
    $stmt->execute(['plan_code' => $planCode]);
    return trim((string) ($stmt->fetchColumn() ?: ''));
}

function create_checkout_session(PDO $pdo, int $userId, string $planCode): array
{
    $planCode = strtoupper(trim($planCode));
    if (!in_array($planCode, membership_plan_codes(), true)) {
        throw new RuntimeException('Invalid plan code.');
    }

    $priceId = stripe_price_id_for_plan($planCode);
    if ($priceId === '') {
        throw new RuntimeException('Stripe price ID missing for plan ' . $planCode);
    }

    $stmt = $pdo->prepare('SELECT id, email, full_name, stripe_customer_id FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();
    if (!$user) {
        throw new RuntimeException('User not found.');
    }

    $base = rtrim((string) (app_config()['app']['base_url'] ?? ''), '/');

    $params = [
        'mode' => 'subscription',
        'success_url' => $base . '/billing/success.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => $base . '/billing/cancel.php',
        'line_items[0][price]' => $priceId,
        'line_items[0][quantity]' => 1,
        'metadata[user_id]' => (string) $userId,
        'metadata[plan_code]' => $planCode,
        'client_reference_id' => (string) $userId,
        'allow_promotion_codes' => 'true',
    ];

    $customerId = trim((string) ($user['stripe_customer_id'] ?? ''));
    if ($customerId !== '') {
        $params['customer'] = $customerId;
    } else {
        $params['customer_email'] = (string) $user['email'];
    }

    $session = stripe_api_request('POST', 'checkout/sessions', $params);
    $sessionId = (string) ($session['id'] ?? '');
    if ($sessionId === '') {
        throw new RuntimeException('Stripe checkout session missing ID.');
    }

    $planStmt = $pdo->prepare('SELECT id FROM subscription_plans WHERE plan_code = :plan_code LIMIT 1');
    $planStmt->execute(['plan_code' => $planCode]);
    $planId = (int) ($planStmt->fetchColumn() ?: 0);
    if ($planId <= 0) {
        throw new RuntimeException('Plan not found in database.');
    }

    $subInsert = $pdo->prepare('INSERT INTO subscriptions (user_id, plan_id, stripe_customer_id, stripe_checkout_session_id, status, starts_at, created_at, updated_at)
        VALUES (:user_id, :plan_id, :stripe_customer_id, :session_id, :status, NOW(), NOW(), NOW())');
    $subInsert->execute([
        'user_id' => $userId,
        'plan_id' => $planId,
        'stripe_customer_id' => $customerId !== '' ? $customerId : null,
        'session_id' => $sessionId,
        'status' => 'pending',
    ]);

    return $session;
}

function verify_stripe_signature(string $payload, string $signatureHeader): bool
{
    $secret = trim((string) (stripe_config()['webhook_secret'] ?? ''));
    if ($secret === '' || $signatureHeader === '') {
        return false;
    }

    $parts = [];
    foreach (explode(',', $signatureHeader) as $piece) {
        $kv = explode('=', trim($piece), 2);
        if (count($kv) === 2) {
            $parts[$kv[0]] = $kv[1];
        }
    }

    $timestamp = $parts['t'] ?? '';
    $v1 = $parts['v1'] ?? '';
    if ($timestamp === '' || $v1 === '') {
        return false;
    }

    if (abs(time() - (int) $timestamp) > 300) {
        return false;
    }

    $signedPayload = $timestamp . '.' . $payload;
    $expected = hash_hmac('sha256', $signedPayload, $secret);
    return hash_equals($expected, $v1);
}
