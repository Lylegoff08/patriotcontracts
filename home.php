<?php
require_once __DIR__ . '/includes/functions.php';
$pdo = db();
$pageTitle = 'PatriotContracts | Federal Contract Listings';

$latest = $pdo->query('SELECT cc.id,
    COALESCE(NULLIF(lo.display_title, ""), NULLIF(go.display_title, ""), cc.title) AS title,
    COALESCE(NULLIF(lo.display_summary, ""), NULLIF(go.display_summary, ""), NULLIF(cc.description_clean, ""), NULLIF(cc.description_raw, ""), cc.description) AS description,
    cc.description_raw, cc.description_clean,
    cc.contract_number, cc.posted_date, cc.award_date, cc.response_deadline, cc.award_amount, cc.status, cc.naics_code, cc.psc_code, cc.contact_name, cc.contracting_office, cc.place_of_performance, cc.set_aside_label, cc.notice_type,
    a.name AS agency_name, v.name AS vendor_name, COALESCE(ocat.name, cat.name) AS category_name, COALESCE(NULLIF(cc.source_name, ""), s.name) AS source_name
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
    ORDER BY cc.posted_date DESC, cc.id DESC LIMIT 25')->fetchAll();

$openCount = (int) $pdo->query('SELECT COUNT(*)
    FROM contracts_clean cc
    LEFT JOIN listing_overrides lo ON lo.contract_id = cc.id
    LEFT JOIN grant_overrides go ON go.contract_id = cc.id
    WHERE cc.is_duplicate = 0
      AND cc.is_biddable_now = 1
      AND cc.is_awarded = 0
      AND (cc.response_deadline IS NULL OR cc.response_deadline >= CURDATE())
      AND cc.status NOT IN ("closed", "archived", "expired", "cancelled")
      AND COALESCE(lo.is_hidden, go.is_hidden, 0) = 0')->fetchColumn();
$signalCount = (int) $pdo->query('SELECT COUNT(*)
    FROM contracts_clean cc
    LEFT JOIN listing_overrides lo ON lo.contract_id = cc.id
    LEFT JOIN grant_overrides go ON go.contract_id = cc.id
    WHERE cc.is_duplicate = 0
      AND cc.is_upcoming_signal = 1
      AND cc.is_awarded = 0
      AND COALESCE(lo.is_hidden, go.is_hidden, 0) = 0')->fetchColumn();
$dueSoonCount = (int) $pdo->query('SELECT COUNT(*)
    FROM contracts_clean cc
    LEFT JOIN listing_overrides lo ON lo.contract_id = cc.id
    LEFT JOIN grant_overrides go ON go.contract_id = cc.id
    WHERE cc.is_duplicate = 0
      AND cc.is_biddable_now = 1
      AND cc.deadline_soon = 1
      AND cc.is_awarded = 0
      AND cc.response_deadline BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
      AND COALESCE(lo.is_hidden, go.is_hidden, 0) = 0')->fetchColumn();
$awardCount = (int) $pdo->query('SELECT COUNT(*)
    FROM contracts_clean cc
    LEFT JOIN listing_overrides lo ON lo.contract_id = cc.id
    LEFT JOIN grant_overrides go ON go.contract_id = cc.id
    WHERE cc.is_duplicate = 0 AND cc.is_awarded = 1 AND COALESCE(lo.is_hidden, go.is_hidden, 0) = 0')->fetchColumn();
$archiveCount = (int) $pdo->query("SELECT COUNT(*) FROM contracts_clean cc
    LEFT JOIN listing_overrides lo ON lo.contract_id = cc.id
    LEFT JOIN grant_overrides go ON go.contract_id = cc.id
    WHERE is_duplicate = 0
      AND COALESCE(lo.is_hidden, go.is_hidden, 0) = 0
      AND (
        (response_deadline IS NOT NULL AND response_deadline < CURDATE())
        OR (end_date IS NOT NULL AND end_date < CURDATE())
        OR status IN ('closed', 'archived', 'expired', 'cancelled')
        OR is_awarded = 1
      )")->fetchColumn();

$topCategories = $pdo->query('SELECT cat.slug, cat.name, COUNT(*) AS total
    FROM contracts_clean cc
    LEFT JOIN listing_overrides lo ON lo.contract_id = cc.id
    LEFT JOIN grant_overrides go ON go.contract_id = cc.id
    JOIN contract_categories cat ON cat.id = COALESCE(lo.category_override, go.category_override, cc.category_id)
    WHERE cc.is_duplicate = 0
      AND COALESCE(lo.is_hidden, go.is_hidden, 0) = 0
    GROUP BY cat.id ORDER BY total DESC LIMIT 6')->fetchAll();

$topAgencies = $pdo->query('SELECT a.id, a.name, COUNT(*) AS total
    FROM contracts_clean cc
    LEFT JOIN listing_overrides lo ON lo.contract_id = cc.id
    LEFT JOIN grant_overrides go ON go.contract_id = cc.id
    JOIN agencies a ON a.id = cc.agency_id
    WHERE cc.is_duplicate = 0
      AND COALESCE(lo.is_hidden, go.is_hidden, 0) = 0
    GROUP BY a.id ORDER BY total DESC LIMIT 6')->fetchAll();

include __DIR__ . '/templates/header.php';
?>
<section class="hero">
  <h1>Federal Contract Listings</h1>
  <p class="muted">Structured U.S. procurement records with normalized agency and vendor details.</p>
  <form method="get" action="<?php echo e(app_url('search.php')); ?>" class="search-form">
    <input type="text" name="q" placeholder="Search contracts, agencies, vendors, NAICS, PSC">
    <button type="submit" class="btn">Search</button>
  </form>
</section>

<section class="home-listings-layout">
  <div class="home-listings-left">
    <div class="card compact-pipeline">
      <h2>Actionable Pipeline</h2>
      <p><a href="<?php echo e(app_url('open-this-week.php')); ?>">Open Now</a>: <?php echo number_format($openCount); ?></p>
      <p><a href="<?php echo e(app_url('early-signals.php')); ?>">Early Signals</a>: <?php echo number_format($signalCount); ?></p>
      <p><a href="<?php echo e(app_url('deadline-soon.php')); ?>">Deadline Soon</a>: <?php echo number_format($dueSoonCount); ?></p>
      <p><a href="<?php echo e(app_url('recent-awards.php')); ?>">Recent Awards</a>: <?php echo number_format($awardCount); ?></p>
      <p><a href="<?php echo e(app_url('archive.php')); ?>">Archive</a>: <?php echo number_format($archiveCount); ?></p>
    </div>

    <div class="card">
      <h2>Latest Contracts</h2>
      <?php foreach ($latest as $row): ?>
        <?php if (is_suspicious_public_record($row)) { continue; } ?>
        <?php $listingDescription = contract_listing_description($row); ?>
        <?php
          $metaTop = join_display_parts([
            display_field_or_null('agency', $row['agency_name'] ?? null),
            display_field_or_null('vendor', $row['vendor_name'] ?? null),
            display_field_or_null('category', $row['category_name'] ?? null),
            display_contract_value_or_null($row),
            display_field_or_null('source_name', $row['source_name'] ?? null),
          ]);
          $metaBottom = join_display_parts([
            ($posted = display_field_or_null('posted_date', $row['posted_date'] ?? null)) ? 'Posted: ' . $posted : '',
            ($award = display_field_or_null('award_date', $row['award_date'] ?? null)) ? 'Award: ' . $award : '',
            ($due = display_field_or_null('response_deadline', $row['response_deadline'] ?? null)) ? 'Due: ' . $due : '',
            display_field_or_null('status', $row['status'] ?? null),
          ]);
        ?>
        <article class="listing-item">
          <a href="<?php echo e(app_url('contract.php?id=' . (int) $row['id'])); ?>"><?php echo e(display_field_value('title', $row['title'] ?? null)); ?></a>
          <?php if ($metaTop !== ''): ?><div class="muted listing-meta"><?php echo e($metaTop); ?></div><?php endif; ?>
          <?php if ($metaBottom !== ''): ?><div class="muted listing-meta"><?php echo e($metaBottom); ?></div><?php endif; ?>
          <?php if ($listingDescription !== ''): ?><p class="listing-desc"><?php echo e($listingDescription); ?></p><?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="home-listings-right">
    <div class="card">
      <h2>Top Categories</h2>
      <?php foreach ($topCategories as $cat): ?>
        <p><a href="<?php echo e(app_url('category.php?slug=' . urlencode((string) $cat['slug']))); ?>"><?php echo e($cat['name']); ?></a> (<?php echo (int) $cat['total']; ?>)</p>
      <?php endforeach; ?>

      <h2>Top Agencies</h2>
      <?php foreach ($topAgencies as $agency): ?>
        <p><a href="<?php echo e(app_url('agency.php?id=' . (int) $agency['id'])); ?>"><?php echo e(display_field_value('agency', $agency['name'] ?? null)); ?></a> (<?php echo (int) $agency['total']; ?>)</p>
      <?php endforeach; ?>

      <section class="home-adsense-block" aria-label="Sponsored">
        <h3>Data Sources</h3>
        <p class="muted">Listings are aggregated from SAM.gov, USAspending.gov, and Grants.gov public datasets.</p>
        <p class="muted">Source links are provided on each contract detail page when available.</p>
      </section>
    </div>
  </div>
</section>
<?php include __DIR__ . '/templates/footer.php'; ?>
