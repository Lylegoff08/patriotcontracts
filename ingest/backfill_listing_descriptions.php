<?php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/normalize.php';
require_once __DIR__ . '/../includes/description_normalizer.php';
require_once __DIR__ . '/../includes/ingest_common.php';
require_once __DIR__ . '/ingest_sam_opportunities.php';

function backfill_listing_descriptions(PDO $pdo, array $config, bool $apply = false, int $batchSize = 1000): array
{
    $startedAt = date('Y-m-d H:i:s');
    $apiKey = trim((string) ($config['sources']['sam_opportunities']['api_key'] ?? ''));

    $processed = 0;
    $updated = 0;
    $changed = 0;
    $urlRows = 0;
    $urlStripped = 0;
    $needsNoticeDesc = 0;
    $noticeDescFetched = 0;
    $noUsable = 0;
    $lastId = 0;

    $select = $pdo->prepare('SELECT cc.id, cc.raw_id, cc.source_type, cc.source_record_id, cc.set_aside_label,
            cc.description, cc.description_raw, cc.description_clean, cc.summary_plain, cr.payload_json
        FROM contracts_clean cc
        JOIN contracts_raw cr ON cr.id = cc.raw_id
        WHERE cc.id > :last_id
        ORDER BY cc.id ASC
        LIMIT :limit');

    $updateStmt = $pdo->prepare('UPDATE contracts_clean
        SET description = :description,
            description_raw = :description_raw,
            description_clean = :description_clean,
            summary_plain = :summary_plain,
            updated_at = NOW()
        WHERE id = :id');

    while (true) {
        $select->bindValue(':last_id', $lastId, PDO::PARAM_INT);
        $select->bindValue(':limit', $batchSize, PDO::PARAM_INT);
        $select->execute();
        $rows = $select->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) {
            break;
        }

        foreach ($rows as $row) {
            $lastId = (int) $row['id'];
            $processed++;

            $payload = json_decode((string) $row['payload_json'], true);
            $sourceType = strtolower(trim((string) ($row['source_type'] ?? 'unknown')));
            $payloadDescription = is_array($payload)
                ? clean_nullable(payload_pick($payload, [['description'], ['synopsis'], ['summary'], ['awardDescription'], ['fullParentPathName']]))
                : null;

            $candidateRaw = clean_nullable($payloadDescription ?? $row['description_raw'] ?? $row['description'] ?? null);
            $countedUrlRow = false;

            if ($sourceType === 'sam_opportunity' && $candidateRaw !== null && preg_match('~https?://[^\\s]*noticedesc~i', $candidateRaw)) {
                $urlRows++;
                $countedUrlRow = true;
                $needsNoticeDesc++;
                $noticeId = sam_extract_notice_id(is_array($payload) ? $payload : []);
                if ($noticeId === '') {
                    $noticeId = trim((string) ($row['source_record_id'] ?? ''));
                }
                if ($noticeId !== '' && $apiKey !== '') {
                    [$fetchedText, $fetchErr] = sam_fetch_notice_description($config, $apiKey, $noticeId, $candidateRaw);
                    if ($fetchedText !== null) {
                        $candidateRaw = $fetchedText;
                        $needsNoticeDesc--;
                        $noticeDescFetched++;
                    } elseif ($fetchErr !== null) {
                        sam_notice_desc_log('warn', 'listing description backfill noticeId=' . $noticeId . ' fetch failed: ' . $fetchErr);
                    }
                }
            }

            $normalized = normalize_listing_description($sourceType, $candidateRaw, [
                'set_aside_label' => $row['set_aside_label'] ?? null,
                'source_record_id' => $row['source_record_id'] ?? null,
            ]);

            $meta = (array) ($normalized['meta'] ?? []);
            if ((int) ($meta['had_url'] ?? 0) === 1 && !$countedUrlRow) {
                $urlRows++;
            }
            if ((int) ($meta['url_stripped'] ?? 0) === 1) {
                $urlStripped++;
            }
            if ((int) ($meta['had_usable_description'] ?? 0) === 0) {
                $noUsable++;
            }

            $nextDescription = clean_nullable($normalized['description_display'] ?? null);
            $nextRaw = clean_nullable($normalized['description_raw'] ?? null);
            $nextClean = clean_nullable($normalized['description_clean'] ?? null);
            $nextSummary = clean_nullable($normalized['summary_plain'] ?? null);

            $currDescription = clean_nullable($row['description'] ?? null);
            $currRaw = clean_nullable($row['description_raw'] ?? null);
            $currClean = clean_nullable($row['description_clean'] ?? null);
            $currSummary = clean_nullable($row['summary_plain'] ?? null);

            $isChanged = $currDescription !== $nextDescription
                || $currRaw !== $nextRaw
                || $currClean !== $nextClean
                || $currSummary !== $nextSummary;
            if (!$isChanged) {
                continue;
            }

            $changed++;
            if ($apply) {
                $updateStmt->execute([
                    'id' => (int) $row['id'],
                    'description' => $nextDescription,
                    'description_raw' => $nextRaw,
                    'description_clean' => $nextClean,
                    'summary_plain' => $nextSummary,
                ]);
                $updated += $updateStmt->rowCount();
            } else {
                $updated++;
            }
        }
    }

    $message = sprintf(
        'Description backfill apply=%s processed=%d updated=%d changed=%d url_rows=%d url_stripped=%d noticedesc_fetched=%d needs_noticedesc=%d no_usable=%d',
        $apply ? 'yes' : 'no',
        $processed,
        $updated,
        $changed,
        $urlRows,
        $urlStripped,
        $noticeDescFetched,
        $needsNoticeDesc,
        $noUsable
    );
    log_ingest($pdo, null, 'backfill_listing_descriptions.php', 'success', $message, $processed, $updated, $startedAt);

    return [
        'ok' => true,
        'apply' => $apply,
        'processed' => $processed,
        'updated' => $updated,
        'changed' => $changed,
        'url_rows' => $urlRows,
        'url_stripped' => $urlStripped,
        'noticedesc_fetched' => $noticeDescFetched,
        'needs_noticedesc' => $needsNoticeDesc,
        'no_usable_description' => $noUsable,
    ];
}

if (php_sapi_name() === 'cli') {
    $apply = in_array('--apply', $argv, true);
    $batchSize = 1000;
    foreach ($argv as $arg) {
        if (strpos($arg, '--batch=') === 0) {
            $batchSize = max(100, (int) substr($arg, 8));
        }
    }

    $result = backfill_listing_descriptions(db(), app_config(), $apply, $batchSize);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}
