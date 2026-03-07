<?php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ingest_common.php';
require_once __DIR__ . '/../includes/description_normalizer.php';
require_once __DIR__ . '/ingest_sam_opportunities.php';

function sam_backfill_notice_descriptions(PDO $pdo, array $config, bool $apply = false, int $limit = 500): array
{
    $sourceId = source_id_by_slug($pdo, 'sam_opportunities');
    if (!$sourceId) {
        return ['ok' => false, 'message' => 'sam_opportunities source not found'];
    }

    $apiKey = trim((string) ($config['sources']['sam_opportunities']['api_key'] ?? ''));
    if ($apiKey === '') {
        return ['ok' => false, 'message' => 'sam_opportunities api_key missing'];
    }

    $sql = 'SELECT cr.id AS raw_id, cr.source_record_id, cr.payload_json,
        cc.id AS clean_id, cc.description, cc.description_raw, cc.description_clean, cc.summary_plain
        FROM contracts_raw cr
        JOIN contracts_clean cc ON cc.raw_id = cr.id
        WHERE cr.source_id = :source_id
          AND cc.source_type = "sam_opportunity"
        ORDER BY cr.id DESC
        LIMIT :limit';

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':source_id', (int) $sourceId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $examined = 0;
    $candidates = 0;
    $updatedRaw = 0;
    $updatedClean = 0;
    $fetchFailed = 0;

    foreach ($rows as $row) {
        $examined++;
        $payload = json_decode((string) $row['payload_json'], true);
        if (!is_array($payload)) {
            continue;
        }

        $payloadDesc = trim((string) ($payload['description'] ?? $payload['synopsis'] ?? ''));
        $cleanDesc = trim((string) ($row['description_clean'] ?? $row['description'] ?? ''));
        $badPayload = sam_noticedesc_is_url_value($payloadDesc);
        $badClean = sam_noticedesc_is_url_value($cleanDesc);
        if (!$badPayload && !$badClean) {
            continue;
        }

        $candidates++;
        $noticeId = trim((string) ($payload['noticeId'] ?? $row['source_record_id'] ?? ''));
        if ($noticeId === '' && preg_match('~/noticedesc/([^?&#/]+)~i', $payloadDesc ?: $cleanDesc, $m)) {
            $noticeId = trim((string) $m[1]);
        }
        if ($noticeId === '') {
            $fetchFailed++;
            continue;
        }

        $candidateUrl = $payloadDesc !== '' ? $payloadDesc : $cleanDesc;
        [$descText, $err] = sam_fetch_notice_description($config, $apiKey, $noticeId, $candidateUrl);
        if ($descText === null) {
            $fetchFailed++;
            sam_notice_desc_log('warn', 'backfill noticeId=' . $noticeId . ' failed: ' . (string) $err);
            continue;
        }

        if (!$apply) {
            continue;
        }

        if ($badPayload) {
            $payload['description'] = $descText;
            $upRaw = $pdo->prepare('UPDATE contracts_raw SET payload_json = :payload_json, processed = 0, fetched_at = NOW() WHERE id = :id');
            $upRaw->execute([
                'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'id' => (int) $row['raw_id'],
            ]);
            $updatedRaw += $upRaw->rowCount();
        }

        $normalized = normalize_listing_description('sam_opportunity', $descText, ['source_record_id' => $noticeId]);
        if ($badClean) {
            $upClean = $pdo->prepare('UPDATE contracts_clean
                SET description = :description,
                    description_raw = :description_raw,
                    description_clean = :description_clean,
                    summary_plain = :summary_plain,
                    updated_at = NOW()
                WHERE id = :id');
            $upClean->execute([
                'description' => $normalized['description_display'],
                'description_raw' => $normalized['description_raw'],
                'description_clean' => $normalized['description_clean'],
                'summary_plain' => $normalized['summary_plain'],
                'id' => (int) $row['clean_id'],
            ]);
            $updatedClean += $upClean->rowCount();
        }
    }

    return [
        'ok' => true,
        'apply' => $apply,
        'examined' => $examined,
        'candidates' => $candidates,
        'updated_raw' => $updatedRaw,
        'updated_clean' => $updatedClean,
        'fetch_failed' => $fetchFailed,
    ];
}

if (php_sapi_name() === 'cli') {
    $apply = in_array('--apply', $argv, true);
    $limit = 500;
    foreach ($argv as $arg) {
        if (strpos($arg, '--limit=') === 0) {
            $limit = max(1, (int) substr($arg, 8));
        }
    }

    $result = sam_backfill_notice_descriptions(db(), app_config(), $apply, $limit);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}
