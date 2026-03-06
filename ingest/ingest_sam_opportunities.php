<?php

require_once __DIR__ . '/../includes/ingest_common.php';

function ingest_sam_opportunities(PDO $pdo, array $config): array
{
    $startedAt = date('Y-m-d H:i:s');
    $sourceId = source_id_by_slug($pdo, 'sam_opportunities');
    $fetched = 0;
    $processed = 0;

    if (!$sourceId) {
        throw new RuntimeException('Source sam_opportunities not configured in sources table');
    }

    $apiKey = trim((string) ($config['sources']['sam_opportunities']['api_key'] ?? ''));
    if ($apiKey === '') {
        $msg = 'SAM opportunities API key is missing in config.php';
        log_ingest($pdo, $sourceId, 'ingest_sam_opportunities.php', 'error', $msg, 0, 0, $startedAt);
        return ['fetched' => 0, 'processed' => 0, 'status' => 'error', 'message' => $msg];
    }

    $daysBack = (int) ($config['ingest']['days_back'] ?? 30);
    $startDate = date('m/d/Y', strtotime('-' . $daysBack . ' days'));
    $endDate = date('m/d/Y');

    $query = [
        'api_key' => $apiKey,
        'postedFrom' => $startDate,
        'postedTo' => $endDate,
        'limit' => (int) ($config['ingest']['page_size'] ?? 100),
        'offset' => 0,
    ];

    $url = rtrim($config['sources']['sam_opportunities']['base_url'], '?') . '?' . http_build_query($query);
    $response = http_get_json($url);

    $rows = $response['data']['opportunitiesData'] ?? [];
    foreach ($rows as $row) {
        $sourceRecordId = (string) ($row['noticeId'] ?? $row['solicitationNumber'] ?? '');
        $sourceUrl = $row['uiLink'] ?? 'https://sam.gov/content/opportunities';
        upsert_raw_record($pdo, $sourceId, $sourceRecordId, $sourceUrl, $row);
        $fetched++;
    }

    $processed = $fetched;
    $message = 'HTTP ' . $response['status'] . ' received, rows=' . $fetched;
    log_ingest($pdo, $sourceId, 'ingest_sam_opportunities.php', 'success', $message, $fetched, $processed, $startedAt);

    return ['fetched' => $fetched, 'processed' => $processed, 'status' => 'success', 'message' => $message];
}

if (php_sapi_name() === 'cli' || basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $result = ingest_sam_opportunities(db(), app_config());
    echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
}
