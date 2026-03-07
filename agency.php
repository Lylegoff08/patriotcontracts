<?php
require_once __DIR__ . '/includes/functions.php';
$pdo = db();
$id = request_int('id', 0);

$agencyStmt = $pdo->prepare('SELECT * FROM agencies WHERE id = :id LIMIT 1');
$agencyStmt->execute(['id' => $id]);
$agency = $agencyStmt->fetch();

$list = [];
if ($agency) {
    $stmt = $pdo->prepare('SELECT cc.id, COALESCE(NULLIF(lo.display_title, ""), NULLIF(go.display_title, ""), cc.title) AS title, cc.posted_date, cc.award_amount
        FROM contracts_clean cc
        LEFT JOIN listing_overrides lo ON lo.contract_id = cc.id
        LEFT JOIN grant_overrides go ON go.contract_id = cc.id
        WHERE cc.agency_id = :agency_id
          AND cc.is_duplicate = 0
          AND COALESCE(lo.is_hidden, go.is_hidden, 0) = 0
        ORDER BY cc.posted_date DESC
        LIMIT 100');
    $stmt->execute(['agency_id' => $id]);
    $list = $stmt->fetchAll();
}

include __DIR__ . '/templates/header.php';
?>
<h1>Agency: <?php echo e((string) ($agency['name'] ?? 'Unknown')); ?></h1>
<section class="card">
<?php foreach ($list as $row): ?>
  <p><a href="contract.php?id=<?php echo (int) $row['id']; ?>"><?php echo e($row['title']); ?></a> <span class="muted">$<?php echo number_format((float) $row['award_amount'], 2); ?> | <?php echo e((string) $row['posted_date']); ?></span></p>
<?php endforeach; ?>
</section>
<?php include __DIR__ . '/templates/footer.php'; ?>
