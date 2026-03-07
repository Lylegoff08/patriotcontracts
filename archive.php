<?php
require_once __DIR__ . '/includes/functions.php';
$pdo = db();
$pageTitle = 'PatriotContracts | Archive';

$stmt = $pdo->query("SELECT cc.id, COALESCE(NULLIF(lo.display_title, ''), NULLIF(go.display_title, ''), cc.title) AS title, cc.contract_number, cc.response_deadline, cc.end_date, cc.award_date, cc.posted_date, cc.status, cc.award_amount, cc.source_type, COALESCE(NULLIF(cc.source_name, ''), s.name) AS source_name,
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
  <?php if (is_suspicious_public_record($row)) { continue; } ?>
  <?php $state = derive_public_contract_state($row); ?>
  <?php if (!$state['is_archived'] && !$state['is_awarded']) { continue; } ?>
  <?php
    $metaTop = join_display_parts([
      display_field_or_null('agency', $row['agency_name'] ?? null),
      display_field_or_null('vendor', $row['vendor_name'] ?? null),
      display_field_or_null('category', $row['category_name'] ?? null),
      display_field_or_null('source_name', $row['source_name'] ?? null),
    ]);
    $metaBottom = join_display_parts([
      display_field_or_null('contract_number', $row['contract_number'] ?? null),
      display_contract_value_or_null($row),
      ($due = display_field_or_null('response_deadline', $row['response_deadline'] ?? null)) ? 'Due ' . $due : '',
      ($end = display_field_or_null('end_date', $row['end_date'] ?? null)) ? 'End ' . $end : '',
      ($award = display_field_or_null('award_date', $row['award_date'] ?? null)) ? 'Award ' . $award : '',
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
