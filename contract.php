<?php
require_once __DIR__ . '/includes/functions.php';
$pdo = db();
$id = request_int('id', 0);

$stmt = $pdo->prepare('SELECT cc.*, a.name AS agency_name, v.name AS vendor_name, cat.name AS category_name, cat.slug AS category_slug
    FROM contracts_clean cc
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
  <h1><?php echo e($contract['title']); ?></h1>
  <section class="card">
    <p><strong>Agency:</strong> <a href="agency.php?id=<?php echo (int) $contract['agency_id']; ?>"><?php echo e((string) $contract['agency_name']); ?></a></p>
    <p><strong>Vendor:</strong> <a href="vendor.php?id=<?php echo (int) $contract['vendor_id']; ?>"><?php echo e((string) $contract['vendor_name']); ?></a></p>
    <p><strong>Category:</strong> <a href="category.php?slug=<?php echo e((string) $contract['category_slug']); ?>"><?php echo e((string) $contract['category_name']); ?></a></p>
    <p><strong>Source:</strong> <?php echo e((string) $contract['source_name']); ?></p>
    <p><strong>Notice Type:</strong> <?php echo e((string) $contract['notice_type']); ?></p>
    <p><strong>Set-Aside:</strong> <?php echo e((string) $contract['set_aside_label']); ?></p>
    <p><strong>Contract Number:</strong> <?php echo e((string) $contract['contract_number']); ?></p>
    <p><strong>NAICS:</strong> <?php echo e((string) $contract['naics_code']); ?> | <strong>PSC:</strong> <?php echo e((string) $contract['psc_code']); ?></p>
    <p><strong>Value:</strong> $<?php echo number_format((float) $contract['award_amount'], 2); ?> | <strong>Range:</strong> $<?php echo number_format((float) $contract['value_min'], 2); ?> - $<?php echo number_format((float) $contract['value_max'], 2); ?></p>
    <p><strong>Dates:</strong> Posted <?php echo e((string) $contract['posted_date']); ?> | Award <?php echo e((string) $contract['award_date']); ?> | Due <?php echo e((string) $contract['response_deadline']); ?> | End <?php echo e((string) $contract['end_date']); ?></p>
    <p><strong>Status:</strong> <?php echo e((string) $contract['status']); ?></p>
    <p><strong>Actionability:</strong>
      <?php if ((int) $contract['is_biddable_now'] === 1): ?>Open Now <?php endif; ?>
      <?php if ((int) $contract['is_upcoming_signal'] === 1): ?>| Early Signal <?php endif; ?>
      <?php if ((int) $contract['is_awarded'] === 1): ?>| Awarded <?php endif; ?>
      <?php if ((int) $contract['deadline_soon'] === 1): ?>| Deadline Soon <?php endif; ?>
    </p>
    <p><strong>Place of Performance:</strong> <?php echo e((string) $contract['place_of_performance']); ?> (<?php echo e((string) $contract['place_state']); ?>)</p>
    <p><strong>Description:</strong> <?php echo nl2br(e((string) $contract['description'])); ?></p>
    <h3>Public Contact Information</h3>
    <p><strong>Name:</strong> <?php echo e((string) $contract['contact_name']); ?></p>
    <p><strong>Email:</strong> <?php echo e((string) $contract['contact_email']); ?></p>
    <p><strong>Phone:</strong> <?php echo e((string) $contract['contact_phone']); ?></p>
    <p><strong>Contracting Office:</strong> <?php echo e((string) $contract['contracting_office']); ?></p>
    <p><strong>Address:</strong> <?php echo e((string) $contract['contact_address']); ?></p>
    <p><strong>Source URL:</strong> <a href="<?php echo e((string) $contract['source_url']); ?>" target="_blank" rel="noopener">Official Listing</a></p>
    <?php if ((int) $contract['is_duplicate'] === 1): ?>
      <p class="warn">Marked duplicate of contract ID <?php echo (int) $contract['duplicate_of']; ?> (<?php echo e((string) $contract['dedupe_reason']); ?>)</p>
    <?php endif; ?>
  </section>
  <?php include __DIR__ . '/templates/contract_future_insights.php'; ?>
<?php endif; ?>
<?php include __DIR__ . '/templates/footer.php'; ?>
