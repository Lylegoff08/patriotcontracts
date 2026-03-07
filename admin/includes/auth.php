<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/functions.php';

function admin_normalize_role(?array $user): string
{
    if (!$user) {
        return '';
    }

    $adminRole = strtolower(trim((string) ($user['admin_role'] ?? '')));
    if (in_array($adminRole, ['super_admin', 'admin', 'editor'], true)) {
        return $adminRole;
    }

    $legacyRole = strtolower(trim((string) ($user['role'] ?? '')));
    if (in_array($legacyRole, ['super_admin', 'admin', 'editor'], true)) {
        return $legacyRole;
    }

    return '';
}

function admin_current_user(): ?array
{
    $user = current_user();
    if (!$user) {
        return null;
    }

    $role = admin_normalize_role($user);
    if ($role === '') {
        return null;
    }

    $user['effective_admin_role'] = $role;
    return $user;
}

function admin_login_url(string $next = ''): string
{
    $base = rtrim((string) (app_config()['app']['base_url'] ?? ''), '/');
    $url = $base . '/admin/login.php';
    if ($next !== '') {
        $url .= '?next=' . urlencode($next);
    }
    return $url;
}

function require_admin_auth(array $allowedRoles = ['editor', 'admin', 'super_admin']): array
{
    $admin = admin_current_user();
    if (!$admin) {
        $next = (string) ($_SERVER['REQUEST_URI'] ?? '/admin/dashboard.php');
        header('Location: ' . admin_login_url($next));
        exit;
    }

    if (!in_array((string) $admin['effective_admin_role'], $allowedRoles, true)) {
        http_response_code(403);
        exit('Admin permission denied');
    }

    return $admin;
}

function admin_json_denied(): void
{
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Admin permission denied']);
    exit;
}

function require_admin_auth_json(array $allowedRoles = ['editor', 'admin', 'super_admin']): array
{
    $admin = admin_current_user();
    if (!$admin) {
        admin_json_denied();
    }

    if (!in_array((string) $admin['effective_admin_role'], $allowedRoles, true)) {
        admin_json_denied();
    }

    return $admin;
}

function admin_log_activity(PDO $pdo, int $adminUserId, string $action, string $entityType, ?int $entityId = null, array $details = []): void
{
    $stmt = $pdo->prepare('INSERT INTO admin_activity_log (admin_user_id, action, entity_type, entity_id, details_json, created_at)
        VALUES (:admin_user_id, :action, :entity_type, :entity_id, :details_json, NOW())');
    $stmt->execute([
        'admin_user_id' => $adminUserId,
        'action' => $action,
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'details_json' => $details ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
    ]);
}

function admin_slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-');
}

function admin_status_badge_class(string $status): string
{
    $status = strtolower(trim($status));
    if (in_array($status, ['active', 'success', 'published', 'visible', 'ok'], true)) {
        return 'badge badge-ok';
    }
    if (in_array($status, ['draft', 'pending', 'trialing', 'unknown'], true)) {
        return 'badge badge-warn';
    }
    return 'badge badge-danger';
}
