<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/../ingest/recategorize_contracts.php';

$adminUser = require_admin_auth_json(['admin', 'super_admin']);
if (!admin_can_run_ingestion($adminUser)) {
    admin_json_denied();
}

header('Content-Type: application/json; charset=utf-8');
$result = recategorize_contracts(db());
admin_log_activity(db(), (int) $adminUser['id'], 'ingest_recategorize_run', 'ingest', null, $result);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);