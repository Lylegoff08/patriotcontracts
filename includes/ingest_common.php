<?php

require_once __DIR__ . '/functions.php';

function ingest_ssl_curl_options(): array
{
    $cfg = app_config();
    $ssl = $cfg['ingest']['ssl'] ?? [];
    $verifySsl = (bool) ($ssl['verify_ssl'] ?? true);
    $caBundle = trim((string) ($ssl['ca_bundle'] ?? ''));

    $options = [
        CURLOPT_SSL_VERIFYPEER => $verifySsl,
        CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
    ];

    if ($verifySsl && $caBundle !== '' && file_exists($caBundle)) {
        $options[CURLOPT_CAINFO] = $caBundle;
    }

    return $options;
}

function http_get_json(string $url, array $headers = [], int $timeout = 30): array
{
    $ch = curl_init($url);
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => $headers,
    ];
    $options = $options + ingest_ssl_curl_options();
    curl_setopt_array($ch, $options);

    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($resp === false || $err) {
        throw new RuntimeException('HTTP request failed: ' . $err);
    }

    $decoded = json_decode($resp, true);
    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('Invalid JSON response from ' . $url . ' HTTP ' . $code);
    }

    return ['status' => $code, 'data' => $decoded];
}

function log_ingest(PDO $pdo, ?int $sourceId, string $scriptName, string $status, string $message, int $fetched, int $processed, string $startedAt): void
{
    $stmt = $pdo->prepare('INSERT INTO ingest_logs (source_id, script_name, status, message, records_fetched, records_processed, started_at, finished_at) VALUES (:source_id, :script_name, :status, :message, :fetched, :processed, :started, NOW())');
    $stmt->execute([
        'source_id' => $sourceId,
        'script_name' => $scriptName,
        'status' => $status,
        'message' => $message,
        'fetched' => $fetched,
        'processed' => $processed,
        'started' => $startedAt,
    ]);

    $line = sprintf("[%s] %s %s fetched=%d processed=%d %s\n", date('Y-m-d H:i:s'), $scriptName, strtoupper($status), $fetched, $processed, $message);
    file_put_contents(__DIR__ . '/../logs/ingest.log', $line, FILE_APPEND);
}

function upsert_raw_record(PDO $pdo, int $sourceId, ?string $sourceRecordId, ?string $sourceUrl, array $payload): int
{
    $sourceRecordId = trim((string) $sourceRecordId);
    if ($sourceRecordId === '') {
        // Keep an id for dedupe/upsert even when upstream payload omits a stable identifier.
        $sourceRecordId = 'hash:' . substr(hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), 0, 48);
    }
    $sourceUrl = trim((string) $sourceUrl);

    $check = $pdo->prepare('SELECT id FROM contracts_raw WHERE source_id = :source_id AND source_record_id = :source_record_id LIMIT 1');
    $check->execute(['source_id' => $sourceId, 'source_record_id' => $sourceRecordId]);
    $existing = $check->fetchColumn();

    if ($existing) {
        $update = $pdo->prepare('UPDATE contracts_raw SET payload_json = :payload_json, source_url = COALESCE(NULLIF(:source_url, ""), source_url), fetched_at = NOW(), processed = 0 WHERE id = :id');
        $update->execute([
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'source_url' => $sourceUrl,
            'id' => (int) $existing,
        ]);
        return (int) $existing;
    }

    $insert = $pdo->prepare('INSERT INTO contracts_raw (source_id, source_record_id, source_url, payload_json) VALUES (:source_id, :source_record_id, :source_url, :payload_json)');
    $insert->execute([
        'source_id' => $sourceId,
        'source_record_id' => $sourceRecordId,
        'source_url' => $sourceUrl === '' ? null : $sourceUrl,
        'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
    return (int) $pdo->lastInsertId();
}
