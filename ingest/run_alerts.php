<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ingest_common.php';

function run_alerts(PDO $pdo): array
{
    $startedAt = date('Y-m-d H:i:s');
    $alerts = $pdo->query('SELECT a.id, a.user_id, ss.query_json FROM alerts a LEFT JOIN saved_searches ss ON ss.id = a.saved_search_id WHERE a.is_active = 1')->fetchAll();

    $processed = 0;
    foreach ($alerts as $alert) {
        $query = json_decode((string) ($alert['query_json'] ?? '{}'), true) ?: [];

        $sql = 'SELECT COUNT(*) FROM contracts_clean WHERE is_duplicate = 0';
        $params = [];

        if (!empty($query['keyword'])) {
            $sql .= ' AND (title LIKE :kw OR COALESCE(NULLIF(description_clean, ""), NULLIF(description_raw, ""), description) LIKE :kw)';
            $params['kw'] = '%' . $query['keyword'] . '%';
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $count = (int) $stmt->fetchColumn();

        $line = sprintf("[%s] alert_id=%d user_id=%d matches=%d\n", date('Y-m-d H:i:s'), $alert['id'], $alert['user_id'], $count);
        file_put_contents(__DIR__ . '/../logs/alerts.log', $line, FILE_APPEND);

        $update = $pdo->prepare('UPDATE alerts SET last_run_at = NOW() WHERE id = :id');
        $update->execute(['id' => (int) $alert['id']]);

        $processed++;
    }

    log_ingest($pdo, null, 'run_alerts.php', 'success', 'Alerts processed', count($alerts), $processed, $startedAt);
    return ['status' => 'success', 'processed' => $processed];
}

if (php_sapi_name() === 'cli' || basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $result = run_alerts(db());
    echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
}
