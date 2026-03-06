<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/api_auth.php';
$pdo = db();
api_authenticate($pdo, '/api/vendors.php');
$page = request_int('page', 1);
[$page, $perPage, $offset] = paginate($page, 50);

$stmt = $pdo->prepare('SELECT id, name, normalized_name, uei, duns FROM vendors ORDER BY name ASC LIMIT :limit OFFSET :offset');
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

json_response([
    'page' => $page,
    'per_page' => $perPage,
    'data' => $stmt->fetchAll(),
]);
