<?php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/normalize.php';
require_once __DIR__ . '/../includes/description_normalizer.php';
require_once __DIR__ . '/../includes/ingest_common.php';

function backfill_sam_description_clean(PDO $pdo, bool $apply = false, int $limit = 2000): array
{
    $startedAt = date('Y-m-d H:i:s');
    $sourceId = source_id_by_slug($pdo, 'sam_opportunities');

    $sql = 'SELECT cc.id, cc.description, cc.description_raw, cc.description_clean, cc.summary_plain, cr.payload_json
            FROM contracts_clean cc
            JOIN contracts_raw cr ON cr.id = cc.raw_id
            WHERE cc.source_type = "sam_opportunity"
            ORDER BY cc.id DESC
            LIMIT :limit';
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $examined = 0;
    $updated = 0;
    $skipped = 0;

    $update = $pdo->prepare('UPDATE contracts_clean
        SET description = :description,
            description_raw = :description_raw,
            description_clean = :description_clean,
            summary_plain = :summary_plain,
            updated_at = NOW()
        WHERE id = :id');

    foreach ($rows as $row) {
        $examined++;
        $payload = json_decode((string) $row['payload_json'], true);
        $payloadDescription = is_array($payload)
            ? clean_nullable(payload_pick($payload, [['description'], ['synopsis'], ['summary']]))
            : null;

        $rawSource = clean_nullable($payloadDescription ?? $row['description_raw'] ?? $row['description'] ?? null);
        if ($rawSource === null) {
            $skipped++;
            continue;
        }

        $normalized = normalize_listing_description('sam_opportunity', $rawSource, ['source_record_id' => $row['id']]);
        $nextDescription = (string) ($normalized['description_display'] ?? '');
        $nextRaw = (string) ($normalized['description_raw'] ?? '');
        $nextClean = (string) ($normalized['description_clean'] ?? '');
        $nextSummary = (string) ($normalized['summary_plain'] ?? '');

        $currDescription = trim((string) ($row['description'] ?? ''));
        $currRaw = trim((string) ($row['description_raw'] ?? ''));
        $currClean = trim((string) ($row['description_clean'] ?? ''));
        $currSummary = trim((string) ($row['summary_plain'] ?? ''));

        $needsUpdate = $currDescription !== $nextDescription
            || $currRaw !== $nextRaw
            || $currClean !== $nextClean
            || $currSummary !== $nextSummary;
        if (!$needsUpdate) {
            continue;
        }

        if ($apply) {
            $update->execute([
                'id' => (int) $row['id'],
                'description' => $nextDescription !== '' ? $nextDescription : null,
                'description_raw' => $nextRaw !== '' ? $nextRaw : null,
                'description_clean' => $nextClean !== '' ? $nextClean : null,
                'summary_plain' => $nextSummary !== '' ? $nextSummary : null,
            ]);
            $updated += $update->rowCount();
        } else {
            $updated++;
        }
    }

    $message = sprintf(
        'SAM description clean backfill apply=%s examined=%d updated=%d skipped=%d',
        $apply ? 'yes' : 'no',
        $examined,
        $updated,
        $skipped
    );
    log_ingest($pdo, $sourceId ?: null, 'backfill_sam_description_clean.php', 'success', $message, $examined, $updated, $startedAt);

    return [
        'ok' => true,
        'apply' => $apply,
        'examined' => $examined,
        'updated' => $updated,
        'skipped' => $skipped,
    ];
}

if (php_sapi_name() === 'cli') {
    $apply = in_array('--apply', $argv, true);
    $limit = 2000;
    foreach ($argv as $arg) {
        if (strpos($arg, '--limit=') === 0) {
            $limit = max(1, (int) substr($arg, 8));
        }
    }

    $result = backfill_sam_description_clean(db(), $apply, $limit);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}
