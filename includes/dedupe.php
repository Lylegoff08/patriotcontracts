<?php

require_once __DIR__ . '/normalize.php';

function dedupe_contract(PDO $pdo, array $candidate): array
{
    $sourceRecordId = (string) ($candidate['source_record_id'] ?? '');
    $contractNumber = (string) ($candidate['contract_number'] ?? '');
    $title = normalize_whitespace(strtoupper((string) ($candidate['title'] ?? '')));
    $agencyId = (int) ($candidate['agency_id'] ?? 0);
    $vendorId = (int) ($candidate['vendor_id'] ?? 0);
    $date = $candidate['award_date'] ?? $candidate['posted_date'] ?? null;

    if ($sourceRecordId !== '') {
        $stmt = $pdo->prepare('SELECT id FROM contracts_clean WHERE source_record_id = :source_record_id LIMIT 1');
        $stmt->execute(['source_record_id' => $sourceRecordId]);
        $id = $stmt->fetchColumn();
        if ($id) {
            return ['is_duplicate' => 1, 'duplicate_of' => (int) $id, 'reason' => 'source_record_id'];
        }
    }

    if ($contractNumber !== '') {
        $stmt = $pdo->prepare('SELECT id FROM contracts_clean WHERE contract_number = :contract_number AND agency_id = :agency_id LIMIT 1');
        $stmt->execute(['contract_number' => $contractNumber, 'agency_id' => $agencyId ?: null]);
        $id = $stmt->fetchColumn();
        if ($id) {
            return ['is_duplicate' => 1, 'duplicate_of' => (int) $id, 'reason' => 'contract_number'];
        }
    }

    if ($title !== '' && $agencyId > 0 && $vendorId > 0 && $date) {
        $stmt = $pdo->prepare('SELECT id, title FROM contracts_clean WHERE agency_id = :agency_id AND vendor_id = :vendor_id AND (award_date = :award_date OR posted_date = :posted_date) ORDER BY id DESC LIMIT 20');
        $stmt->execute(['agency_id' => $agencyId, 'vendor_id' => $vendorId, 'award_date' => $date, 'posted_date' => $date]);
        while ($row = $stmt->fetch()) {
            $existingTitle = normalize_whitespace(strtoupper((string) $row['title']));
            similar_text($title, $existingTitle, $pct);
            if ($pct >= 90.0) {
                return ['is_duplicate' => 1, 'duplicate_of' => (int) $row['id'], 'reason' => 'title_similarity'];
            }
        }
    }

    return ['is_duplicate' => 0, 'duplicate_of' => null, 'reason' => null];
}


