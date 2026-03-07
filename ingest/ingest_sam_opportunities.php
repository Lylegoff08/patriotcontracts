<?php

require_once __DIR__ . '/../includes/ingest_common.php';
require_once __DIR__ . '/../includes/normalize.php';

function sam_notice_desc_log(string $level, string $message): void
{
    $line = sprintf("[%s] ingest_sam_opportunities.php %s %s\n", date('Y-m-d H:i:s'), strtoupper($level), $message);
    ingest_append_log_line(__DIR__ . '/../logs/ingest.log', $line);
}

function sam_transport_log_url(string $url): string
{
    return ingest_redact_url_for_log($url);
}

function sam_transport_failure_snippet(string $body, int $limit = 300): string
{
    $text = trim(preg_replace('/\s+/', ' ', $body) ?? '');
    if ($text === '') {
        return '';
    }
    if (mb_strlen($text) > $limit) {
        return mb_substr($text, 0, $limit) . '...';
    }
    return $text;
}

function sam_noticedesc_is_url_value(?string $text): bool
{
    $text = trim((string) $text);
    if ($text === '') {
        return false;
    }
    return (bool) preg_match('~^https?://[^\\s]*noticedesc~i', $text);
}

function sam_extract_notice_id(array $row): string
{
    $noticeId = trim((string) ($row['noticeId'] ?? ''));
    if ($noticeId !== '') {
        return $noticeId;
    }

    $desc = trim((string) ($row['description'] ?? $row['synopsis'] ?? ''));
    if (preg_match('~/noticedesc/([^?&#/]+)~i', $desc, $m)) {
        return trim((string) $m[1]);
    }

    return '';
}

function sam_noticedesc_base_urls(array $config): array
{
    $configured = trim((string) ($config['sources']['sam_opportunities']['noticedesc_base_url'] ?? ''));
    $searchBase = rtrim((string) ($config['sources']['sam_opportunities']['base_url'] ?? ''), '/');
    $derived = preg_replace('~/search/?$~i', '/noticedesc', $searchBase) ?: '';
    $derivedV1 = preg_replace('~/v2/noticedesc/?$~i', '/v1/noticedesc', $derived) ?: '';

    $candidates = array_filter([
        $configured,
        $derivedV1,
        $derived,
        'https://api.sam.gov/prod/opportunities/v1/noticedesc',
        'https://api.sam.gov/prod/opportunities/v2/noticedesc',
    ]);

    return array_values(array_unique(array_map(static fn($v) => rtrim((string) $v, '/'), $candidates)));
}

function sam_noticedesc_url_with_api_key(string $url, string $apiKey): string
{
    $parts = parse_url($url);
    if (!is_array($parts)) {
        return $url;
    }

    $query = [];
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }
    $query['api_key'] = $apiKey;

    $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
    $host = $parts['host'] ?? '';
    $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
    $path = $parts['path'] ?? '';
    $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

    return $scheme . $host . $port . $path . '?' . http_build_query($query) . $fragment;
}

function sam_noticedesc_request_url(string $baseUrl, string $noticeId, string $apiKey): string
{
    $baseUrl = rtrim($baseUrl, '/');
    if (str_contains(strtolower($baseUrl), '/v1/noticedesc')) {
        return $baseUrl . '?noticeid=' . rawurlencode($noticeId) . '&api_key=' . urlencode($apiKey);
    }
    return $baseUrl . '/' . rawurlencode($noticeId) . '?api_key=' . urlencode($apiKey);
}

function sam_extract_notice_description_text($payload): ?string
{
    if (!is_array($payload)) {
        return null;
    }

    $knownPaths = [
        ['description'],
        ['noticeDescription'],
        ['solicitationDescription'],
        ['descriptionText'],
        ['body'],
        ['data', 'description'],
        ['data', 'noticeDescription'],
        ['data', 'descriptionText'],
        ['data', 'body'],
    ];
    foreach ($knownPaths as $path) {
        $value = payload_get($payload, $path);
        $text = clean_nullable($value);
        if ($text !== null && !sam_noticedesc_is_url_value($text)) {
            return $text;
        }
    }

    $stack = [$payload];
    $visited = 0;
    while ($stack && $visited < 800) {
        $visited++;
        $node = array_pop($stack);
        if (!is_array($node)) {
            continue;
        }

        foreach ($node as $key => $value) {
            if (is_array($value)) {
                $stack[] = $value;
                continue;
            }

            if (!is_string($value)) {
                continue;
            }

            $k = strtolower((string) $key);
            if (str_contains($k, 'desc') || in_array($k, ['body', 'content', 'text', 'details', 'synopsis'], true)) {
                $text = clean_nullable($value);
                if ($text !== null && !sam_noticedesc_is_url_value($text) && mb_strlen($text) >= 20) {
                    return $text;
                }
            }
        }
    }

    return null;
}

function sam_fetch_notice_description(array $config, string $apiKey, string $noticeId, ?string $candidateNoticeUrl = null): array
{
    if ($noticeId === '' || $apiKey === '') {
        return [null, 'missing notice id or api key'];
    }

    $proxyMeta = ingest_sam_proxy_options($config);
    $extraCurlOptions = (array) ($proxyMeta['curl_options'] ?? []);
    $urls = [];
    $candidateNoticeUrl = trim((string) $candidateNoticeUrl);
    if (sam_noticedesc_is_url_value($candidateNoticeUrl)) {
        $urls[] = sam_noticedesc_url_with_api_key($candidateNoticeUrl, $apiKey);
    }
    foreach (sam_noticedesc_base_urls($config) as $baseUrl) {
        $urls[] = sam_noticedesc_request_url($baseUrl, $noticeId, $apiKey);
    }

    $errors = [];
    foreach (array_values(array_unique($urls)) as $url) {
        $diag = http_get_json_diagnostic($url, [], 30, $extraCurlOptions);
        $safeUrl = sam_transport_log_url($url);
        $proxyNote = 'proxy=' . (($proxyMeta['using_proxy'] ?? false) ? 'yes' : 'no') . ' (' . ($proxyMeta['proxy'] ?? 'none') . '; ' . ($proxyMeta['decision'] ?? 'n/a') . ')';

        if (!$diag['ok']) {
            $errors[] = 'transport errno=' . (int) ($diag['curl_errno'] ?? 0) . ' ' . (string) ($diag['curl_error'] ?? '') . ' ' . $safeUrl;
            sam_notice_desc_log(
                'warn',
                'noticedesc transport failure'
                . ' noticeId=' . $noticeId
                . ' url=' . $safeUrl
                . ' ' . $proxyNote
                . ' http_status=' . (int) ($diag['status'] ?? 0)
                . ' curl_errno=' . (int) ($diag['curl_errno'] ?? 0)
                . ' curl_error=' . trim((string) ($diag['curl_error'] ?? 'n/a'))
            );
            continue;
        }

        if ((int) ($diag['status'] ?? 0) >= 400) {
            $snippet = sam_transport_failure_snippet((string) ($diag['body'] ?? ''));
            $errors[] = 'HTTP ' . (int) $diag['status'] . ' ' . $safeUrl;
            sam_notice_desc_log(
                'warn',
                'noticedesc HTTP failure'
                . ' noticeId=' . $noticeId
                . ' url=' . $safeUrl
                . ' ' . $proxyNote
                . ' http_status=' . (int) $diag['status']
                . ' curl_errno=' . (int) ($diag['curl_errno'] ?? 0)
                . ' curl_error=' . trim((string) ($diag['curl_error'] ?? ''))
                . ($snippet !== '' ? ' body=' . $snippet : '')
            );
            continue;
        }

        if (($diag['json_error'] ?? null) !== null) {
            $snippet = sam_transport_failure_snippet((string) ($diag['body'] ?? ''));
            $errors[] = 'json decode failed ' . $safeUrl;
            sam_notice_desc_log(
                'warn',
                'noticedesc JSON failure'
                . ' noticeId=' . $noticeId
                . ' url=' . $safeUrl
                . ' ' . $proxyNote
                . ' http_status=' . (int) ($diag['status'] ?? 0)
                . ' curl_errno=' . (int) ($diag['curl_errno'] ?? 0)
                . ' curl_error=' . trim((string) ($diag['curl_error'] ?? ''))
                . ' json_error=' . (string) ($diag['json_error'] ?? '')
                . ($snippet !== '' ? ' body=' . $snippet : '')
            );
            continue;
        }

        $text = sam_extract_notice_description_text($diag['data'] ?? []);
        if ($text !== null) {
            return [$text, null];
        }

        $errors[] = 'empty description ' . $safeUrl;
        sam_notice_desc_log(
            'warn',
            'noticedesc empty description'
            . ' noticeId=' . $noticeId
            . ' url=' . $safeUrl
            . ' ' . $proxyNote
            . ' http_status=' . (int) ($diag['status'] ?? 0)
            . ' curl_errno=' . (int) ($diag['curl_errno'] ?? 0)
            . ' curl_error=' . trim((string) ($diag['curl_error'] ?? ''))
        );
    }

    return [null, implode(' | ', $errors)];
}

function sam_place_value(array $row, array $paths): ?string
{
    foreach ($paths as $path) {
        $value = payload_get($row, $path);
        $text = clean_nullable($value);
        if ($text !== null && strtolower($text) !== 'array') {
            return $text;
        }
    }
    return null;
}

function sam_place_of_performance_text(array $row): ?string
{
    $city = sam_place_value($row, [
        ['placeOfPerformance', 'city', 'name'],
        ['placeOfPerformance', 'city'],
        ['placeOfPerformanceAddress', 'city', 'name'],
        ['placeOfPerformanceAddress', 'city'],
        ['placeOfPerformanceCity'],
        ['popCity'],
    ]);
    $state = sam_place_value($row, [
        ['placeOfPerformance', 'state', 'code'],
        ['placeOfPerformance', 'state', 'name'],
        ['placeOfPerformance', 'stateCode'],
        ['placeOfPerformance', 'state'],
        ['placeOfPerformanceAddress', 'stateCode'],
        ['placeOfPerformanceAddress', 'stateName'],
        ['placeOfPerformanceAddress', 'state'],
        ['placeOfPerformanceStateName'],
        ['placeOfPerformanceState'],
        ['popState'],
    ]);
    $country = sam_place_value($row, [
        ['placeOfPerformance', 'country', 'code'],
        ['placeOfPerformance', 'country', 'name'],
        ['placeOfPerformance', 'countryCode'],
        ['placeOfPerformanceAddress', 'country'],
        ['placeOfPerformanceAddress', 'countryCode'],
        ['popCountry'],
    ]);

    $pieces = array_values(array_filter([$city, $state, $country], static fn($v) => !is_placeholder_value($v)));
    if ($pieces) {
        return implode(', ', $pieces);
    }

    return sam_place_value($row, [
        ['placeOfPerformanceAddress'],
        ['placeOfPerformance'],
        ['placeOfPerformanceCode'],
    ]);
}

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
    $samProxy = ingest_sam_proxy_options($config);
    $response = http_get_json($url, [], 30, (array) ($samProxy['curl_options'] ?? []));

    $rows = $response['data']['opportunitiesData'] ?? [];
    $descFetched = 0;
    $descFailed = 0;
    foreach ($rows as $row) {
        $sourceRecordId = (string) ($row['noticeId'] ?? $row['solicitationNumber'] ?? '');
        $noticeId = sam_extract_notice_id($row);
        $badDesc = trim((string) ($row['description'] ?? $row['synopsis'] ?? ''));

        if ($noticeId !== '') {
            [$descText, $descError] = sam_fetch_notice_description($config, $apiKey, $noticeId, $badDesc);
            if ($descText !== null) {
                $row['description'] = $descText;
                $descFetched++;
            } elseif ($descError !== null) {
                $descFailed++;
                sam_notice_desc_log('warn', "noticeId={$noticeId} fetch failed: {$descError}");
            }
        }

        if (sam_noticedesc_is_url_value($badDesc) || sam_noticedesc_is_url_value((string) ($row['description'] ?? ''))) {
            $row['description'] = null;
        }

        $popText = sam_place_of_performance_text($row);
        if ($popText !== null) {
            $row['placeOfPerformanceAddress'] = $popText;
            if (!isset($row['placeOfPerformanceStateName'])) {
                $stateGuess = place_state_from_text($popText);
                if ($stateGuess !== null) {
                    $row['placeOfPerformanceStateName'] = $stateGuess;
                }
            }
        }

        $sourceUrl = $row['uiLink'] ?? 'https://sam.gov/content/opportunities';
        upsert_raw_record($pdo, $sourceId, $sourceRecordId, $sourceUrl, $row);
        $fetched++;
    }

    $processed = $fetched;
    $message = 'HTTP ' . $response['status'] . ' received, rows=' . $fetched . ', noticedesc_ok=' . $descFetched . ', noticedesc_fail=' . $descFailed;
    log_ingest($pdo, $sourceId, 'ingest_sam_opportunities.php', 'success', $message, $fetched, $processed, $startedAt);

    return ['fetched' => $fetched, 'processed' => $processed, 'status' => 'success', 'message' => $message];
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $result = ingest_sam_opportunities(db(), app_config());
    echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
}
