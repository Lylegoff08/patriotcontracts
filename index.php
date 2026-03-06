<?php
require_once __DIR__ . '/includes/functions.php';
$pdo = db();
$pageTitle = 'PatriotContracts | Federal Contract Listings';

$latest = $pdo->query('SELECT cc.id, cc.title, cc.description, cc.contract_number, cc.posted_date, cc.award_date, cc.response_deadline, cc.award_amount, cc.status, cc.naics_code, cc.psc_code, cc.contact_name, cc.contracting_office, cc.place_of_performance, cc.set_aside_label, cc.notice_type, a.name AS agency_name, v.name AS vendor_name, cat.name AS category_name
    FROM contracts_clean cc
    LEFT JOIN agencies a ON a.id = cc.agency_id
    LEFT JOIN vendors v ON v.id = cc.vendor_id
    LEFT JOIN contract_categories cat ON cat.id = cc.category_id
    WHERE cc.is_duplicate = 0
    ORDER BY cc.posted_date DESC, cc.id DESC LIMIT 25')->fetchAll();

$openCount = (int) $pdo->query('SELECT COUNT(*) FROM contracts_clean WHERE is_duplicate = 0 AND is_biddable_now = 1')->fetchColumn();
$signalCount = (int) $pdo->query('SELECT COUNT(*) FROM contracts_clean WHERE is_duplicate = 0 AND is_upcoming_signal = 1')->fetchColumn();
$dueSoonCount = (int) $pdo->query('SELECT COUNT(*) FROM contracts_clean WHERE is_duplicate = 0 AND deadline_soon = 1')->fetchColumn();
$awardCount = (int) $pdo->query('SELECT COUNT(*) FROM contracts_clean WHERE is_duplicate = 0 AND is_awarded = 1')->fetchColumn();

$topCategories = $pdo->query('SELECT cat.slug, cat.name, COUNT(*) AS total
    FROM contracts_clean cc JOIN contract_categories cat ON cat.id = cc.category_id
    WHERE cc.is_duplicate = 0
    GROUP BY cat.id ORDER BY total DESC LIMIT 6')->fetchAll();

$topAgencies = $pdo->query('SELECT a.id, a.name, COUNT(*) AS total
    FROM contracts_clean cc JOIN agencies a ON a.id = cc.agency_id
    WHERE cc.is_duplicate = 0
    GROUP BY a.id ORDER BY total DESC LIMIT 6')->fetchAll();

include __DIR__ . '/templates/header.php';
?>
<section class="hero">
  <h1>Federal Contract Listings</h1>
  <p class="muted">Structured U.S. procurement records with normalized agency and vendor details.</p>
  <form method="get" action="search.php" class="search-form">
    <input type="text" name="q" placeholder="Search contracts, agencies, vendors, NAICS, PSC">
    <button type="submit" class="btn">Search</button>
  </form>
</section>

<section class="grid-2">
  <div class="card compact-pipeline">
    <h2>Actionable Pipeline</h2>
    <p><a href="open-this-week.php">Open Now</a>: <?php echo number_format($openCount); ?></p>
    <p><a href="early-signals.php">Early Signals</a>: <?php echo number_format($signalCount); ?></p>
    <p><a href="deadline-soon.php">Deadline Soon</a>: <?php echo number_format($dueSoonCount); ?></p>
    <p><a href="recent-awards.php">Recent Awards</a>: <?php echo number_format($awardCount); ?></p>
  </div>
</section>

<section class="grid-2">
  <div class="card">
    <h2>Latest Contracts</h2>
    <?php foreach ($latest as $row): ?>
      <article class="listing-item">
        <a href="contract.php?id=<?php echo (int) $row['id']; ?>"><?php echo e($row['title']); ?></a>
        <div class="muted listing-meta"><?php echo e((string) $row['agency_name']); ?> | <?php echo e((string) $row['vendor_name']); ?> | <?php echo e((string) $row['category_name']); ?> | $<?php echo number_format((float) $row['award_amount'], 2); ?></div>
        <div class="muted listing-meta">Posted: <?php echo e((string) $row['posted_date']); ?> | Award: <?php echo e((string) $row['award_date']); ?> | Due: <?php echo e((string) $row['response_deadline']); ?> | Status: <?php echo e((string) $row['status']); ?></div>
        <p class="listing-desc"><?php echo e(contract_listing_description($row)); ?></p>
      </article>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <h2>Top Categories</h2>
    <?php foreach ($topCategories as $cat): ?>
      <p><a href="category.php?slug=<?php echo e($cat['slug']); ?>"><?php echo e($cat['name']); ?></a> (<?php echo (int) $cat['total']; ?>)</p>
    <?php endforeach; ?>

    <h2>Top Agencies</h2>
    <?php foreach ($topAgencies as $agency): ?>
      <p><a href="agency.php?id=<?php echo (int) $agency['id']; ?>"><?php echo e($agency['name']); ?></a> (<?php echo (int) $agency['total']; ?>)</p>
    <?php endforeach; ?>
  </div>
</section>
<?php include __DIR__ . '/templates/footer.php'; ?>
