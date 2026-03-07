<?php
require_once __DIR__ . '/includes/functions.php';
$pdo = db();
$pageTitle = 'PatriotContracts | Open This Week';

$stmt = $pdo->query('SELECT cc.id, COALESCE(NULLIF(lo.display_title, ""), NULLIF(go.display_title, ""), cc.title) AS title, cc.contract_number, cc.response_deadline, cc.posted_date, cc.status, cc.set_aside_label,
    a.name AS agency_name, v.name AS vendor_name, COALESCE(ocat.name, cat.name) AS category_name
    FROM contracts_clean cc
    LEFT JOIN listing_overrides lo ON lo.contract_id = cc.id
    LEFT JOIN grant_overrides go ON go.contract_id = cc.id
    LEFT JOIN agencies a ON a.id = cc.agency_id
    LEFT JOIN vendors v ON v.id = cc.vendor_id
    LEFT JOIN contract_categories cat ON cat.id = cc.category_id
    LEFT JOIN contract_categories ocat ON ocat.id = COALESCE(lo.category_override, go.category_override)
    WHERE cc.is_duplicate = 0
      AND COALESCE(lo.is_hidden, go.is_hidden, 0) = 0
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
    <h3><a href="contract.php?id=<?php echo (int) $row['id']; ?>"><?php echo e(display_field_value('title', $row['title'] ?? null)); ?></a></h3>
    <p class="muted"><?php echo e(display_field_value('agency', $row['agency_name'] ?? null)); ?> | <?php echo e(display_field_value('vendor', $row['vendor_name'] ?? null)); ?> | <?php echo e(display_field_value('category', $row['category_name'] ?? null)); ?></p>
    <p class="muted">#<?php echo e(display_field_value('contract_number', $row['contract_number'] ?? null)); ?> | Posted <?php echo e(display_field_value('posted_date', $row['posted_date'] ?? null)); ?> | Due <?php echo e(display_field_value('response_deadline', $row['response_deadline'] ?? null)); ?> | <?php echo e(display_field_value('status', $row['status'] ?? null)); ?> | <?php echo e(display_field_value('set_aside', $row['set_aside_label'] ?? null)); ?></p>
  </article>
  <hr>
<?php endforeach; ?>
</section>
<?php include __DIR__ . '/templates/footer.php'; ?>

