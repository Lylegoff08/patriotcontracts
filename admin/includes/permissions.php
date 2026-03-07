<?php

require_once __DIR__ . '/auth.php';

function admin_role_rank(string $role): int
{
    $map = [
        'editor' => 10,
        'admin' => 20,
        'super_admin' => 30,
    ];
    return $map[$role] ?? 0;
}

function admin_has_role(array $adminUser, string $minimumRole): bool
{
    $current = (string) ($adminUser['effective_admin_role'] ?? '');
    return admin_role_rank($current) >= admin_role_rank($minimumRole);
}

function admin_require_role(string $minimumRole): array
{
    $admin = require_admin_auth();
    if (!admin_has_role($admin, $minimumRole)) {
        http_response_code(403);
        exit('Admin permission denied');
    }
    return $admin;
}

function admin_can_manage_content(array $adminUser): bool
{
    return admin_has_role($adminUser, 'editor');
}

function admin_can_manage_moderation(array $adminUser): bool
{
    return admin_has_role($adminUser, 'admin');
}

function admin_can_manage_settings(array $adminUser): bool
{
    return admin_has_role($adminUser, 'admin');
}

function admin_can_manage_users(array $adminUser): bool
{
    return admin_has_role($adminUser, 'admin');
}

function admin_can_manage_roles(array $adminUser): bool
{
    return admin_has_role($adminUser, 'super_admin');
}

function admin_can_view_ingestion(array $adminUser): bool
{
    return admin_has_role($adminUser, 'admin');
}

function admin_can_run_ingestion(array $adminUser): bool
{
    return admin_has_role($adminUser, 'admin');
}