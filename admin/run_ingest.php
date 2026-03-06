<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../ingest/ingest_usaspending.php';
require_once __DIR__ . '/../ingest/ingest_sam_opportunities.php';
require_once __DIR__ . '/../ingest/ingest_sam_awards.php';
require_once __DIR__ . '/../ingest/normalize_contracts.php';
require_once __DIR__ . '/../ingest/recategorize_contracts.php';
require_once __DIR__ . '/../ingest/calculate_stats.php';
require_once __DIR__ . '/../ingest/run_alerts.php';
if (file_exists(__DIR__ . '/../ingest/ingest_grants.php')) {
    require_once __DIR__ . '/../ingest/ingest_grants.php';
}

$user = current_user();
if (!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    die('Admin access required');
}

$pdo = db();
$config = app_config();
$results = [];

$runSource = request_int('run_source', 1) === 1;
$runNormalize = request_int('run_normalize', 1) === 1;
$runRecategorize = request_int('run_recategorize', 1) === 1;
$runStats = request_int('run_stats', 1) === 1;
$runAlerts = request_int('run_alerts', 1) === 1;

if ($runSource) {
    $results['usaspending'] = ingest_usaspending($pdo, $config);
    $results['sam_opportunities'] = ingest_sam_opportunities($pdo, $config);
    $results['sam_awards'] = ingest_sam_awards($pdo, $config);
    if (function_exists('ingest_grants')) {
        $results['grants'] = ingest_grants($pdo, $config);
    }
}
if ($runNormalize) {
    $results['normalize'] = normalize_contracts($pdo);
}
if ($runRecategorize) {
    $results['recategorize'] = recategorize_contracts($pdo);
}
if ($runStats) {
    $results['stats'] = calculate_stats($pdo);
}
if ($runAlerts) {
    $results['alerts'] = run_alerts($pdo);
}

header('Content-Type: application/json');
echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
