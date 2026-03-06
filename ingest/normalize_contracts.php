<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/normalize.php';
require_once __DIR__ . '/../includes/categorize.php';
require_once __DIR__ . '/../includes/dedupe.php';
require_once __DIR__ . '/../includes/ingest_common.php';

function normalize_contracts(PDO $pdo): array
{
    $startedAt = date('Y-m-d H:i:s');
    $fetched = 0;
    $processed = 0;

    $rows = $pdo->query('SELECT * FROM contracts_raw WHERE processed = 0 ORDER BY id ASC LIMIT 1000')->fetchAll();
    $fetched = count($rows);

    $insert = $pdo->prepare('INSERT INTO contracts_clean (
        source_id, raw_id, source_record_id, source_type, contract_number, title, description,
        source_name, agency_id, vendor_id, naics_code, psc_code, notice_type, set_aside_code, set_aside_label,
        award_amount, value_min, value_max, posted_date, award_date,
        response_deadline, start_date, end_date, status, place_of_performance,
        contact_name, contact_email, contact_phone, contracting_office, contact_address,
        place_state, source_url, category_id, is_biddable_now, is_upcoming_signal, is_awarded, deadline_soon,
        has_contact_info, has_value_estimate, is_duplicate, duplicate_of, dedupe_reason
    ) VALUES (
        :source_id, :raw_id, :source_record_id, :source_type, :contract_number, :title, :description,
        :source_name, :agency_id, :vendor_id, :naics_code, :psc_code, :notice_type, :set_aside_code, :set_aside_label,
        :award_amount, :value_min, :value_max, :posted_date, :award_date,
        :response_deadline, :start_date, :end_date, :status, :place_of_performance,
        :contact_name, :contact_email, :contact_phone, :contracting_office, :contact_address,
        :place_state, :source_url, :category_id, :is_biddable_now, :is_upcoming_signal, :is_awarded, :deadline_soon,
        :has_contact_info, :has_value_estimate, :is_duplicate, :duplicate_of, :dedupe_reason
    )
    ON DUPLICATE KEY UPDATE
        title = VALUES(title),
        description = VALUES(description),
        source_name = VALUES(source_name),
        notice_type = VALUES(notice_type),
        set_aside_code = VALUES(set_aside_code),
        set_aside_label = VALUES(set_aside_label),
        value_min = VALUES(value_min),
        value_max = VALUES(value_max),
        status = VALUES(status),
        place_state = VALUES(place_state),
        category_id = VALUES(category_id),
        is_biddable_now = VALUES(is_biddable_now),
        is_upcoming_signal = VALUES(is_upcoming_signal),
        is_awarded = VALUES(is_awarded),
        deadline_soon = VALUES(deadline_soon),
        has_contact_info = VALUES(has_contact_info),
        has_value_estimate = VALUES(has_value_estimate),
        is_duplicate = VALUES(is_duplicate),
        duplicate_of = VALUES(duplicate_of),
        dedupe_reason = VALUES(dedupe_reason),
        updated_at = NOW()');

    $mark = $pdo->prepare('UPDATE contracts_raw SET processed = 1 WHERE id = :id');

    foreach ($rows as $raw) {
        $payload = json_decode($raw['payload_json'], true);
        if (!is_array($payload)) {
            continue;
        }

        $sourceSlug = (int) $raw['source_id'];
        $sourceType = 'unknown';

        $title = (string) ($payload['title'] ?? $payload['solicitationTitle'] ?? $payload['opportunityTitle'] ?? $payload['Award ID'] ?? $payload['awardId'] ?? 'Untitled');
        $description = (string) ($payload['description'] ?? $payload['fullParentPathName'] ?? $payload['synopsis'] ?? $payload['Recipient Name'] ?? '');
        $contractNumber = (string) ($payload['solicitationNumber'] ?? $payload['opportunityNumber'] ?? $payload['piid'] ?? $payload['Award ID'] ?? $payload['awardId'] ?? '');
        $agencyName = (string) ($payload['department'] ?? $payload['fullParentPathName'] ?? $payload['Awarding Agency'] ?? $payload['agencyName'] ?? $payload['agency'] ?? 'Unknown Agency');
        $vendorName = (string) ($payload['organizationName'] ?? $payload['awardeeName'] ?? $payload['Recipient Name'] ?? $payload['applicantType'] ?? 'Unknown Vendor');

        if (isset($payload['noticeId'])) {
            $sourceType = 'sam_opportunity';
        } elseif (isset($payload['opportunityNumber']) || (($payload['source_family'] ?? '') === 'grants_assistance')) {
            $sourceType = 'grants';
        } elseif (isset($payload['awardId']) || isset($payload['piid'])) {
            $sourceType = 'sam_award';
        } elseif (isset($payload['Award ID']) || isset($payload['generated_internal_id'])) {
            $sourceType = 'usaspending';
        }

        $noticeType = detect_notice_type($payload);
        $setAside = detect_set_aside($payload);
        $values = parse_value_range($payload);
        $sourceName = source_display_name($pdo, (int) $raw['source_id']);

        $contactName = $payload['pointOfContact'][0]['fullName']
            ?? $payload['contacts'][0]['name']
            ?? $payload['contactName']
            ?? $payload['primaryContactName']
            ?? null;
        $contactEmail = $payload['pointOfContact'][0]['email']
            ?? $payload['contacts'][0]['email']
            ?? $payload['contactEmail']
            ?? $payload['primaryContactEmail']
            ?? null;
        $contactPhone = $payload['pointOfContact'][0]['phone']
            ?? $payload['contacts'][0]['phone']
            ?? $payload['contactPhone']
            ?? $payload['primaryContactPhone']
            ?? null;
        $office = $payload['office']
            ?? $payload['officeAddress']
            ?? $payload['departmentIndAgency']
            ?? $payload['agency']
            ?? $payload['agencyName']
            ?? null;
        $placeText = $payload['placeOfPerformance']
            ?? $payload['placeOfPerformanceStateName']
            ?? $payload['placeOfPerformanceCode']
            ?? $payload['Place of Performance']
            ?? $payload['city'] ?? null;
        $placeState = place_state_from_text((string) $placeText);
        $normalizedStatus = normalize_status($payload['type'] ?? $payload['status'] ?? $payload['noticeType'] ?? $noticeType);

        $agencyId = get_or_create_agency($pdo, $agencyName, $payload['agencyCode'] ?? null);
        $vendorId = get_or_create_vendor($pdo, $vendorName, $payload['ueiSAM'] ?? null, $payload['duns'] ?? null);

        $candidate = [
            'source_record_id' => $raw['source_record_id'],
            'contract_number' => $contractNumber,
            'title' => $title,
            'agency_id' => $agencyId,
            'vendor_id' => $vendorId,
            'award_date' => normalize_date($payload['awardDate'] ?? $payload['Start Date'] ?? null),
            'posted_date' => normalize_date($payload['postedDate'] ?? null),
        ];
        $dup = dedupe_contract($pdo, $candidate);

        $categorized = categorize_contract([
            'agency_name' => $agencyName,
            'title' => $title,
            'description' => $description,
            'naics_code' => $payload['naicsCode'] ?? $payload['naics'] ?? null,
            'psc_code' => $payload['pscCode'] ?? null,
        ], $pdo);

        $categoryId = category_slug_to_id($pdo, $categorized['slug']);

        $actionability = derive_actionability([
            'status' => $normalizedStatus,
            'notice_type' => $noticeType,
            'source_type' => $sourceType,
            'title' => $title,
            'description' => $description,
            'response_deadline' => normalize_date($payload['responseDeadLine'] ?? $payload['responseDate'] ?? $payload['closeDate'] ?? null),
            'contact_name' => $contactName,
            'contact_email' => $contactEmail,
            'contact_phone' => $contactPhone,
            'contracting_office' => $office,
            'award_amount' => $values['award_amount'],
            'value_min' => $values['value_min'],
            'value_max' => $values['value_max'],
        ]);

        $insert->execute([
            'source_id' => (int) $raw['source_id'],
            'raw_id' => (int) $raw['id'],
            'source_record_id' => $raw['source_record_id'],
            'source_type' => $sourceType,
            'source_name' => $sourceName,
            'contract_number' => $contractNumber,
            'title' => $title,
            'description' => $description,
            'agency_id' => $agencyId,
            'vendor_id' => $vendorId,
            'naics_code' => $payload['naicsCode'] ?? $payload['naics'] ?? null,
            'psc_code' => $payload['pscCode'] ?? null,
            'notice_type' => $noticeType,
            'set_aside_code' => $setAside['set_aside_code'],
            'set_aside_label' => $setAside['set_aside_label'],
            'award_amount' => $values['award_amount'],
            'value_min' => $values['value_min'],
            'value_max' => $values['value_max'],
            'posted_date' => normalize_date($payload['postedDate'] ?? $payload['openDate'] ?? null),
            'award_date' => normalize_date($payload['awardDate'] ?? $payload['Start Date'] ?? $payload['awardDateSigned'] ?? null),
            'response_deadline' => normalize_date($payload['responseDeadLine'] ?? $payload['responseDate'] ?? $payload['closeDate'] ?? null),
            'start_date' => normalize_date($payload['activeDate'] ?? $payload['Start Date'] ?? $payload['openDate'] ?? null),
            'end_date' => normalize_date($payload['archiveDate'] ?? $payload['End Date'] ?? $payload['closeDate'] ?? null),
            'status' => $normalizedStatus,
            'place_of_performance' => $placeText,
            'contact_name' => $contactName,
            'contact_email' => $contactEmail,
            'contact_phone' => $contactPhone,
            'contracting_office' => $office,
            'contact_address' => $payload['officeAddress'] ?? null,
            'place_state' => $placeState,
            'source_url' => $raw['source_url'],
            'category_id' => $categoryId,
            'is_biddable_now' => $actionability['is_biddable_now'],
            'is_upcoming_signal' => $actionability['is_upcoming_signal'],
            'is_awarded' => $actionability['is_awarded'],
            'deadline_soon' => $actionability['deadline_soon'],
            'has_contact_info' => $actionability['has_contact_info'],
            'has_value_estimate' => $actionability['has_value_estimate'],
            'is_duplicate' => $dup['is_duplicate'],
            'duplicate_of' => $dup['duplicate_of'],
            'dedupe_reason' => $dup['reason'],
        ]);

        $contractId = (int) $pdo->lastInsertId();
        if ($contractId > 0 && $categoryId) {
            $map = $pdo->prepare('INSERT IGNORE INTO contract_category_map (contract_id, category_id, confidence, rule_name) VALUES (:contract_id, :category_id, :confidence, :rule_name)');
            $map->execute([
                'contract_id' => $contractId,
                'category_id' => $categoryId,
                'confidence' => 1.00,
                'rule_name' => $categorized['rule'],
            ]);
        }

        $mark->execute(['id' => (int) $raw['id']]);
        $processed++;
    }

    log_ingest($pdo, null, 'normalize_contracts.php', 'success', 'Normalized records', $fetched, $processed, $startedAt);
    return ['fetched' => $fetched, 'processed' => $processed, 'status' => 'success'];
}

if (php_sapi_name() === 'cli' || basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $result = normalize_contracts(db());
    echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
}
