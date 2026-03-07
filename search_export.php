<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/membership.php';

$pdo = db();
$user = require_active_member();
if (!user_has_feature($pdo, (int) $user['id'], 'csv_export')) {
    http_response_code(403);
    exit('Pro feature: CSV export requires MEMBER_PRO or API_MEMBER.');
}
$filters = build_contract_search_filters();
[$where, $params] = contract_search_query_parts($filters);

$limit = min(2000, max(1, request_int('limit', 1000)));

$sql = 'SELECT cc.id,
    COALESCE(NULLIF(lo.display_title, ""), NULLIF(go.display_title, ""), cc.title) AS title,
    cc.contract_number, a.name AS agency_name, v.name AS vendor_name, COALESCE(ocat.name, cat.name) AS category_name,
    cc.status, cc.notice_type, cc.posted_date, cc.award_date, cc.response_deadline, cc.place_of_performance, cc.place_state,
    cc.award_amount, cc.value_min, cc.value_max, cc.naics_code, cc.psc_code, cc.set_aside_label, cc.contact_name, cc.contact_email, cc.contact_phone,
    cc.source_name, cc.source_url
    FROM contracts_clean cc
    LEFT JOIN listing_overrides lo ON lo.contract_id = cc.id
    LEFT JOIN grant_overrides go ON go.contract_id = cc.id
    LEFT JOIN agencies a ON a.id = cc.agency_id
    LEFT JOIN vendors v ON v.id = cc.vendor_id
    LEFT JOIN contract_categories cat ON cat.id = cc.category_id
    LEFT JOIN contract_categories ocat ON ocat.id = COALESCE(lo.category_override, go.category_override)
    WHERE ' . implode(' AND ', $where) . '
    ORDER BY cc.posted_date DESC, cc.id DESC
    LIMIT :limit';

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue(':' . $k, $v);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=patriotcontracts_export_' . date('Ymd_His') . '.csv');

$out = fopen('php://output', 'w');
fputcsv($out, [
    'id', 'title', 'contract_number', 'agency', 'vendor', 'category', 'status', 'notice_type',
    'posted_date', 'award_date', 'response_deadline', 'place_of_performance', 'place_state',
    'award_amount', 'value_min', 'value_max', 'naics', 'psc', 'set_aside', 'contact_name', 'contact_email',
    'contact_phone', 'source_name', 'source_url',
]);
foreach ($rows as $row) {
    fputcsv($out, [
        $row['id'],
        $row['title'],
        $row['contract_number'],
        $row['agency_name'],
        $row['vendor_name'],
        $row['category_name'],
        $row['status'],
        $row['notice_type'],
        $row['posted_date'],
        $row['award_date'],
        $row['response_deadline'],
        $row['place_of_performance'],
        $row['place_state'],
        $row['award_amount'],
        $row['value_min'],
        $row['value_max'],
        $row['naics_code'],
        $row['psc_code'],
        $row['set_aside_label'],
        $row['contact_name'],
        $row['contact_email'],
        $row['contact_phone'],
        $row['source_name'],
        $row['source_url'],
    ]);
}
fclose($out);
exit;
