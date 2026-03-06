<?php
require_once __DIR__ . '/includes/functions.php';
$pdo = db();
$pageTitle = 'PatriotContracts | Open This Week';

$stmt = $pdo->query('SELECT cc.id, cc.title, cc.contract_number, cc.response_deadline, cc.posted_date, cc.status, cc.set_aside_label,
    a.name AS agency_name, v.name AS vendor_name, cat.name AS category_name
    FROM contracts_clean cc
    LEFT JOIN agencies a ON a.id = cc.agency_id
    LEFT JOIN vendors v ON v.id = cc.vendor_id
    LEFT JOIN contract_categories cat ON cat.id = cc.category_id
    WHERE cc.is_duplicate = 0
      AND cc.is_biddable_now = 1
      AND cc.posted_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ORDER BY cc.response_deadline ASC, cc.posted_date DESC
    LIMIT 200');
$rows = $stmt->fetchAll();

include __DIR__ . '/templates/header.php';
?>
<h1>Open This Week</h1>
<p class="muted">Active opportunities posted in the last 7 days, prioritized by upcoming deadlines.</p>
<section class="card">
<?php if (!$rows): ?>
  <p>No open opportunities found for this week.</p>
<?php endif; ?>
<?php foreach ($rows as $row): ?>
  <article>
    <h3><a href="contract.php?id=<?php echo (int) $row['id']; ?>"><?php echo e($row['title']); ?></a></h3>
    <p class="muted"><?php echo e((string) $row['agency_name']); ?> | <?php echo e((string) $row['vendor_name']); ?> | <?php echo e((string) $row['category_name']); ?></p>
    <p class="muted">#<?php echo e((string) $row['contract_number']); ?> | Posted <?php echo e((string) $row['posted_date']); ?> | Due <?php echo e((string) $row['response_deadline']); ?> | <?php echo e((string) $row['status']); ?> | <?php echo e((string) $row['set_aside_label']); ?></p>
  </article>
  <hr>
<?php endforeach; ?>
</section>
<?php include __DIR__ . '/templates/footer.php'; ?>
