<?php
require_once __DIR__ . '/includes/functions.php';
$pdo = db();
$pageTitle = 'PatriotContracts | Recent Awards';

$stmt = $pdo->query('SELECT cc.id, cc.title, cc.contract_number, cc.award_amount, cc.award_date, cc.posted_date, cc.status,
    a.name AS agency_name, v.name AS vendor_name, cat.name AS category_name
    FROM contracts_clean cc
    LEFT JOIN agencies a ON a.id = cc.agency_id
    LEFT JOIN vendors v ON v.id = cc.vendor_id
    LEFT JOIN contract_categories cat ON cat.id = cc.category_id
    WHERE cc.is_duplicate = 0
      AND cc.is_awarded = 1
      AND COALESCE(cc.award_date, cc.posted_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ORDER BY COALESCE(cc.award_date, cc.posted_date) DESC, cc.id DESC
    LIMIT 200');
$rows = $stmt->fetchAll();

include __DIR__ . '/templates/header.php';
?>
<h1>Recent Awards</h1>
<p class="muted">Recent award and historical records for agency and competitor intelligence.</p>
<section class="card">
<?php if (!$rows): ?>
  <p>No recent awards found.</p>
<?php endif; ?>
<?php foreach ($rows as $row): ?>
  <article>
    <h3><a href="contract.php?id=<?php echo (int) $row['id']; ?>"><?php echo e(display_field_value('title', $row['title'] ?? null)); ?></a></h3>
    <p class="muted"><?php echo e(display_field_value('agency', $row['agency_name'] ?? null)); ?> | <?php echo e(display_field_value('vendor', $row['vendor_name'] ?? null)); ?> | <?php echo e(display_field_value('category', $row['category_name'] ?? null)); ?></p>
    <p class="muted">#<?php echo e(display_field_value('contract_number', $row['contract_number'] ?? null)); ?> | <?php echo e(display_contract_value($row, display_field_value('award_value', null))); ?> | Award <?php echo e(display_field_value('award_date', $row['award_date'] ?? null)); ?> | Posted <?php echo e(display_field_value('posted_date', $row['posted_date'] ?? null)); ?> | <?php echo e(display_field_value('status', $row['status'] ?? null)); ?></p>
  </article>
  <hr>
<?php endforeach; ?>
</section>
<?php include __DIR__ . '/templates/footer.php'; ?>
