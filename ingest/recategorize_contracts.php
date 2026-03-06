<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/categorize.php';
require_once __DIR__ . '/../includes/ingest_common.php';

function recategorize_contracts(PDO $pdo, int $limit = 5000): array
{
    $startedAt = date('Y-m-d H:i:s');

    $sql = 'SELECT cc.id, cc.title, cc.description, cc.naics_code, cc.psc_code, cc.notice_type, cc.status, cc.source_type,
                   cc.response_deadline, cc.contact_name, cc.contact_email, cc.contact_phone, cc.contracting_office,
                   cc.award_amount, cc.value_min, cc.value_max, a.name AS agency_name
            FROM contracts_clean cc
            LEFT JOIN agencies a ON a.id = cc.agency_id
            ORDER BY cc.id ASC
            LIMIT :limit';
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $updated = 0;
    $mapInsert = $pdo->prepare('INSERT INTO contract_category_map (contract_id, category_id, confidence, rule_name)
                                VALUES (:contract_id, :category_id, :confidence, :rule_name)
                                ON DUPLICATE KEY UPDATE confidence = VALUES(confidence), rule_name = VALUES(rule_name)');
    $updateContract = $pdo->prepare('UPDATE contracts_clean
        SET category_id = :category_id,
            is_biddable_now = :is_biddable_now,
            is_upcoming_signal = :is_upcoming_signal,
            is_awarded = :is_awarded,
            deadline_soon = :deadline_soon,
            has_contact_info = :has_contact_info,
            has_value_estimate = :has_value_estimate,
            updated_at = NOW()
        WHERE id = :id');

    foreach ($rows as $row) {
        $result = categorize_contract([
            'agency_name' => $row['agency_name'] ?? '',
            'title' => $row['title'] ?? '',
            'description' => $row['description'] ?? '',
            'naics_code' => $row['naics_code'] ?? '',
            'psc_code' => $row['psc_code'] ?? '',
        ], $pdo);

        $categoryId = category_slug_to_id($pdo, $result['slug']);
        if (!$categoryId) {
            continue;
        }

        $actionability = derive_actionability($row);

        $updateContract->execute([
            'category_id' => $categoryId,
            'is_biddable_now' => $actionability['is_biddable_now'],
            'is_upcoming_signal' => $actionability['is_upcoming_signal'],
            'is_awarded' => $actionability['is_awarded'],
            'deadline_soon' => $actionability['deadline_soon'],
            'has_contact_info' => $actionability['has_contact_info'],
            'has_value_estimate' => $actionability['has_value_estimate'],
            'id' => (int) $row['id'],
        ]);

        $mapInsert->execute([
            'contract_id' => (int) $row['id'],
            'category_id' => $categoryId,
            'confidence' => 1.00,
            'rule_name' => $result['rule'],
        ]);

        $updated++;
    }

    log_ingest($pdo, null, 'recategorize_contracts.php', 'success', 'Recategorized contracts', count($rows), $updated, $startedAt);

    return [
        'status' => 'success',
        'fetched' => count($rows),
        'updated' => $updated,
    ];
}

if (php_sapi_name() === 'cli' || basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $result = recategorize_contracts(db());
    echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
}
