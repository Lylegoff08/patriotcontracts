<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/stripe.php';
require_once __DIR__ . '/../includes/membership.php';

$payload = file_get_contents('php://input') ?: '';
$sig = (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');

if (!verify_stripe_signature($payload, $sig)) {
    http_response_code(400);
    echo 'Invalid signature';
    exit;
}

$event = json_decode($payload, true);
if (!is_array($event)) {
    http_response_code(400);
    echo 'Invalid payload';
    exit;
}

$eventId = (string) ($event['id'] ?? '');
$eventType = (string) ($event['type'] ?? '');
if ($eventId === '' || $eventType === '') {
    http_response_code(400);
    echo 'Missing event id/type';
    exit;
}

$pdo = db();

$insertEvent = $pdo->prepare('INSERT INTO webhook_events (provider, event_id, event_type, payload_json, created_at)
    VALUES ("stripe", :event_id, :event_type, :payload_json, NOW())
    ON DUPLICATE KEY UPDATE event_type = VALUES(event_type)');
$insertEvent->execute([
    'event_id' => $eventId,
    'event_type' => $eventType,
    'payload_json' => $payload,
]);

function webhook_find_user_id(array $obj): int
{
    $metadata = $obj['metadata'] ?? [];
    if (!empty($metadata['user_id'])) {
        return (int) $metadata['user_id'];
    }
    if (!empty($obj['client_reference_id'])) {
        return (int) $obj['client_reference_id'];
    }
    return 0;
}

function webhook_plan_id_by_code(PDO $pdo, string $code): int
{
    $stmt = $pdo->prepare('SELECT id FROM subscription_plans WHERE plan_code = :plan_code LIMIT 1');
    $stmt->execute(['plan_code' => strtoupper($code)]);
    return (int) ($stmt->fetchColumn() ?: 0);
}

function webhook_apply_status(PDO $pdo, int $userId, string $stripeCustomerId, string $stripeSubscriptionId, string $checkoutSessionId, string $planCode, string $status, ?int $periodEndTs = null): void
{
    $planId = webhook_plan_id_by_code($pdo, $planCode);
    if ($planId <= 0) {
        return;
    }

    $periodEnd = $periodEndTs ? date('Y-m-d H:i:s', $periodEndTs) : null;

    $existing = null;
    if ($stripeSubscriptionId !== '') {
        $find = $pdo->prepare('SELECT id FROM subscriptions WHERE stripe_subscription_id = :sid LIMIT 1');
        $find->execute(['sid' => $stripeSubscriptionId]);
        $existing = $find->fetchColumn();
    }
    if (!$existing && $checkoutSessionId !== '') {
        $find = $pdo->prepare('SELECT id FROM subscriptions WHERE stripe_checkout_session_id = :cid LIMIT 1');
        $find->execute(['cid' => $checkoutSessionId]);
        $existing = $find->fetchColumn();
    }

    if ($existing) {
        $update = $pdo->prepare('UPDATE subscriptions
            SET user_id = :user_id,
                plan_id = :plan_id,
                stripe_customer_id = :customer_id,
                stripe_subscription_id = :subscription_id,
                stripe_checkout_session_id = :checkout_id,
                status = :status,
                current_period_end = :current_period_end,
                updated_at = NOW()
            WHERE id = :id');
        $update->execute([
            'user_id' => $userId,
            'plan_id' => $planId,
            'customer_id' => $stripeCustomerId !== '' ? $stripeCustomerId : null,
            'subscription_id' => $stripeSubscriptionId !== '' ? $stripeSubscriptionId : null,
            'checkout_id' => $checkoutSessionId !== '' ? $checkoutSessionId : null,
            'status' => $status,
            'current_period_end' => $periodEnd,
            'id' => (int) $existing,
        ]);
    } else {
        $insert = $pdo->prepare('INSERT INTO subscriptions
            (user_id, plan_id, stripe_customer_id, stripe_subscription_id, stripe_checkout_session_id, status, current_period_end, starts_at, created_at, updated_at)
            VALUES (:user_id, :plan_id, :customer_id, :subscription_id, :checkout_id, :status, :current_period_end, NOW(), NOW(), NOW())');
        $insert->execute([
            'user_id' => $userId,
            'plan_id' => $planId,
            'customer_id' => $stripeCustomerId !== '' ? $stripeCustomerId : null,
            'subscription_id' => $stripeSubscriptionId !== '' ? $stripeSubscriptionId : null,
            'checkout_id' => $checkoutSessionId !== '' ? $checkoutSessionId : null,
            'status' => $status,
            'current_period_end' => $periodEnd,
        ]);
    }

    $upUser = $pdo->prepare('UPDATE users SET stripe_customer_id = :customer_id, updated_at = NOW() WHERE id = :id');
    $upUser->execute(['customer_id' => $stripeCustomerId !== '' ? $stripeCustomerId : null, 'id' => $userId]);

    set_user_account_status_from_subscription($pdo, $userId);
}

function webhook_user_id_by_customer(PDO $pdo, string $customerId): int
{
    if ($customerId === '') {
        return 0;
    }
    $stmt = $pdo->prepare('SELECT id FROM users WHERE stripe_customer_id = :customer_id LIMIT 1');
    $stmt->execute(['customer_id' => $customerId]);
    return (int) ($stmt->fetchColumn() ?: 0);
}

$obj = $event['data']['object'] ?? [];

try {
    if ($eventType === 'checkout.session.completed') {
        $userId = webhook_find_user_id($obj);
        if ($userId <= 0 && !empty($obj['customer'])) {
            $userId = webhook_user_id_by_customer($pdo, (string) $obj['customer']);
        }
        if ($userId > 0) {
            $planCode = strtoupper((string) (($obj['metadata']['plan_code'] ?? '') ?: 'MEMBER_BASIC'));
            webhook_apply_status(
                $pdo,
                $userId,
                (string) ($obj['customer'] ?? ''),
                (string) ($obj['subscription'] ?? ''),
                (string) ($obj['id'] ?? ''),
                $planCode,
                'active',
                null
            );
        }
    } elseif ($eventType === 'invoice.payment_succeeded') {
        $customerId = (string) ($obj['customer'] ?? '');
        $userId = webhook_user_id_by_customer($pdo, $customerId);
        if ($userId > 0) {
            $subId = (string) ($obj['subscription'] ?? '');
            $planCode = 'MEMBER_BASIC';
            if ($subId !== '') {
                $existingPlan = $pdo->prepare('SELECT p.plan_code FROM subscriptions s JOIN subscription_plans p ON p.id = s.plan_id WHERE s.stripe_subscription_id = :sid ORDER BY s.id DESC LIMIT 1');
                $existingPlan->execute(['sid' => $subId]);
                $planFromDb = strtoupper((string) ($existingPlan->fetchColumn() ?: ''));
                if (in_array($planFromDb, membership_plan_codes(), true)) {
                    $planCode = $planFromDb;
                }
            }
            $linePlan = strtoupper((string) ($obj['lines']['data'][0]['price']['nickname'] ?? ''));
            if (in_array($linePlan, ['MEMBER_BASIC', 'MEMBER_PRO', 'API_MEMBER'], true)) {
                $planCode = $linePlan;
            }
            webhook_apply_status($pdo, $userId, $customerId, $subId, '', $planCode, 'active', (int) ($obj['period_end'] ?? 0));
        }
    } elseif ($eventType === 'invoice.payment_failed') {
        $customerId = (string) ($obj['customer'] ?? '');
        $userId = webhook_user_id_by_customer($pdo, $customerId);
        if ($userId > 0) {
            $subId = (string) ($obj['subscription'] ?? '');
            $up = $pdo->prepare('UPDATE subscriptions SET status = "past_due", updated_at = NOW() WHERE stripe_subscription_id = :sid');
            $up->execute(['sid' => $subId]);
            $u = $pdo->prepare('UPDATE users SET account_status = "suspended", updated_at = NOW() WHERE id = :id');
            $u->execute(['id' => $userId]);
        }
    } elseif ($eventType === 'customer.subscription.deleted' || $eventType === 'customer.subscription.updated') {
        $subId = (string) ($obj['id'] ?? '');
        $customerId = (string) ($obj['customer'] ?? '');
        $status = (string) ($obj['status'] ?? 'cancelled');

        $userId = webhook_user_id_by_customer($pdo, $customerId);
        if ($userId > 0) {
            $mapped = in_array($status, ['active', 'trialing'], true) ? 'active' : ($status === 'past_due' ? 'past_due' : 'cancelled');
            $up = $pdo->prepare('UPDATE subscriptions SET status = :status, current_period_end = :period_end, updated_at = NOW() WHERE stripe_subscription_id = :sid');
            $up->execute([
                'status' => $mapped,
                'period_end' => !empty($obj['current_period_end']) ? date('Y-m-d H:i:s', (int) $obj['current_period_end']) : null,
                'sid' => $subId,
            ]);

            if ($mapped === 'active') {
                set_user_account_status_from_subscription($pdo, $userId);
            } elseif ($mapped === 'past_due') {
                $u = $pdo->prepare('UPDATE users SET account_status = "suspended", updated_at = NOW() WHERE id = :id');
                $u->execute(['id' => $userId]);
            } else {
                $u = $pdo->prepare('UPDATE users SET account_status = "cancelled", updated_at = NOW() WHERE id = :id');
                $u->execute(['id' => $userId]);
            }
        }
    }

    $mark = $pdo->prepare('UPDATE webhook_events SET processed_at = NOW() WHERE provider = "stripe" AND event_id = :event_id');
    $mark->execute(['event_id' => $eventId]);

    http_response_code(200);
    echo 'ok';
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Webhook processing failed';
}
