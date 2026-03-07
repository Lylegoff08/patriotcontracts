<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/api_auth.php';
$pdo = db();
api_authenticate($pdo, '/api/contracts.php');
$page = request_int('page', 1);
[$page, $perPage, $offset] = paginate($page, 25);

$filters = build_contract_search_filters();
[$where, $params] = contract_search_query_parts($filters);

$sql = 'SELECT cc.id, cc.title,
        COALESCE(NULLIF(cc.description_clean, ""), NULLIF(cc.description_raw, ""), cc.description) AS description,
        cc.description_raw, cc.description_clean, cc.summary_plain,
        cc.contract_number, cc.award_amount, cc.value_min, cc.value_max, cc.posted_date, cc.award_date,
        cc.response_deadline, cc.status, cc.notice_type, cc.set_aside_code, cc.set_aside_label, cc.place_of_performance, cc.place_state,
        cc.is_biddable_now, cc.is_upcoming_signal, cc.is_awarded, cc.deadline_soon, cc.source_name, cc.source_url,
        a.name AS agency_name, v.name AS vendor_name, cat.slug AS category
        FROM contracts_clean cc
        LEFT JOIN agencies a ON a.id = cc.agency_id
        LEFT JOIN vendors v ON v.id = cc.vendor_id
        LEFT JOIN contract_categories cat ON cat.id = cc.category_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY cc.posted_date DESC, cc.id DESC
        LIMIT :limit OFFSET :offset';

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue(':' . $k, $v);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

foreach ($rows as &$row) {
    $effective = contract_effective_description($row);
    $row['description'] = $effective !== '' ? $effective : null;
}
unset($row);

json_response([
    'page' => $page,
    'per_page' => $perPage,
    'data' => $rows,
]);
