<?php
require_once __DIR__ . '/includes/functions.php';
$pdo = db();
$id = request_int('id', 0);

$stmt = $pdo->prepare('SELECT cc.*, COALESCE(NULLIF(cc.source_name, ""), s.name) AS source_name_resolved, a.name AS agency_name, v.name AS vendor_name, cat.name AS category_name, cat.slug AS category_slug
    FROM contracts_clean cc
    LEFT JOIN sources s ON s.id = cc.source_id
    LEFT JOIN agencies a ON a.id = cc.agency_id
    LEFT JOIN vendors v ON v.id = cc.vendor_id
    LEFT JOIN contract_categories cat ON cat.id = cc.category_id
    WHERE cc.id = :id LIMIT 1');
$stmt->execute(['id' => $id]);
$contract = $stmt->fetch();
$pageTitle = $contract ? 'PatriotContracts | ' . $contract['title'] : 'PatriotContracts | Contract';

include __DIR__ . '/templates/header.php';
?>
<?php if (!$contract): ?>
  <h1>Contract not found</h1>
<?php else: ?>
  <h1><?php echo e(display_field_value('title', $contract['title'] ?? null)); ?></h1>
  <section class="card contract-details">
    <p><strong>Agency:</strong>
      <?php if ((int) ($contract['agency_id'] ?? 0) > 0): ?>
        <a href="agency.php?id=<?php echo (int) $contract['agency_id']; ?>"><?php echo e(display_field_value('agency', $contract['agency_name'] ?? null)); ?></a>
      <?php else: ?>
        <?php echo e(display_field_value('agency', $contract['agency_name'] ?? null)); ?>
      <?php endif; ?>
    </p>
    <p><strong>Vendor:</strong>
      <?php if ((int) ($contract['vendor_id'] ?? 0) > 0): ?>
        <a href="vendor.php?id=<?php echo (int) $contract['vendor_id']; ?>"><?php echo e(display_field_value('vendor', $contract['vendor_name'] ?? null)); ?></a>
      <?php else: ?>
        <?php echo e(display_field_value('vendor', $contract['vendor_name'] ?? null)); ?>
      <?php endif; ?>
    </p>
    <p><strong>Category:</strong>
      <?php if (trim((string) ($contract['category_slug'] ?? '')) !== ''): ?>
        <a href="category.php?slug=<?php echo e((string) $contract['category_slug']); ?>"><?php echo e(display_field_value('category', $contract['category_name'] ?? null)); ?></a>
      <?php else: ?>
        <?php echo e(display_field_value('category', $contract['category_name'] ?? null)); ?>
      <?php endif; ?>
    </p>
    <p><strong>Source:</strong> <?php echo e(display_field_value('source_name', $contract['source_name_resolved'] ?? null)); ?></p>
    <p><strong>Notice Type:</strong> <?php echo e(display_field_value('notice_type', $contract['notice_type'] ?? null)); ?></p>
    <p><strong>Set-Aside:</strong> <?php echo e(display_field_value('set_aside', $contract['set_aside_label'] ?? null)); ?></p>
    <p><strong>Contract Number:</strong> <?php echo e(display_field_value('contract_number', $contract['contract_number'] ?? null)); ?></p>
    <p><strong>NAICS:</strong> <?php echo e(display_field_value('naics_code', $contract['naics_code'] ?? null)); ?> | <strong>PSC:</strong> <?php echo e(display_field_value('psc_code', $contract['psc_code'] ?? null)); ?></p>
    <p><strong>Value:</strong> <?php echo e(display_contract_value($contract, display_field_value('award_value', null))); ?> | <strong>Range:</strong> <?php echo e(display_contract_value(['award_amount' => null, 'value_min' => $contract['value_min'] ?? null, 'value_max' => $contract['value_max'] ?? null], display_field_value('award_value', null))); ?></p>
    <p><strong>Dates:</strong> Posted <?php echo e(display_field_value('posted_date', $contract['posted_date'] ?? null)); ?> | Award <?php echo e(display_field_value('award_date', $contract['award_date'] ?? null)); ?> | Due <?php echo e(display_field_value('response_deadline', $contract['response_deadline'] ?? null)); ?> | End <?php echo e(display_field_value('end_date', $contract['end_date'] ?? null)); ?></p>
    <p><strong>Status:</strong> <?php echo e(display_field_value('status', $contract['status'] ?? null)); ?></p>
    <p><strong>Actionability:</strong>
      <?php if ((int) $contract['is_biddable_now'] === 1): ?>Open Now <?php endif; ?>
      <?php if ((int) $contract['is_upcoming_signal'] === 1): ?>| Early Signal <?php endif; ?>
      <?php if ((int) $contract['is_awarded'] === 1): ?>| Awarded <?php endif; ?>
      <?php if ((int) $contract['deadline_soon'] === 1): ?>| Deadline Soon <?php endif; ?>
    </p>
    <p><strong>Place of Performance:</strong> <?php echo e(display_field_value('place_of_performance', $contract['place_of_performance'] ?? null)); ?> (<?php echo e(display_field_value('place_state', $contract['place_state'] ?? null)); ?>)</p>
    <p><strong>Description:</strong> <?php echo nl2br(e(display_field_value('description', $contract['description'] ?? null))); ?></p>
    <h3>Public Contact Information</h3>
    <p><strong>Name:</strong> <?php echo e(display_field_value('contact_name', $contract['contact_name'] ?? null)); ?></p>
    <p><strong>Email:</strong> <?php echo e(display_field_value('contact_email', $contract['contact_email'] ?? null)); ?></p>
    <p><strong>Phone:</strong> <?php echo e(display_field_value('contact_phone', $contract['contact_phone'] ?? null)); ?></p>
    <p><strong>Contracting Office:</strong> <?php echo e(display_field_value('contracting_office', $contract['contracting_office'] ?? null)); ?></p>
    <p><strong>Address:</strong> <?php echo e(display_field_value('contact_address', $contract['contact_address'] ?? null)); ?></p>
    <p><strong>Source URL:</strong>
      <?php if (trim((string) ($contract['source_url'] ?? '')) !== ''): ?>
        <a href="<?php echo e((string) $contract['source_url']); ?>" target="_blank" rel="noopener">View source notice</a>
      <?php else: ?>
        <?php echo e(display_field_value('source_url', null)); ?>
      <?php endif; ?>
    </p>
    <?php if ((int) $contract['is_duplicate'] === 1): ?>
      <p class="warn">Marked duplicate of contract ID <?php echo (int) $contract['duplicate_of']; ?> (<?php echo e((string) $contract['dedupe_reason']); ?>)</p>
    <?php endif; ?>
  </section>
  <?php include __DIR__ . '/templates/contract_future_insights.php'; ?>
<?php endif; ?>
<?php include __DIR__ . '/templates/footer.php'; ?>
