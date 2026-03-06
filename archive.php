<?php
require_once __DIR__ . '/includes/functions.php';
$pdo = db();
$pageTitle = 'PatriotContracts | Archive';

$stmt = $pdo->query("SELECT cc.id, cc.title, cc.contract_number, cc.response_deadline, cc.end_date, cc.award_date, cc.posted_date, cc.status, cc.award_amount,
    a.name AS agency_name, v.name AS vendor_name, cat.name AS category_name
    FROM contracts_clean cc
    LEFT JOIN agencies a ON a.id = cc.agency_id
    LEFT JOIN vendors v ON v.id = cc.vendor_id
    LEFT JOIN contract_categories cat ON cat.id = cc.category_id
    WHERE cc.is_duplicate = 0
      AND (
        (cc.response_deadline IS NOT NULL AND cc.response_deadline < CURDATE())
        OR (cc.end_date IS NOT NULL AND cc.end_date < CURDATE())
        OR cc.status IN ('closed', 'archived', 'expired', 'cancelled')
        OR cc.is_awarded = 1
      )
    ORDER BY COALESCE(cc.response_deadline, cc.end_date, cc.award_date, cc.posted_date) DESC, cc.id DESC
    LIMIT 500");
$rows = $stmt->fetchAll();

include __DIR__ . '/templates/header.php';
?>
<h1>Archived Contracts</h1>
<p class="muted">Contracts that are already over based on deadline, end date, award state, or closed/archive status.</p>
<section class="card">
<?php if (!$rows): ?>
  <p>No archived contracts found.</p>
<?php endif; ?>
<?php foreach ($rows as $row): ?>
  <article>
    <h3><a href="contract.php?id=<?php echo (int) $row['id']; ?>"><?php echo e($row['title']); ?></a></h3>
    <p class="muted"><?php echo e(display_text($row['agency_name'] ?? null)); ?> | <?php echo e(display_text($row['vendor_name'] ?? null)); ?> | <?php echo e(display_text($row['category_name'] ?? null)); ?></p>
    <p class="muted">#<?php echo e(display_text($row['contract_number'] ?? null)); ?> | <?php echo e(display_contract_value($row)); ?> | Due <?php echo e(display_date($row['response_deadline'] ?? null)); ?> | End <?php echo e(display_date($row['end_date'] ?? null)); ?> | Award <?php echo e(display_date($row['award_date'] ?? null)); ?> | Posted <?php echo e(display_date($row['posted_date'] ?? null)); ?> | <?php echo e(display_text($row['status'] ?? null)); ?></p>
  </article>
  <hr>
<?php endforeach; ?>
</section>
<?php include __DIR__ . '/templates/footer.php'; ?>
