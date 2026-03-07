<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$config = app_config();
$baseUrl = rtrim((string) ($config['app']['base_url'] ?? ''), '/');
$user = current_user();
$pageTitle = 'PatriotContracts | Government Contract Discovery';
$pdo = db();
$siteName = site_setting($pdo, 'site_name', 'PatriotContracts');
$tagline = site_setting($pdo, 'tagline', 'Federal contract discovery and monitoring');
$heroTitle = site_setting($pdo, 'homepage_hero_title', 'Find government contract opportunities faster.');
$heroSubtitle = site_setting($pdo, 'homepage_hero_subtitle', 'PatriotContracts helps contractors, analysts, and procurement researchers discover and review U.S. government opportunities in a cleaner, structured interface.');
$pricingCtaText = site_setting($pdo, 'pricing_cta_text', 'Buy Subscription');

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
    <a class="brand" href="<?php echo e($baseUrl); ?>/index.php"><?php echo e($siteName); ?></a>
    <nav class="site-nav">
      <a class="nav-link" href="<?php echo e($baseUrl); ?>/home.php">Browse Listings</a>
      <a class="nav-link" href="<?php echo e($baseUrl); ?>/search.php">Search</a>
      <a class="nav-link" href="<?php echo e($baseUrl); ?>/pricing.php">Pricing</a>
      <?php if ($user): ?>
        <a class="nav-link" href="<?php echo e($baseUrl); ?>/dashboard.php">Dashboard</a>
      <?php else: ?>
        <a class="nav-link" href="<?php echo e($baseUrl); ?>/login.php">Login</a>
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
      <a class="btn btn-lg" href="<?php echo e($baseUrl); ?>/home.php">Browse Listings</a>
      <a class="btn btn-secondary btn-lg" href="<?php echo e($baseUrl); ?>/pricing.php"><?php echo e($pricingCtaText); ?></a>
    </div>
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
<script src="<?php echo e($baseUrl); ?>/assets/js/app.js"></script>
</body>
</html>
