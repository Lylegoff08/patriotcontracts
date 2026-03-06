<?php
require_once __DIR__ . '/includes/functions.php';
$pdo = db();
$id = request_int('id', 0);

$agencyStmt = $pdo->prepare('SELECT * FROM agencies WHERE id = :id LIMIT 1');
$agencyStmt->execute(['id' => $id]);
$agency = $agencyStmt->fetch();

$list = [];
if ($agency) {
    $stmt = $pdo->prepare('SELECT id, title, posted_date, award_amount FROM contracts_clean WHERE agency_id = :agency_id AND is_duplicate = 0 ORDER BY posted_date DESC LIMIT 100');
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
