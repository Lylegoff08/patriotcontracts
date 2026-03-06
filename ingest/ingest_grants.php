<?php

require_once __DIR__ . '/../includes/ingest_common.php';

function ingest_grants(PDO $pdo, array $config): array
{
    $startedAt = date('Y-m-d H:i:s');
    $sourceId = source_id_by_slug($pdo, 'grants');
    $fetched = 0;
    $processed = 0;

    if (!$sourceId) {
        return ['fetched' => 0, 'processed' => 0, 'status' => 'skipped', 'message' => 'Source grants not configured'];
    }

    $daysBack = (int) ($config['ingest']['days_back'] ?? 30);
    $from = date('m/d/Y', strtotime('-' . $daysBack . ' days'));
    $to = date('m/d/Y');
    $limit = (int) ($config['ingest']['page_size'] ?? 100);
    $baseUrl = rtrim((string) ($config['sources']['grants']['base_url'] ?? 'https://api.grants.gov/v1/api/search2'), '/');

    $payload = [
        'rows' => $limit,
        'startRecordNum' => 0,
        'postedFrom' => $from,
        'postedTo' => $to,
        'oppStatuses' => ['posted', 'forecasted', 'closed'],
        'sortBy' => 'openDate|desc',
    ];

    $responseData = null;
    $statusCode = 0;
    $errors = [];

    try {
        $ch = curl_init($baseUrl);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 40,
        ];
        $options = $options + ingest_ssl_curl_options();
        curl_setopt_array($ch, $options);
        $resp = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($resp === false || $err) {
            throw new RuntimeException('POST failed: ' . $err);
        }
        $decoded = json_decode($resp, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('POST returned invalid JSON');
        }
        $responseData = $decoded;
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }

    if (!is_array($responseData)) {
        try {
            $query = [
                'rows' => $limit,
                'startRecordNum' => 0,
                'postedFrom' => $from,
                'postedTo' => $to,
            ];
            $url = $baseUrl . '?' . http_build_query($query);
            $response = http_get_json($url);
            $statusCode = (int) $response['status'];
            $responseData = $response['data'];
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (!is_array($responseData)) {
        $msg = 'Grants ingest failed: ' . implode(' | ', $errors);
        log_ingest($pdo, $sourceId, 'ingest_grants.php', 'error', $msg, 0, 0, $startedAt);
        return ['fetched' => 0, 'processed' => 0, 'status' => 'error', 'message' => $msg];
    }

    $rows = $responseData['oppHits'] ?? $responseData['data'] ?? $responseData['results'] ?? [];
    foreach ($rows as $row) {
        $sourceRecordId = (string) ($row['opportunityNumber'] ?? $row['id'] ?? '');
        $sourceUrl = (string) ($row['url'] ?? 'https://www.grants.gov/');
        $row['source_family'] = 'grants_assistance';
        upsert_raw_record($pdo, $sourceId, $sourceRecordId, $sourceUrl, $row);
        $fetched++;
    }

    $processed = $fetched;
    $message = 'HTTP ' . $statusCode . ' received, rows=' . $fetched;
    log_ingest($pdo, $sourceId, 'ingest_grants.php', 'success', $message, $fetched, $processed, $startedAt);

    return ['fetched' => $fetched, 'processed' => $processed, 'status' => 'success', 'message' => $message];
}

if (php_sapi_name() === 'cli' || basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $result = ingest_grants(db(), app_config());
    echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
}
