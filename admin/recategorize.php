<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../ingest/recategorize_contracts.php';

$user = current_user();
if (!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    die('Admin access required');
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(recategorize_contracts(db()), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
