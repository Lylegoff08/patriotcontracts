<?php

require_once __DIR__ . '/../includes/ingest_common.php';

function ingest_sam_awards(PDO $pdo, array $config): array
{
    $startedAt = date('Y-m-d H:i:s');
    $sourceId = source_id_by_slug($pdo, 'sam_awards');
    $fetched = 0;
    $processed = 0;

    if (!$sourceId) {
        throw new RuntimeException('Source sam_awards not configured in sources table');
    }

    $apiKey = trim((string) ($config['sources']['sam_awards']['api_key'] ?? ''));
    if ($apiKey === '') {
        $msg = 'SAM awards API key is missing in config.php';
        log_ingest($pdo, $sourceId, 'ingest_sam_awards.php', 'error', $msg, 0, 0, $startedAt);
        return ['fetched' => 0, 'processed' => 0, 'status' => 'error', 'message' => $msg];
    }

    $daysBack = (int) ($config['ingest']['days_back'] ?? 30);
    $from = date('Y-m-d', strtotime('-' . $daysBack . ' days'));
    $to = date('Y-m-d');

    $limit = (int) ($config['ingest']['page_size'] ?? 100);
    $queryCandidates = [
        [
            'api_key' => $apiKey,
            'dateFrom' => $from,
            'dateTo' => $to,
            'limit' => $limit,
            'offset' => 0,
        ],
        [
            'api_key' => $apiKey,
            'signedDateFrom' => $from,
            'signedDateTo' => $to,
            'limit' => $limit,
            'offset' => 0,
        ],
    ];

    $baseUrl = rtrim($config['sources']['sam_awards']['base_url'], '?');
    $response = null;
    $lastErr = null;
    foreach ($queryCandidates as $query) {
        try {
            $url = $baseUrl . '?' . http_build_query($query);
            $candidate = http_get_json($url);
            if ((int) $candidate['status'] >= 200 && (int) $candidate['status'] < 300) {
                $response = $candidate;
                break;
            }
        } catch (Throwable $e) {
            $lastErr = $e->getMessage();
        }
    }
    if (!$response) {
        $msg = 'SAM awards request failed' . ($lastErr ? ': ' . $lastErr : '');
        log_ingest($pdo, $sourceId, 'ingest_sam_awards.php', 'error', $msg, 0, 0, $startedAt);
        return ['fetched' => 0, 'processed' => 0, 'status' => 'error', 'message' => $msg];
    }

    $rows = $response['data']['data'] ?? $response['data']['results'] ?? [];
    foreach ($rows as $row) {
        $sourceRecordId = (string) ($row['awardId'] ?? $row['piid'] ?? $row['id'] ?? '');
        $sourceUrl = $row['awardUrl'] ?? 'https://sam.gov/content/home';
        upsert_raw_record($pdo, $sourceId, $sourceRecordId, $sourceUrl, $row);
        $fetched++;
    }

    $processed = $fetched;
    $message = 'HTTP ' . $response['status'] . ' received, rows=' . $fetched;
    log_ingest($pdo, $sourceId, 'ingest_sam_awards.php', 'success', $message, $fetched, $processed, $startedAt);

    return ['fetched' => $fetched, 'processed' => $processed, 'status' => 'success', 'message' => $message];
}

if (php_sapi_name() === 'cli' || basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $result = ingest_sam_awards(db(), app_config());
    echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
}
