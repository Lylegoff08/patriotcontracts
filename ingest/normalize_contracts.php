<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/normalize.php';
require_once __DIR__ . '/../includes/categorize.php';
require_once __DIR__ . '/../includes/dedupe.php';
require_once __DIR__ . '/../includes/ingest_common.php';

function source_type_from_payload(array $payload): string
{
    if (isset($payload['noticeId'])) {
        return 'sam_opportunity';
    }
    if (isset($payload['opportunityNumber']) || (($payload['source_family'] ?? '') === 'grants_assistance')) {
        return 'grants';
    }
    if (isset($payload['awardId']) || isset($payload['piid'])) {
        return 'sam_award';
    }
    if (isset($payload['Award ID']) || isset($payload['generated_internal_id'])) {
        return 'usaspending';
    }
    return 'unknown';
}

function compose_place_of_performance(array $payload): ?string
{
    $place = payload_pick($payload, [
        ['placeOfPerformance'],
        ['placeOfPerformanceAddress'],
        ['placeOfPerformanceStateName'],
        ['placeOfPerformanceCode'],
        ['Place of Performance'],
        ['location'],
    ]);
    if ($place !== null) {
        return $place;
    }

    $city = payload_pick($payload, [
        ['placeOfPerformanceAddress', 'city'],
        ['city'],
    ]);
    $state = payload_pick($payload, [
        ['placeOfPerformanceAddress', 'state'],
        ['placeOfPerformanceAddress', 'stateCode'],
        ['placeOfPerformanceAddress', 'stateName'],
        ['state'],
        ['stateCode'],
    ]);
    $country = payload_pick($payload, [
        ['placeOfPerformanceAddress', 'country'],
        ['placeOfPerformanceAddress', 'countryCode'],
        ['country'],
        ['countryCode'],
    ]);

    return pick_first_nonempty([implode(', ', array_values(array_filter([$city, $state, $country])))]);
}

function extract_contact_data(array $payload): array
{
    $name = clean_contact_field(payload_pick($payload, [
        ['pointOfContact', 0, 'fullName'],
        ['pointOfContact', 0, 'name'],
        ['pointOfContact', 0, 'title'],
        ['contacts', 0, 'name'],
        ['contacts', 0, 'fullName'],
        ['primaryContactName'],
        ['contactName'],
    ]));

    $email = clean_contact_field(payload_pick($payload, [
        ['pointOfContact', 0, 'email'],
        ['contacts', 0, 'email'],
        ['primaryContactEmail'],
        ['contactEmail'],
        ['officeAddress', 'email'],
    ]));

    $phone = clean_contact_field(payload_pick($payload, [
        ['pointOfContact', 0, 'phone'],
        ['contacts', 0, 'phone'],
        ['primaryContactPhone'],
        ['contactPhone'],
        ['officeAddress', 'phone'],
    ]));

    $office = clean_nullable(payload_pick($payload, [
        ['office'],
        ['officeAddress', 'officeName'],
        ['officeAddress', 'name'],
        ['departmentIndAgency'],
        ['agency'],
        ['agencyName'],
        ['organization', 'office'],
    ]));

    $address = clean_nullable(payload_pick($payload, [
        ['officeAddress'],
        ['pointOfContact', 0, 'address'],
        ['contacts', 0, 'address'],
        ['address'],
    ]));

    return [
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'office' => $office,
        'address' => $address,
    ];
}

function record_missing_field(array &$stats, string $field, bool $isMissing): void
{
    if (!$isMissing) {
        return;
    }
    if (!isset($stats[$field])) {
        $stats[$field] = 0;
    }
    $stats[$field]++;
}

function normalize_contracts(PDO $pdo): array
{
    $startedAt = date('Y-m-d H:i:s');
    $fetched = 0;
    $processed = 0;
    $warnings = 0;
    $skipped = 0;
    $missingStats = [];

    $config = app_config();
    $debugMissing = (bool) (($config['ingest']['debug_missing_fields'] ?? false) || ((string) getenv('PC_DEBUG_MISSING_FIELDS') === '1'));

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
        source_record_id = COALESCE(NULLIF(VALUES(source_record_id), ""), source_record_id),
        source_type = COALESCE(NULLIF(VALUES(source_type), ""), source_type),
        contract_number = COALESCE(NULLIF(VALUES(contract_number), ""), contract_number),
        title = COALESCE(NULLIF(VALUES(title), ""), title),
        description = COALESCE(NULLIF(VALUES(description), ""), description),
        source_name = COALESCE(NULLIF(VALUES(source_name), ""), source_name),
        agency_id = COALESCE(VALUES(agency_id), agency_id),
        vendor_id = COALESCE(VALUES(vendor_id), vendor_id),
        naics_code = COALESCE(NULLIF(VALUES(naics_code), ""), naics_code),
        psc_code = COALESCE(NULLIF(VALUES(psc_code), ""), psc_code),
        notice_type = COALESCE(NULLIF(VALUES(notice_type), ""), notice_type),
        set_aside_code = COALESCE(NULLIF(VALUES(set_aside_code), ""), set_aside_code),
        set_aside_label = COALESCE(NULLIF(VALUES(set_aside_label), ""), set_aside_label),
        award_amount = COALESCE(VALUES(award_amount), award_amount),
        value_min = COALESCE(VALUES(value_min), value_min),
        value_max = COALESCE(VALUES(value_max), value_max),
        posted_date = COALESCE(VALUES(posted_date), posted_date),
        award_date = COALESCE(VALUES(award_date), award_date),
        response_deadline = COALESCE(VALUES(response_deadline), response_deadline),
        start_date = COALESCE(VALUES(start_date), start_date),
        end_date = COALESCE(VALUES(end_date), end_date),
        status = COALESCE(NULLIF(VALUES(status), ""), status),
        place_of_performance = COALESCE(NULLIF(VALUES(place_of_performance), ""), place_of_performance),
        contact_name = COALESCE(NULLIF(VALUES(contact_name), ""), contact_name),
        contact_email = COALESCE(NULLIF(VALUES(contact_email), ""), contact_email),
        contact_phone = COALESCE(NULLIF(VALUES(contact_phone), ""), contact_phone),
        contracting_office = COALESCE(NULLIF(VALUES(contracting_office), ""), contracting_office),
        contact_address = COALESCE(NULLIF(VALUES(contact_address), ""), contact_address),
        place_state = COALESCE(NULLIF(VALUES(place_state), ""), place_state),
        source_url = COALESCE(NULLIF(VALUES(source_url), ""), source_url),
        category_id = COALESCE(VALUES(category_id), category_id),
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
        try {
            $payload = json_decode($raw['payload_json'], true);
            if (!is_array($payload)) {
                $warnings++;
                $skipped++;
                $mark->execute(['id' => (int) $raw['id']]);
                continue;
            }

            $sourceType = source_type_from_payload($payload);
            $noticeType = clean_nullable(pick_first_nonempty([
                detect_notice_type($payload),
                payload_pick($payload, [['opportunityType'], ['noticeTypeCode']]),
            ]));

            $title = pick_first_nonempty([
                payload_pick($payload, [['title'], ['solicitationTitle'], ['opportunityTitle'], ['Award ID'], ['awardId'], ['subject']]),
                payload_pick($payload, [['description'], ['synopsis']]),
            ]);

            $description = clean_nullable(payload_pick($payload, [
                ['description'],
                ['synopsis'],
                ['fullParentPathName'],
                ['awardDescription'],
                ['summary'],
            ]));

            $contractNumber = clean_nullable(payload_pick($payload, [
                ['solicitationNumber'],
                ['opportunityNumber'],
                ['piid'],
                ['Award ID'],
                ['awardId'],
                ['referenceNumber'],
                ['solicitationId'],
            ]));

            if ($title === null) {
                $title = pick_first_nonempty([
                    $contractNumber,
                    clean_nullable($raw['source_record_id'] ?? null),
                    'Untitled Contract #' . (int) $raw['id'],
                ]);
                $warnings++;
            }

            $agencyName = clean_nullable(payload_pick($payload, [
                ['agencyName'],
                ['agency'],
                ['department'],
                ['Awarding Agency'],
                ['Awarding Sub Agency'],
                ['departmentIndAgency'],
                ['office'],
                ['organization', 'name'],
                ['fullParentPathName'],
            ]));

            $vendorName = clean_nullable(payload_pick($payload, [
                ['organizationName'],
                ['awardeeName'],
                ['Recipient Name'],
                ['legalBusinessName'],
                ['entityName'],
                ['applicantType'],
            ]));

            $agencyId = $agencyName !== null ? get_or_create_agency($pdo, $agencyName, clean_nullable($payload['agencyCode'] ?? null)) : null;
            $vendorId = $vendorName !== null ? get_or_create_vendor($pdo, $vendorName, clean_nullable($payload['ueiSAM'] ?? null), clean_nullable($payload['duns'] ?? null)) : null;

            $setAside = detect_set_aside($payload);
            $setAsideCode = clean_nullable($setAside['set_aside_code'] ?? null);
            $setAsideLabel = clean_nullable($setAside['set_aside_label'] ?? null);

            $values = parse_value_range($payload);
            $postedDate = clean_date(payload_pick($payload, [['postedDate'], ['publishDate'], ['openDate'], ['createdDate']]));
            $awardDate = clean_date(payload_pick($payload, [['awardDate'], ['awardDateSigned'], ['Start Date'], ['signedDate']]));
            $responseDeadline = clean_date(payload_pick($payload, [['responseDeadLine'], ['responseDeadline'], ['responseDate'], ['closeDate'], ['dueDate'], ['offersDueDate']]));
            $startDate = clean_date(payload_pick($payload, [['activeDate'], ['Start Date'], ['openDate'], ['periodOfPerformanceStartDate']]));
            $endDate = clean_date(payload_pick($payload, [['archiveDate'], ['End Date'], ['closeDate'], ['periodOfPerformanceEndDate']]));

            $statusRaw = pick_first_nonempty([
                payload_pick($payload, [['status'], ['type'], ['opportunityStatus'], ['awardStatus']]),
                $noticeType,
            ]);
            $normalizedStatus = normalize_status($statusRaw);

            $contact = extract_contact_data($payload);
            $placeText = clean_nullable(compose_place_of_performance($payload));
            $placeState = clean_nullable(pick_first_nonempty([
                payload_pick($payload, [['placeOfPerformanceAddress', 'state'], ['placeOfPerformanceAddress', 'stateCode'], ['state'], ['stateCode']]),
                place_state_from_text($placeText),
            ]));

            $sourceName = source_display_name($pdo, (int) $raw['source_id']);
            $sourceUrl = clean_nullable(pick_first_nonempty([
                $raw['source_url'] ?? null,
                payload_pick($payload, [['uiLink'], ['url'], ['link'], ['opportunityUrl'], ['awardUrl'], ['resourceUrl']]),
            ]));

            $naicsCode = clean_nullable(payload_pick($payload, [
                ['naicsCode'],
                ['naics'],
                ['naicsCodeValue'],
                ['naicsCodes', 0],
            ]));
            $pscCode = clean_nullable(payload_pick($payload, [
                ['pscCode'],
                ['psc'],
                ['pscCodeValue'],
            ]));

            $isAwardSource = in_array($sourceType, ['usaspending', 'sam_award'], true);
            $isAwardRow = $isAwardSource && $awardDate !== null;
            if ($isAwardRow && ($normalizedStatus === '' || $normalizedStatus === 'unknown')) {
                $normalizedStatus = 'awarded';
            }

            $candidate = [
                'source_record_id' => $raw['source_record_id'],
                'contract_number' => $contractNumber,
                'title' => $title,
                'agency_id' => $agencyId,
                'vendor_id' => $vendorId,
                'award_date' => $awardDate,
                'posted_date' => $postedDate,
            ];
            $dup = dedupe_contract($pdo, $candidate);

            $categorized = categorize_contract([
                'agency_name' => (string) ($agencyName ?? ''),
                'title' => (string) $title,
                'description' => (string) ($description ?? ''),
                'naics_code' => $naicsCode,
                'psc_code' => $pscCode,
            ], $pdo);

            $categoryId = category_slug_to_id($pdo, $categorized['slug']);

            $actionability = derive_actionability([
                'status' => $normalizedStatus,
                'notice_type' => $noticeType,
                'source_type' => $sourceType,
                'title' => $title,
                'description' => $description,
                'response_deadline' => $responseDeadline,
                'contact_name' => $contact['name'],
                'contact_email' => $contact['email'],
                'contact_phone' => $contact['phone'],
                'contracting_office' => $contact['office'],
                'award_amount' => $values['award_amount'],
                'value_min' => $values['value_min'],
                'value_max' => $values['value_max'],
            ]);
            if ($isAwardRow) {
                $actionability['is_awarded'] = 1;
            }

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
                'naics_code' => $naicsCode,
                'psc_code' => $pscCode,
                'notice_type' => $noticeType,
                'set_aside_code' => $setAsideCode,
                'set_aside_label' => $setAsideLabel,
                'award_amount' => $values['award_amount'],
                'value_min' => $values['value_min'],
                'value_max' => $values['value_max'],
                'posted_date' => $postedDate,
                'award_date' => $awardDate,
                'response_deadline' => $responseDeadline,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => $normalizedStatus,
                'place_of_performance' => $placeText,
                'contact_name' => $contact['name'],
                'contact_email' => $contact['email'],
                'contact_phone' => $contact['phone'],
                'contracting_office' => $contact['office'],
                'contact_address' => $contact['address'],
                'place_state' => $placeState,
                'source_url' => $sourceUrl,
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

            record_missing_field($missingStats, 'agency_name', $agencyName === null);
            record_missing_field($missingStats, 'vendor_name', $vendorName === null);
            record_missing_field($missingStats, 'notice_type', $noticeType === null);
            record_missing_field($missingStats, 'contract_number', $contractNumber === null);
            record_missing_field($missingStats, 'posted_date', $postedDate === null);
            record_missing_field($missingStats, 'response_deadline', $responseDeadline === null);
            record_missing_field($missingStats, 'set_aside', $setAsideLabel === null && $setAsideCode === null);
            record_missing_field($missingStats, 'naics_code', $naicsCode === null);
            record_missing_field($missingStats, 'psc_code', $pscCode === null);
            record_missing_field($missingStats, 'place_of_performance', $placeText === null);
            record_missing_field($missingStats, 'contact_name', $contact['name'] === null);
            record_missing_field($missingStats, 'contact_email', $contact['email'] === null);
            record_missing_field($missingStats, 'contact_phone', $contact['phone'] === null);
            record_missing_field($missingStats, 'description', $description === null);
            record_missing_field($missingStats, 'source_url', $sourceUrl === null);

            if ($debugMissing) {
                $line = json_encode([
                    'ts' => date('c'),
                    'raw_id' => (int) $raw['id'],
                    'source_id' => (int) $raw['source_id'],
                    'source_record_id' => $raw['source_record_id'],
                    'missing' => [
                        'agency_name' => $agencyName === null,
                        'notice_type' => $noticeType === null,
                        'contract_number' => $contractNumber === null,
                        'response_deadline' => $responseDeadline === null,
                        'naics_code' => $naicsCode === null,
                        'place_of_performance' => $placeText === null,
                        'contact_email' => $contact['email'] === null,
                    ],
                ], JSON_UNESCAPED_SLASHES);
                if ($line !== false) {
                    file_put_contents(__DIR__ . '/../logs/normalize_missing_fields.log', $line . "\n", FILE_APPEND);
                }
            }

            $mark->execute(['id' => (int) $raw['id']]);
            $processed++;
        } catch (Throwable $e) {
            $warnings++;
            $line = sprintf(
                "[%s] normalize_contracts.php WARN raw_id=%d source_id=%d %s\n",
                date('Y-m-d H:i:s'),
                (int) ($raw['id'] ?? 0),
                (int) ($raw['source_id'] ?? 0),
                $e->getMessage()
            );
            file_put_contents(__DIR__ . '/../logs/ingest.log', $line, FILE_APPEND);
        }
    }

    if (!empty($missingStats)) {
        $summaryLine = json_encode([
            'ts' => date('c'),
            'script' => 'normalize_contracts.php',
            'fetched' => $fetched,
            'processed' => $processed,
            'warnings' => $warnings,
            'missing_field_counts' => $missingStats,
        ], JSON_UNESCAPED_SLASHES);
        if ($summaryLine !== false) {
            file_put_contents(__DIR__ . '/../logs/normalize_missing_fields.log', $summaryLine . "\n", FILE_APPEND);
        }
    }

    $message = sprintf('Normalized records warnings=%d skipped=%d', $warnings, $skipped);
    log_ingest($pdo, null, 'normalize_contracts.php', 'success', $message, $fetched, $processed, $startedAt);
    return ['fetched' => $fetched, 'processed' => $processed, 'warnings' => $warnings, 'skipped' => $skipped, 'status' => 'success', 'missing_field_counts' => $missingStats];
}

if (php_sapi_name() === 'cli' || basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $result = normalize_contracts(db());
    echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
}
