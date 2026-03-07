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

function ingest_parse_bool_env(string $name): ?bool
{
    $value = getenv($name);
    if ($value === false) {
        return null;
    }

    $value = strtolower(trim((string) $value));
    if ($value === '') {
        return null;
    }

    if (in_array($value, ['1', 'true', 'yes', 'on'], true)) {
        return true;
    }
    if (in_array($value, ['0', 'false', 'no', 'off'], true)) {
        return false;
    }

    return null;
}

function ingest_redact_url_for_log(string $url): string
{
    if ($url === '') {
        return $url;
    }

    $parts = parse_url($url);
    if (!is_array($parts)) {
        return $url;
    }

    $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
    $host = $parts['host'] ?? '';
    $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
    $path = $parts['path'] ?? '';
    $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';
    $safeQuery = '';

    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
        foreach ($query as $key => $value) {
            $lower = strtolower((string) $key);
            if (str_contains($lower, 'key') || str_contains($lower, 'token') || str_contains($lower, 'secret')) {
                $query[$key] = '[redacted]';
            }
        }
        $safeQuery = '?' . http_build_query($query);
    }

    return $scheme . $host . $port . $path . $safeQuery . $fragment;
}

function ingest_is_loopback_proxy(string $proxyUrl): bool
{
    $proxyUrl = trim($proxyUrl);
    if ($proxyUrl === '') {
        return false;
    }

    if (!str_contains($proxyUrl, '://')) {
        $proxyUrl = 'http://' . $proxyUrl;
    }

    $parts = parse_url($proxyUrl);
    if (!is_array($parts)) {
        return false;
    }

    $host = strtolower((string) ($parts['host'] ?? ''));
    return in_array($host, ['127.0.0.1', 'localhost', '::1'], true);
}

function ingest_proxy_host_port_label(string $proxyUrl): string
{
    $proxyUrl = trim($proxyUrl);
    if ($proxyUrl === '') {
        return 'none';
    }

    if (!str_contains($proxyUrl, '://')) {
        $proxyUrl = 'http://' . $proxyUrl;
    }

    $parts = parse_url($proxyUrl);
    if (!is_array($parts)) {
        return 'invalid';
    }

    $host = (string) ($parts['host'] ?? 'invalid');
    $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
    return $host . $port;
}

function ingest_sam_proxy_options(array $config): array
{
    $ingest = $config['ingest'] ?? [];
    $samHttp = is_array($ingest['sam_http'] ?? null) ? $ingest['sam_http'] : [];

    // Default: SAM requests do not inherit process-level proxy env vars.
    $disableByConfig = $samHttp['disable_proxy'] ?? $ingest['sam_disable_proxy'] ?? $ingest['disable_http_proxy'] ?? null;
    $disableByEnv = ingest_parse_bool_env('SAM_DISABLE_PROXY');
    if ($disableByEnv === null) {
        $disableByEnv = ingest_parse_bool_env('DISABLE_HTTP_PROXY');
    }
    $disableProxy = $disableByEnv ?? (is_bool($disableByConfig) ? $disableByConfig : true);

    $explicitProxy = trim((string) ($samHttp['proxy_url'] ?? $ingest['sam_http_proxy'] ?? $ingest['http_proxy'] ?? ''));
    $proxyLabel = 'none';
    $decision = 'disabled';
    $curlOptions = [
        CURLOPT_PROXY => '',
        CURLOPT_NOPROXY => '*',
    ];

    if (!$disableProxy && $explicitProxy !== '') {
        $proxyLabel = ingest_proxy_host_port_label($explicitProxy);

        // Guardrail for common poisoned localhost blackhole proxy values.
        if (ingest_is_loopback_proxy($explicitProxy) && preg_match('/:\s*9(?:\D|$)/', $explicitProxy)) {
            $decision = 'ignored_invalid_loopback_proxy';
            return [
                'curl_options' => $curlOptions,
                'using_proxy' => false,
                'proxy' => $proxyLabel,
                'decision' => $decision,
            ];
        }

        $curlOptions = [
            CURLOPT_PROXY => $explicitProxy,
        ];
        $decision = 'explicit';

        return [
            'curl_options' => $curlOptions,
            'using_proxy' => true,
            'proxy' => $proxyLabel,
            'decision' => $decision,
        ];
    }

    return [
        'curl_options' => $curlOptions,
        'using_proxy' => false,
        'proxy' => $proxyLabel,
        'decision' => $decision,
    ];
}

function http_get_json_diagnostic(string $url, array $headers = [], int $timeout = 30, array $extraCurlOptions = []): array
{
    $ch = curl_init($url);
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => $headers,
    ];
    $options = $options + ingest_ssl_curl_options() + $extraCurlOptions;
    curl_setopt_array($ch, $options);

    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $effectiveUrl = (string) (curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $url);
    $errno = curl_errno($ch);
    $err = curl_error($ch);
    curl_close($ch);

    $body = $resp === false ? '' : (string) $resp;
    $decoded = null;
    $decodeError = null;
    if ($body !== '') {
        $decoded = json_decode($body, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            $decodeError = json_last_error_msg();
        }
    }

    return [
        'ok' => ($resp !== false && $errno === 0),
        'status' => $code,
        'data' => $decoded,
        'body' => $body,
        'url' => $effectiveUrl,
        'curl_errno' => $errno,
        'curl_error' => (string) $err,
        'json_error' => $decodeError,
        'json_error_code' => json_last_error(),
    ];
}

function http_get_json(string $url, array $headers = [], int $timeout = 30, array $extraCurlOptions = []): array
{
    $diag = http_get_json_diagnostic($url, $headers, $timeout, $extraCurlOptions);

    if (!$diag['ok']) {
        throw new RuntimeException('HTTP request failed [' . $diag['curl_errno'] . ']: ' . $diag['curl_error']);
    }

    if ($diag['json_error'] !== null) {
        throw new RuntimeException('Invalid JSON response from ' . ingest_redact_url_for_log($diag['url']) . ' HTTP ' . $diag['status']);
    }

    return [
        'status' => $diag['status'],
        'data' => $diag['data'],
        'body' => $diag['body'],
        'url' => $diag['url'],
        'curl_errno' => $diag['curl_errno'],
        'curl_error' => $diag['curl_error'],
    ];
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
    ingest_append_log_line(__DIR__ . '/../logs/ingest.log', $line);
}

function ingest_append_log_line(string $path, string $line): void
{
    $ok = @file_put_contents($path, $line, FILE_APPEND);
    if ($ok === false) {
        error_log(rtrim($line));
    }
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
