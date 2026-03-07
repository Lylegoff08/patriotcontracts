<?php
require_once __DIR__ . '/includes/functions.php';
$pdo = db();
$pageTitle = 'PatriotContracts | Deadline Soon';

$stmt = $pdo->query('SELECT cc.id, cc.title, cc.contract_number, cc.response_deadline, cc.posted_date, cc.status, cc.set_aside_label,
    a.name AS agency_name, v.name AS vendor_name, cat.name AS category_name
    FROM contracts_clean cc
    LEFT JOIN agencies a ON a.id = cc.agency_id
    LEFT JOIN vendors v ON v.id = cc.vendor_id
    LEFT JOIN contract_categories cat ON cat.id = cc.category_id
    WHERE cc.is_duplicate = 0
      AND cc.is_biddable_now = 1
      AND cc.deadline_soon = 1
    ORDER BY cc.response_deadline ASC, cc.posted_date DESC
    LIMIT 200');
$rows = $stmt->fetchAll();

include __DIR__ . '/templates/header.php';
?>
<h1>Deadline Soon</h1>
<p class="muted">Open opportunities with response deadlines within the next 7 days.</p>
<section class="card">
<?php if (!$rows): ?>
  <p>No near-term deadlines found.</p>
<?php endif; ?>
<?php foreach ($rows as $row): ?>
  <article>
    <h3><a href="contract.php?id=<?php echo (int) $row['id']; ?>"><?php echo e(display_field_value('title', $row['title'] ?? null)); ?></a></h3>
    <p class="muted"><?php echo e(display_field_value('agency', $row['agency_name'] ?? null)); ?> | <?php echo e(display_field_value('vendor', $row['vendor_name'] ?? null)); ?> | <?php echo e(display_field_value('category', $row['category_name'] ?? null)); ?></p>
    <p class="muted">#<?php echo e(display_field_value('contract_number', $row['contract_number'] ?? null)); ?> | Due <?php echo e(display_field_value('response_deadline', $row['response_deadline'] ?? null)); ?> | Posted <?php echo e(display_field_value('posted_date', $row['posted_date'] ?? null)); ?> | <?php echo e(display_field_value('status', $row['status'] ?? null)); ?> | <?php echo e(display_field_value('set_aside', $row['set_aside_label'] ?? null)); ?></p>
  </article>
  <hr>
<?php endforeach; ?>
</section>
<?php include __DIR__ . '/templates/footer.php'; ?>

