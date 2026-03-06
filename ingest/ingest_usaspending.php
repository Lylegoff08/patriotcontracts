<?php

require_once __DIR__ . '/../includes/ingest_common.php';

function ingest_usaspending(PDO $pdo, array $config): array
{
    $startedAt = date('Y-m-d H:i:s');
    $sourceId = source_id_by_slug($pdo, 'usaspending');
    $fetched = 0;
    $processed = 0;

    if (!$sourceId) {
        throw new RuntimeException('Source usaspending not configured in sources table');
    }

    $daysBack = (int) ($config['ingest']['days_back'] ?? 30);
    $limit = (int) ($config['ingest']['page_size'] ?? 100);
    $maxPages = (int) ($config['ingest']['max_pages_per_run'] ?? 10);

    $startDate = (string) ($config['ingest']['usaspending_start_date'] ?? date('Y-m-d', strtotime('-' . $daysBack . ' days')));
    $endDate = (string) ($config['ingest']['usaspending_end_date'] ?? date('Y-m-d'));

    $url = rtrim($config['sources']['usaspending']['base_url'], '/') . '/api/v2/search/spending_by_award/';

    $body = [
        'filters' => [
            'time_period' => [[
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]],
            'award_type_codes' => ['A', 'B', 'C', 'D'],
        ],
        'fields' => [
            'Award ID',
            'Recipient Name',
            'Award Amount',
            'Start Date',
            'End Date',
            'Awarding Agency',
            'Awarding Sub Agency',
            'generated_internal_id',
        ],
        'sort' => 'Award Amount',
        'order' => 'desc',
    ];

    for ($page = 1; $page <= $maxPages; $page++) {
        $body['limit'] = $limit;
        $body['page'] = $page;

        $ch = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 40,
        ];
        $options = $options + ingest_ssl_curl_options();
        curl_setopt_array($ch, $options);
        $resp = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($resp === false || $err) {
            log_ingest($pdo, $sourceId, 'ingest_usaspending.php', 'error', 'HTTP error page ' . $page . ': ' . $err, $fetched, $processed, $startedAt);
            return ['fetched' => $fetched, 'processed' => $processed, 'status' => 'error', 'message' => $err];
        }

        if ($status < 200 || $status >= 300) {
            log_ingest($pdo, $sourceId, 'ingest_usaspending.php', 'error', 'HTTP status ' . $status . ' on page ' . $page, $fetched, $processed, $startedAt);
            return ['fetched' => $fetched, 'processed' => $processed, 'status' => 'error', 'message' => 'HTTP status ' . $status];
        }

        $json = json_decode($resp, true);
        $results = $json['results'] ?? [];

        if (!$results) {
            break;
        }

        foreach ($results as $row) {
            $sourceRecordId = (string) ($row['generated_internal_id'] ?? $row['Award ID'] ?? '');
            $sourceUrl = 'https://www.usaspending.gov/';
            upsert_raw_record($pdo, $sourceId, $sourceRecordId, $sourceUrl, $row);
            $fetched++;
        }

        if (count($results) < $limit) {
            break;
        }
    }

    $processed = $fetched;
    $message = 'Fetched rows=' . $fetched . ' across up to ' . $maxPages . ' pages';
    log_ingest($pdo, $sourceId, 'ingest_usaspending.php', 'success', $message, $fetched, $processed, $startedAt);

    return ['fetched' => $fetched, 'processed' => $processed, 'status' => 'success', 'message' => $message];
}

if (php_sapi_name() === 'cli' || basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $config = app_config();
    $result = ingest_usaspending(db(), $config);
    echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
}
