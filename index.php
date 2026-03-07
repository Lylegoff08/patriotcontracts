<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$config = app_config();
$baseUrl = app_base_url();
$user = current_user();
$pageTitle = 'PatriotContracts | Government Contract Discovery';
$pdo = db();
$siteName = site_setting($pdo, 'site_name', 'PatriotContracts');
$tagline = site_setting($pdo, 'tagline', 'Federal contract discovery and monitoring');
$heroTitle = site_setting($pdo, 'homepage_hero_title', 'Find government contract opportunities faster.');
$heroSubtitle = site_setting($pdo, 'homepage_hero_subtitle', 'PatriotContracts helps contractors, analysts, and procurement researchers discover and review U.S. government opportunities in a cleaner, structured interface.');
$coverage = $pdo->query('SELECT
    COUNT(*) AS indexed_records,
    COUNT(DISTINCT cc.agency_id) AS agency_count,
    COUNT(DISTINCT cc.vendor_id) AS vendor_count,
    COUNT(DISTINCT cc.category_id) AS category_count,
    MAX(cc.posted_date) AS latest_posted
    FROM contracts_clean cc
    LEFT JOIN listing_overrides lo ON lo.contract_id = cc.id
    LEFT JOIN grant_overrides go ON go.contract_id = cc.id
    WHERE cc.is_duplicate = 0
      AND COALESCE(lo.is_hidden, go.is_hidden, 0) = 0')->fetch() ?: [];

$pipeline = $pdo->query('SELECT
    SUM(cc.is_biddable_now = 1 AND (cc.response_deadline IS NULL OR cc.response_deadline >= CURDATE()) AND cc.is_awarded = 0) AS open_now,
    SUM(cc.is_upcoming_signal = 1 AND cc.is_awarded = 0) AS early_signals,
    SUM(cc.deadline_soon = 1 AND cc.response_deadline BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)) AS due_soon,
    SUM(cc.is_awarded = 1) AS awarded
    FROM contracts_clean cc
    LEFT JOIN listing_overrides lo ON lo.contract_id = cc.id
    LEFT JOIN grant_overrides go ON go.contract_id = cc.id
    WHERE cc.is_duplicate = 0
      AND COALESCE(lo.is_hidden, go.is_hidden, 0) = 0')->fetch() ?: [];

$indexedRecords = (int) ($coverage['indexed_records'] ?? 0);
$agencyCount = (int) ($coverage['agency_count'] ?? 0);
$vendorCount = (int) ($coverage['vendor_count'] ?? 0);
$categoryCount = (int) ($coverage['category_count'] ?? 0);
$latestPosted = display_field_or_null('posted_date', $coverage['latest_posted'] ?? null);

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo e(page_title($config['app']['name'] ?? 'PatriotContracts')); ?></title>
  <link rel="stylesheet" href="<?php echo e($baseUrl); ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?php echo e($baseUrl); ?>/assets/css/main.css">
</head>
<body>
<header class="site-header">
  <div class="container row">
    <a class="brand" href="<?php echo e(app_url('index.php')); ?>"><?php echo e($siteName); ?></a>
    <nav class="site-nav">
      <a class="nav-link" href="<?php echo e(app_url('home.php')); ?>">Browse Listings</a>
      <a class="nav-link" href="<?php echo e(app_url('search.php')); ?>">Search</a>
      <a class="nav-link" href="<?php echo e(app_url('stats.php')); ?>">Stats</a>
      <a class="nav-link" href="<?php echo e(app_url('pricing.php')); ?>">Pricing</a>
      <?php if ($user): ?>
        <a class="nav-link" href="<?php echo e(app_url('dashboard.php')); ?>">Dashboard</a>
      <?php else: ?>
        <a class="nav-link" href="<?php echo e(app_url('login.php')); ?>">Sign In</a>
        <a class="nav-link" href="<?php echo e(app_url('register.php')); ?>">Create Account</a>
      <?php endif; ?>
    </nav>
  </div>
</header>

<main class="container landing-main">
  <section class="landing-hero card">
    <p class="landing-kicker"><?php echo e($siteName); ?></p>
    <h1><?php echo e($heroTitle); ?></h1>
    <p class="landing-summary"><?php echo e($heroSubtitle); ?></p>
    <div class="landing-cta">
      <a class="btn btn-lg" href="<?php echo e(app_url('home.php')); ?>">Browse Public Listings</a>
      <a class="btn btn-secondary btn-lg" href="<?php echo e(app_url('pricing.php')); ?>">View Membership Plans</a>
    </div>
  </section>

  <section class="landing-section">
    <h2>Coverage Snapshot</h2>
    <div class="grid-3">
      <article class="card">
        <h3><?php echo number_format($indexedRecords); ?></h3>
        <p class="muted">Public records indexed</p>
      </article>
      <article class="card">
        <h3><?php echo number_format($agencyCount); ?></h3>
        <p class="muted">Agencies represented</p>
      </article>
      <article class="card">
        <h3><?php echo number_format($categoryCount); ?></h3>
        <p class="muted">Categories tracked</p>
      </article>
    </div>
    <p class="muted">
      Sources include SAM.gov, USAspending.gov, and Grants.gov public datasets.
      <?php if ($latestPosted !== null): ?>Latest posted record in this index: <?php echo e($latestPosted); ?>.<?php endif; ?>
    </p>
  </section>

  <section class="landing-section">
    <h2>What the platform helps you do</h2>
    <div class="grid-2">
      <article class="card">
        <h3>Track relevant opportunities</h3>
        <p class="muted">Browse open opportunities, upcoming signals, and recent awards in one place.</p>
      </article>
      <article class="card">
        <h3>Search with less noise</h3>
        <p class="muted">Use focused search and filtering to find contracts by agency, vendor, category, and key identifiers.</p>
      </article>
    </div>
  </section>

  <section class="landing-section">
    <h2>Actionable Pipeline Snapshot</h2>
    <div class="grid-2">
      <article class="card">
        <p>Open Now: <strong><?php echo number_format((int) ($pipeline['open_now'] ?? 0)); ?></strong></p>
        <p>Early Signals: <strong><?php echo number_format((int) ($pipeline['early_signals'] ?? 0)); ?></strong></p>
      </article>
      <article class="card">
        <p>Deadline Soon: <strong><?php echo number_format((int) ($pipeline['due_soon'] ?? 0)); ?></strong></p>
        <p>Awarded: <strong><?php echo number_format((int) ($pipeline['awarded'] ?? 0)); ?></strong></p>
      </article>
    </div>
  </section>

  <section class="landing-section">
    <h2>How It Works</h2>
    <div class="grid-3 landing-features">
      <article class="card">
        <h3>1. Ingest</h3>
        <p class="muted">Public procurement records are pulled from federal source datasets.</p>
      </article>
      <article class="card">
        <h3>2. Normalize</h3>
        <p class="muted">Fields are cleaned so agencies, dates, and descriptions are easier to review.</p>
      </article>
      <article class="card">
        <h3>3. Triage</h3>
        <p class="muted">Browse, search, and sort records by open opportunities, signals, deadlines, and awards.</p>
      </article>
    </div>
  </section>

  <section class="landing-section">
    <h2>Key features</h2>
    <div class="grid-3 landing-features">
      <article class="card">
        <h3>Contract Opportunity Browsing</h3>
        <p class="muted">Review listings in a structured feed built for quick triage.</p>
      </article>
      <article class="card">
        <h3>Search and Filtering</h3>
        <p class="muted">Narrow results by terms, organizations, and procurement metadata.</p>
      </article>
      <article class="card">
        <h3>Streamlined Listing Details</h3>
        <p class="muted">Open listing pages with normalized fields, timeline context, and summary data.</p>
      </article>
      <article class="card">
        <h3>Centralized Data Access</h3>
        <p class="muted">View agencies, vendors, categories, and contract records in one system.</p>
      </article>
      <article class="card">
        <h3>Member Subscription Tools</h3>
        <p class="muted">Unlock paid account features for advanced workflows and access controls.</p>
      </article>
      <article class="card">
        <h3>Built for Practical Use</h3>
        <p class="muted">Designed for teams that need clear procurement intelligence without unnecessary complexity.</p>
      </article>
    </div>
  </section>
</main>

<footer class="site-footer">
  <div class="container">
    <small><?php echo e($tagline); ?></small>
  </div>
</footer>
<script src="<?php echo e(app_url('assets/js/app.js')); ?>"></script>
</body>
</html>
