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
  <?php
    $state = derive_public_contract_state($contract);
    $isSuspiciousRecord = is_suspicious_public_record($contract);
    $effectiveDescription = contract_effective_description($contract);
    $valueLine = join_display_parts([
        ($primaryValue = display_contract_value_or_null($contract)) ? 'Value: ' . $primaryValue : '',
        ($rangeValue = display_contract_value_or_null(['award_amount' => null, 'value_min' => $contract['value_min'] ?? null, 'value_max' => $contract['value_max'] ?? null])) ? 'Range: ' . $rangeValue : '',
    ]);
    $datesLine = join_display_parts([
        ($posted = display_field_or_null('posted_date', $contract['posted_date'] ?? null)) ? 'Posted ' . $posted : '',
        ($award = display_field_or_null('award_date', $contract['award_date'] ?? null)) ? 'Award ' . $award : '',
        ($due = display_field_or_null('response_deadline', $contract['response_deadline'] ?? null)) ? 'Due ' . $due : '',
        ($end = display_field_or_null('end_date', $contract['end_date'] ?? null)) ? 'End ' . $end : '',
    ]);
    $actionabilityLine = join_display_parts([
        in_array('Open Now', $state['tags'], true) ? 'Open Now' : '',
        in_array('Early Signal', $state['tags'], true) ? 'Early Signal' : '',
        in_array('Awarded', $state['tags'], true) ? 'Awarded' : '',
        in_array('Deadline Soon', $state['tags'], true) ? 'Deadline Soon' : '',
        in_array('Archived', $state['tags'], true) ? 'Archived' : '',
    ]);
    $safeSourceUrl = safe_external_url($contract['source_url'] ?? null);
    $rawNotice = trim((string) ($contract['description_raw'] ?? ''));
    $noticeDescPending = $effectiveDescription === '' && (bool) preg_match('~https?://[^\\s]*noticedesc~i', $rawNotice);
  ?>
  <h1><?php echo e(display_field_value('title', $contract['display_title_effective'] ?? $contract['title'] ?? null)); ?></h1>
  <section class="card contract-details">
    <?php if ($isSuspiciousRecord): ?><p class="warn">This source record contains outlier or test-like values. Actionability is conservatively suppressed.</p><?php endif; ?>
    <p><strong>Agency:</strong>
      <?php if ((int) ($contract['agency_id'] ?? 0) > 0): ?>
        <a href="<?php echo e(app_url('agency.php?id=' . (int) $contract['agency_id'])); ?>"><?php echo e(display_field_value('agency', $contract['agency_name'] ?? null)); ?></a>
      <?php else: ?>
        <?php echo e(display_field_value('agency', $contract['agency_name'] ?? null)); ?>
      <?php endif; ?>
    </p>
    <p><strong>Vendor:</strong>
      <?php if ((int) ($contract['vendor_id'] ?? 0) > 0): ?>
        <a href="<?php echo e(app_url('vendor.php?id=' . (int) $contract['vendor_id'])); ?>"><?php echo e(display_field_value('vendor', $contract['vendor_name'] ?? null)); ?></a>
      <?php else: ?>
        <?php echo e(display_field_value('vendor', $contract['vendor_name'] ?? null)); ?>
      <?php endif; ?>
    </p>
    <p><strong>Category:</strong>
      <?php if (trim((string) ($contract['category_slug'] ?? '')) !== ''): ?>
        <a href="<?php echo e(app_url('category.php?slug=' . urlencode((string) $contract['category_slug']))); ?>"><?php echo e(display_field_value('category', $contract['category_name'] ?? null)); ?></a>
      <?php else: ?>
        <?php echo e(display_field_value('category', $contract['category_name'] ?? null)); ?>
      <?php endif; ?>
    </p>
    <?php if (($sourceLabel = display_field_or_null('source_name', $contract['source_name_resolved'] ?? null)) !== null): ?><p><strong>Source:</strong> <?php echo e($sourceLabel); ?></p><?php endif; ?>
    <?php if (($noticeType = display_field_or_null('notice_type', $contract['notice_type'] ?? null)) !== null): ?><p><strong>Notice Type:</strong> <?php echo e($noticeType); ?></p><?php endif; ?>
    <?php if (($setAside = display_field_or_null('set_aside', $contract['set_aside_label'] ?? null)) !== null): ?><p><strong>Set-Aside:</strong> <?php echo e($setAside); ?></p><?php endif; ?>
    <?php if (($contractNumber = display_field_or_null('contract_number', $contract['contract_number'] ?? null)) !== null): ?><p><strong>Contract Number:</strong> <?php echo e($contractNumber); ?></p><?php endif; ?>
    <?php
      $naicsCode = display_field_or_null('naics_code', $contract['naics_code'] ?? null);
      $pscCode = display_field_or_null('psc_code', $contract['psc_code'] ?? null);
    ?>
    <?php if ($naicsCode !== null || $pscCode !== null): ?>
      <p>
        <?php if ($naicsCode !== null): ?><strong>NAICS:</strong> <?php echo e($naicsCode); ?><?php endif; ?>
        <?php if ($naicsCode !== null && $pscCode !== null): ?> | <?php endif; ?>
        <?php if ($pscCode !== null): ?><strong>PSC:</strong> <?php echo e($pscCode); ?><?php endif; ?>
      </p>
    <?php endif; ?>
    <?php if ($valueLine !== ''): ?><p><strong><?php echo e($valueLine); ?></strong></p><?php endif; ?>
    <?php if ($datesLine !== ''): ?><p><strong>Dates:</strong> <?php echo e($datesLine); ?></p><?php endif; ?>
    <p><strong>Status:</strong> <?php echo e($state['public_status']); ?></p>
    <?php if ($actionabilityLine !== ''): ?><p><strong>Actionability:</strong> <?php echo e($actionabilityLine); ?></p><?php endif; ?>
    <?php
      $pop = display_field_or_null('place_of_performance', $contract['place_of_performance'] ?? null);
      $popState = display_field_or_null('place_state', $contract['place_state'] ?? null);
    ?>
    <?php if ($pop !== null || $popState !== null): ?>
      <p>
        <strong>Place of Performance:</strong>
        <?php if ($pop !== null): ?><?php echo e($pop); ?><?php endif; ?>
        <?php if ($popState !== null): ?><?php echo $pop !== null ? ' (' . e($popState) . ')' : e($popState); ?><?php endif; ?>
      </p>
    <?php endif; ?>
    <?php if (!is_empty_display_value($effectiveDescription)): ?>
      <p><strong>Description:</strong> <?php echo nl2br(e($effectiveDescription)); ?></p>
      <?php
        $effectiveNotice = trim($effectiveDescription);
      ?>
      <?php if (!is_empty_display_value($rawNotice) && description_is_displayable_text($rawNotice) && $rawNotice !== $effectiveNotice): ?>
        <details>
          <summary>View original notice wording</summary>
          <p><?php echo nl2br(e($rawNotice)); ?></p>
        </details>
      <?php endif; ?>
    <?php elseif ($noticeDescPending): ?>
      <p class="muted"><strong>Description:</strong> Full notice text is pending source enrichment for this record.</p>
    <?php endif; ?>
    <h3>Public Contact Information</h3>
    <?php if (($contactName = display_field_or_null('contact_name', $contract['contact_name'] ?? null)) !== null): ?><p><strong>Name:</strong> <?php echo e($contactName); ?></p><?php endif; ?>
    <?php if (($contactEmail = display_field_or_null('contact_email', $contract['contact_email'] ?? null)) !== null): ?><p><strong>Email:</strong> <?php echo e($contactEmail); ?></p><?php endif; ?>
    <?php if (($contactPhone = display_field_or_null('contact_phone', $contract['contact_phone'] ?? null)) !== null): ?><p><strong>Phone:</strong> <?php echo e($contactPhone); ?></p><?php endif; ?>
    <?php if (($contractOffice = display_field_or_null('contracting_office', $contract['contracting_office'] ?? null)) !== null): ?><p><strong>Contracting Office:</strong> <?php echo e($contractOffice); ?></p><?php endif; ?>
    <?php if (($contactAddress = display_field_or_null('contact_address', $contract['contact_address'] ?? null)) !== null): ?><p><strong>Address:</strong> <?php echo e($contactAddress); ?></p><?php endif; ?>
    <?php if ($safeSourceUrl !== null): ?>
      <p><strong>Source URL:</strong> <a href="<?php echo e($safeSourceUrl); ?>" target="_blank" rel="noopener">View source notice</a></p>
    <?php endif; ?>
    <?php if ((int) $contract['is_duplicate'] === 1): ?>
      <p class="warn">Marked duplicate of contract ID <?php echo (int) $contract['duplicate_of']; ?> (<?php echo e((string) $contract['dedupe_reason']); ?>)</p>
    <?php endif; ?>
  </section>
  <?php include __DIR__ . '/templates/contract_future_insights.php'; ?>
<?php endif; ?>
<?php include __DIR__ . '/templates/footer.php'; ?>
