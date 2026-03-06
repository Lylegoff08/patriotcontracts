<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ingest_common.php';

/*
 * This script is a placeholder hook for SAM Data Bank report ingestion.
 * In many environments, Data Bank award access is delivered through account-level
 * report exports rather than a simple public endpoint. Keep this as an explicit
 * operational entry point for later CSV/report import wiring.
 */
function ingest_sam_databank_awards(PDO $pdo): array
{
    $startedAt = date('Y-m-d H:i:s');
    $sourceId = source_id_by_slug($pdo, 'sam_awards');
    $msg = 'No direct Data Bank feed configured yet. Use SAM report exports/API access path when available.';
    log_ingest($pdo, $sourceId, 'ingest_sam_databank_awards.php', 'skipped', $msg, 0, 0, $startedAt);
    return ['status' => 'skipped', 'fetched' => 0, 'processed' => 0, 'message' => $msg];
}

if (php_sapi_name() === 'cli' || basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $result = ingest_sam_databank_awards(db());
    echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
}
