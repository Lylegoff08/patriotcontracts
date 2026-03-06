<?php
require_once __DIR__ . '/includes/functions.php';
$pdo = db();
$id = request_int('id', 0);

$vendorStmt = $pdo->prepare('SELECT * FROM vendors WHERE id = :id LIMIT 1');
$vendorStmt->execute(['id' => $id]);
$vendor = $vendorStmt->fetch();

$list = [];
if ($vendor) {
    $stmt = $pdo->prepare('SELECT id, title, posted_date, award_amount FROM contracts_clean WHERE vendor_id = :vendor_id AND is_duplicate = 0 ORDER BY posted_date DESC LIMIT 100');
    $stmt->execute(['vendor_id' => $id]);
    $list = $stmt->fetchAll();
}

include __DIR__ . '/templates/header.php';
?>
<h1>Vendor: <?php echo e((string) ($vendor['name'] ?? 'Unknown')); ?></h1>
<section class="card">
<?php foreach ($list as $row): ?>
  <p><a href="contract.php?id=<?php echo (int) $row['id']; ?>"><?php echo e($row['title']); ?></a> <span class="muted">$<?php echo number_format((float) $row['award_amount'], 2); ?> | <?php echo e((string) $row['posted_date']); ?></span></p>
<?php endforeach; ?>
</section>
<?php include __DIR__ . '/templates/footer.php'; ?>
