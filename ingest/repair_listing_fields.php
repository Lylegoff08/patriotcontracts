<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/normalize.php';
require_once __DIR__ . '/../includes/description_normalizer.php';
require_once __DIR__ . '/normalize_contracts.php';

function db_field_is_empty($value): bool
{
    if ($value === null) {
        return true;
    }
    $text = trim((string) $value);
    return is_placeholder_value($text);
}

function repair_listing_fields(PDO $pdo, bool $dryRun = true, int $limit = 2000): array
{
    $sql = "SELECT cc.id, cc.raw_id, cc.source_type, cc.title, cc.contract_number, cc.notice_type, cc.posted_date, cc.response_deadline,
                   cc.set_aside_code, cc.set_aside_label, cc.naics_code, cc.psc_code, cc.place_of_performance, cc.place_state,
                   cc.contact_name, cc.contact_email, cc.contact_phone, cc.contracting_office, cc.contact_address,
                   cc.description, cc.description_raw, cc.description_clean, cc.summary_plain, cc.source_url, cc.agency_id, cc.vendor_id,
                   cr.payload_json, cr.source_url AS raw_source_url, cr.source_record_id
            FROM contracts_clean cc
            JOIN contracts_raw cr ON cr.id = cc.raw_id
            WHERE cc.is_duplicate = 0
            ORDER BY cc.id ASC
            LIMIT :limit";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $updated = 0;
    $examined = count($rows);
    $changes = [];

    foreach ($rows as $row) {
        $payload = json_decode((string) $row['payload_json'], true);
        if (!is_array($payload)) {
            continue;
        }

        $setAside = detect_set_aside($payload);
        $contact = extract_contact_data($payload);
        $placeText = clean_nullable(compose_place_of_performance($payload));
        $placeState = clean_nullable(pick_first_nonempty([
            payload_pick($payload, [['placeOfPerformanceAddress', 'state'], ['placeOfPerformanceAddress', 'stateCode'], ['state'], ['stateCode']]),
            place_state_from_text($placeText),
        ]));

        $title = pick_first_nonempty([
            payload_pick($payload, [['title'], ['solicitationTitle'], ['opportunityTitle'], ['Award ID'], ['awardId'], ['subject']]),
            clean_nullable($row['source_record_id'] ?? null),
        ]);

        $agencyName = clean_nullable(payload_pick($payload, [
            ['agencyName'], ['agency'], ['department'], ['Awarding Agency'], ['Awarding Sub Agency'], ['departmentIndAgency'], ['office'], ['organization', 'name'], ['fullParentPathName'],
        ]));
        $vendorName = clean_nullable(payload_pick($payload, [
            ['organizationName'], ['awardeeName'], ['Recipient Name'], ['legalBusinessName'], ['entityName'], ['applicantType'],
        ]));

        $descriptionRaw = clean_nullable(payload_pick($payload, [['description'], ['synopsis'], ['summary'], ['awardDescription'], ['fullParentPathName']]));
        $sourceType = strtolower(trim((string) ($row['source_type'] ?? '')));
        $normalizedDescription = normalize_listing_description($sourceType, $descriptionRaw, [
            'set_aside_label' => clean_nullable($setAside['set_aside_label'] ?? null),
            'source_record_id' => $row['source_record_id'] ?? null,
        ]);

        $candidate = [
            'title' => $title,
            'contract_number' => clean_nullable(payload_pick($payload, [['solicitationNumber'], ['opportunityNumber'], ['piid'], ['Award ID'], ['awardId'], ['referenceNumber']])),
            'notice_type' => clean_nullable(pick_first_nonempty([detect_notice_type($payload), payload_pick($payload, [['opportunityType'], ['noticeTypeCode']])])),
            'posted_date' => clean_date(payload_pick($payload, [['postedDate'], ['publishDate'], ['openDate'], ['createdDate']])),
            'response_deadline' => clean_date(payload_pick($payload, [['responseDeadLine'], ['responseDeadline'], ['responseDate'], ['closeDate'], ['dueDate'], ['offersDueDate']])),
            'set_aside_code' => clean_nullable($setAside['set_aside_code'] ?? null),
            'set_aside_label' => clean_nullable($setAside['set_aside_label'] ?? null),
            'naics_code' => clean_nullable(payload_pick($payload, [['naicsCode'], ['naics'], ['naicsCodeValue'], ['naicsCodes', 0]])),
            'psc_code' => clean_nullable(payload_pick($payload, [['pscCode'], ['psc'], ['pscCodeValue']])),
            'place_of_performance' => $placeText,
            'place_state' => $placeState,
            'contact_name' => $contact['name'],
            'contact_email' => $contact['email'],
            'contact_phone' => $contact['phone'],
            'contracting_office' => $contact['office'],
            'contact_address' => $contact['address'],
            'description' => $normalizedDescription['description_display'],
            'description_raw' => $normalizedDescription['description_raw'],
            'description_clean' => $normalizedDescription['description_clean'],
            'summary_plain' => $normalizedDescription['summary_plain'],
            'source_url' => clean_nullable(pick_first_nonempty([$row['source_url'] ?? null, $row['raw_source_url'] ?? null, payload_pick($payload, [['uiLink'], ['url'], ['link'], ['opportunityUrl'], ['awardUrl']])])),
        ];

        if ((int) $row['agency_id'] === 0 && $agencyName !== null) {
            $agencyId = get_or_create_agency($pdo, $agencyName, clean_nullable($payload['agencyCode'] ?? null));
            if ($agencyId !== null) {
                $candidate['agency_id'] = $agencyId;
            }
        }
        if ((int) $row['vendor_id'] === 0 && $vendorName !== null) {
            $vendorId = get_or_create_vendor($pdo, $vendorName, clean_nullable($payload['ueiSAM'] ?? null), clean_nullable($payload['duns'] ?? null));
            if ($vendorId !== null) {
                $candidate['vendor_id'] = $vendorId;
            }
        }

        $updates = [];
        $params = ['id' => (int) $row['id']];
        foreach ($candidate as $field => $value) {
            $current = $row[$field] ?? null;
            if (!db_field_is_empty($current)) {
                continue;
            }
            if (db_field_is_empty($value)) {
                continue;
            }
            $updates[] = "{$field} = :{$field}";
            $params[$field] = $value;
        }

        if (!$updates) {
            continue;
        }

        $updated++;
        $changes[] = [
            'id' => (int) $row['id'],
            'fields' => array_values(array_keys(array_diff_key($params, ['id' => 1]))),
        ];

        if ($dryRun) {
            continue;
        }

        $updateSql = 'UPDATE contracts_clean SET ' . implode(', ', $updates) . ', updated_at = NOW() WHERE id = :id';
        $u = $pdo->prepare($updateSql);
        $u->execute($params);
    }

    return [
        'dry_run' => $dryRun,
        'examined' => $examined,
        'rows_with_updates' => $updated,
        'changes' => $changes,
    ];
}

if (php_sapi_name() === 'cli') {
    $dryRun = true;
    foreach ($argv as $arg) {
        if ($arg === '--apply') {
            $dryRun = false;
        }
    }

    $limit = 2000;
    foreach ($argv as $arg) {
        if (strpos($arg, '--limit=') === 0) {
            $limit = max(1, (int) substr($arg, 8));
        }
    }

    $result = repair_listing_fields(db(), $dryRun, $limit);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}
