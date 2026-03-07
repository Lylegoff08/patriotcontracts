<?php
require_once __DIR__ . '/includes/functions.php';
$pdo = db();
$id = request_int('id', 0);

$stmt = $pdo->prepare('SELECT cc.*,
    COALESCE(NULLIF(lo.display_title, ""), NULLIF(go.display_title, ""), cc.title) AS display_title_effective,
    COALESCE(NULLIF(lo.display_summary, ""), NULLIF(go.display_summary, ""), NULLIF(cc.description_clean, ""), NULLIF(cc.description_raw, ""), cc.description) AS display_summary_effective,
    COALESCE(NULLIF(cc.source_name, ""), s.name) AS source_name_resolved,
    a.name AS agency_name,
    v.name AS vendor_name,
    COALESCE(ocat.name, cat.name) AS category_name,
    COALESCE(ocat.slug, cat.slug) AS category_slug
    FROM contracts_clean cc
    LEFT JOIN listing_overrides lo ON lo.contract_id = cc.id
    LEFT JOIN grant_overrides go ON go.contract_id = cc.id
    LEFT JOIN sources s ON s.id = cc.source_id
    LEFT JOIN agencies a ON a.id = cc.agency_id
    LEFT JOIN vendors v ON v.id = cc.vendor_id
    LEFT JOIN contract_categories cat ON cat.id = cc.category_id
    LEFT JOIN contract_categories ocat ON ocat.id = COALESCE(lo.category_override, go.category_override)
    WHERE cc.id = :id
      AND COALESCE(lo.is_hidden, go.is_hidden, 0) = 0
    LIMIT 1');
$stmt->execute(['id' => $id]);
$contract = $stmt->fetch();
$pageTitle = $contract ? 'PatriotContracts | ' . ($contract['display_title_effective'] ?? $contract['title']) : 'PatriotContracts | Contract';

include __DIR__ . '/templates/header.php';
?>
<?php if (!$contract): ?>
  <h1>Contract not found</h1>
<?php else: ?>
  <?php $effectiveDescription = contract_effective_description($contract); ?>
  <h1><?php echo e(display_field_value('title', $contract['display_title_effective'] ?? $contract['title'] ?? null)); ?></h1>
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
    <?php if (!is_empty_display_value($contract['source_name_resolved'] ?? null)): ?><p><strong>Source:</strong> <?php echo e((string) $contract['source_name_resolved']); ?></p><?php endif; ?>
    <?php if (!is_empty_display_value($contract['notice_type'] ?? null)): ?><p><strong>Notice Type:</strong> <?php echo e((string) $contract['notice_type']); ?></p><?php endif; ?>
    <?php if (!is_empty_display_value($contract['set_aside_label'] ?? null)): ?><p><strong>Set-Aside:</strong> <?php echo e((string) $contract['set_aside_label']); ?></p><?php endif; ?>
    <?php if (!is_empty_display_value($contract['contract_number'] ?? null)): ?><p><strong>Contract Number:</strong> <?php echo e((string) $contract['contract_number']); ?></p><?php endif; ?>
    <?php if (!is_empty_display_value($contract['naics_code'] ?? null) || !is_empty_display_value($contract['psc_code'] ?? null)): ?>
      <p>
        <?php if (!is_empty_display_value($contract['naics_code'] ?? null)): ?><strong>NAICS:</strong> <?php echo e((string) $contract['naics_code']); ?><?php endif; ?>
        <?php if (!is_empty_display_value($contract['naics_code'] ?? null) && !is_empty_display_value($contract['psc_code'] ?? null)): ?> | <?php endif; ?>
        <?php if (!is_empty_display_value($contract['psc_code'] ?? null)): ?><strong>PSC:</strong> <?php echo e((string) $contract['psc_code']); ?><?php endif; ?>
      </p>
    <?php endif; ?>
    <p><strong>Value:</strong> <?php echo e(display_contract_value($contract, display_field_value('award_value', null))); ?> | <strong>Range:</strong> <?php echo e(display_contract_value(['award_amount' => null, 'value_min' => $contract['value_min'] ?? null, 'value_max' => $contract['value_max'] ?? null], display_field_value('award_value', null))); ?></p>
    <p><strong>Dates:</strong> Posted <?php echo e(display_field_value('posted_date', $contract['posted_date'] ?? null)); ?> | Award <?php echo e(display_field_value('award_date', $contract['award_date'] ?? null)); ?> | Due <?php echo e(display_field_value('response_deadline', $contract['response_deadline'] ?? null)); ?> | End <?php echo e(display_field_value('end_date', $contract['end_date'] ?? null)); ?></p>
    <?php if (!is_empty_display_value($contract['status'] ?? null)): ?><p><strong>Status:</strong> <?php echo e((string) $contract['status']); ?></p><?php endif; ?>
    <p><strong>Actionability:</strong>
      <?php if ((int) $contract['is_biddable_now'] === 1): ?>Open Now <?php endif; ?>
      <?php if ((int) $contract['is_upcoming_signal'] === 1): ?>| Early Signal <?php endif; ?>
      <?php if ((int) $contract['is_awarded'] === 1): ?>| Awarded <?php endif; ?>
      <?php if ((int) $contract['deadline_soon'] === 1): ?>| Deadline Soon <?php endif; ?>
    </p>
    <?php
      $pop = trim((string) ($contract['place_of_performance'] ?? ''));
      $popState = trim((string) ($contract['place_state'] ?? ''));
    ?>
    <?php if (!is_empty_display_value($pop) || !is_empty_display_value($popState)): ?>
      <p>
        <strong>Place of Performance:</strong>
        <?php if (!is_empty_display_value($pop)): ?><?php echo e($pop); ?><?php endif; ?>
        <?php if (!is_empty_display_value($popState)): ?><?php echo !is_empty_display_value($pop) ? ' (' . e($popState) . ')' : e($popState); ?><?php endif; ?>
      </p>
    <?php endif; ?>
    <?php if (!is_empty_display_value($effectiveDescription)): ?>
      <p><strong>Description:</strong> <?php echo nl2br(e($effectiveDescription)); ?></p>
      <?php
        $rawNotice = trim((string) ($contract['description_raw'] ?? ''));
        $effectiveNotice = trim($effectiveDescription);
      ?>
      <?php if (!is_empty_display_value($rawNotice) && description_is_displayable_text($rawNotice) && $rawNotice !== $effectiveNotice): ?>
        <details>
          <summary>View original notice wording</summary>
          <p><?php echo nl2br(e($rawNotice)); ?></p>
        </details>
      <?php endif; ?>
    <?php endif; ?>
    <h3>Public Contact Information</h3>
    <?php if (!is_empty_display_value($contract['contact_name'] ?? null)): ?><p><strong>Name:</strong> <?php echo e((string) $contract['contact_name']); ?></p><?php endif; ?>
    <?php if (!is_empty_display_value($contract['contact_email'] ?? null)): ?><p><strong>Email:</strong> <?php echo e((string) $contract['contact_email']); ?></p><?php endif; ?>
    <?php if (!is_empty_display_value($contract['contact_phone'] ?? null)): ?><p><strong>Phone:</strong> <?php echo e((string) $contract['contact_phone']); ?></p><?php endif; ?>
    <?php if (!is_empty_display_value($contract['contracting_office'] ?? null)): ?><p><strong>Contracting Office:</strong> <?php echo e((string) $contract['contracting_office']); ?></p><?php endif; ?>
    <?php if (!is_empty_display_value($contract['contact_address'] ?? null)): ?><p><strong>Address:</strong> <?php echo e((string) $contract['contact_address']); ?></p><?php endif; ?>
    <?php if (trim((string) ($contract['source_url'] ?? '')) !== ''): ?>
      <p><strong>Source URL:</strong> <a href="<?php echo e((string) $contract['source_url']); ?>" target="_blank" rel="noopener">View source notice</a></p>
    <?php endif; ?>
    <?php if ((int) $contract['is_duplicate'] === 1): ?>
      <p class="warn">Marked duplicate of contract ID <?php echo (int) $contract['duplicate_of']; ?> (<?php echo e((string) $contract['dedupe_reason']); ?>)</p>
    <?php endif; ?>
  </section>
  <?php include __DIR__ . '/templates/contract_future_insights.php'; ?>
<?php endif; ?>
<?php include __DIR__ . '/templates/footer.php'; ?>
