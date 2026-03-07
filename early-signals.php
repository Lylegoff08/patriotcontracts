<?php
require_once __DIR__ . '/includes/functions.php';
$pdo = db();
$pageTitle = 'PatriotContracts | Early Signals';

$stmt = $pdo->query('SELECT cc.id, COALESCE(NULLIF(lo.display_title, ""), NULLIF(go.display_title, ""), cc.title) AS title, cc.contract_number, cc.notice_type, cc.posted_date, cc.status, cc.source_type, COALESCE(NULLIF(cc.source_name, ""), s.name) AS source_name,
    a.name AS agency_name, v.name AS vendor_name, COALESCE(ocat.name, cat.name) AS category_name
    FROM contracts_clean cc
    LEFT JOIN listing_overrides lo ON lo.contract_id = cc.id
    LEFT JOIN grant_overrides go ON go.contract_id = cc.id
    LEFT JOIN sources s ON s.id = cc.source_id
    LEFT JOIN agencies a ON a.id = cc.agency_id
    LEFT JOIN vendors v ON v.id = cc.vendor_id
    LEFT JOIN contract_categories cat ON cat.id = cc.category_id
    LEFT JOIN contract_categories ocat ON ocat.id = COALESCE(lo.category_override, go.category_override)
    WHERE cc.is_duplicate = 0
      AND COALESCE(lo.is_hidden, go.is_hidden, 0) = 0
      AND cc.is_upcoming_signal = 1
    ORDER BY cc.posted_date DESC, cc.id DESC
    LIMIT 200');
$rows = $stmt->fetchAll();

include __DIR__ . '/templates/header.php';
?>
<h1>Early Signals</h1>
<p class="muted">RFI, sources sought, presolicitation, and related market research notices.</p>
<section class="card">
<?php if (!$rows): ?>
  <p>No early signals found.</p>
<?php endif; ?>
<?php foreach ($rows as $row): ?>
  <?php if (is_suspicious_public_record($row)) { continue; } ?>
  <?php $state = derive_public_contract_state($row); ?>
  <?php if (!$state['is_early_signal']) { continue; } ?>
  <?php
    $metaTop = join_display_parts([
      display_field_or_null('agency', $row['agency_name'] ?? null),
      display_field_or_null('vendor', $row['vendor_name'] ?? null),
      display_field_or_null('category', $row['category_name'] ?? null),
      display_field_or_null('source_name', $row['source_name'] ?? null),
    ]);
    $metaBottom = join_display_parts([
      display_field_or_null('contract_number', $row['contract_number'] ?? null),
      ($notice = display_field_or_null('notice_type', $row['notice_type'] ?? null)) ? 'Notice: ' . $notice : '',
      ($posted = display_field_or_null('posted_date', $row['posted_date'] ?? null)) ? 'Posted ' . $posted : '',
      display_field_or_null('status', $row['status'] ?? null),
    ]);
  ?>
  <article>
    <h3><a href="<?php echo e(app_url('contract.php?id=' . (int) $row['id'])); ?>"><?php echo e(display_field_value('title', $row['title'] ?? null)); ?></a></h3>
    <?php if ($metaTop !== ''): ?><p class="muted"><?php echo e($metaTop); ?></p><?php endif; ?>
    <?php if ($metaBottom !== ''): ?><p class="muted"><?php echo e($metaBottom); ?></p><?php endif; ?>
  </article>
  <hr>
<?php endforeach; ?>
</section>
<?php include __DIR__ . '/templates/footer.php'; ?>

