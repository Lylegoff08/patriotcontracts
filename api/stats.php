<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/api_auth.php';
$pdo = db();
api_authenticate($pdo, '/api/stats.php');

$stats = $pdo->query('SELECT metric_key, metric_value, stat_date FROM stats_daily WHERE stat_date = CURDATE() ORDER BY metric_key ASC')->fetchAll();

if (!$stats) {
    $stats = $pdo->query('SELECT metric_key, metric_value, stat_date FROM stats_daily ORDER BY stat_date DESC, metric_key ASC LIMIT 100')->fetchAll();
}

json_response(['data' => $stats]);
