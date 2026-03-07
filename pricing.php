<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/security.php';

$user = current_user();
$pdo = db();
$plans = $pdo->query('SELECT plan_code, COALESCE(name, plan_name) AS name, price_monthly, feature_flags_json, stripe_price_id, is_active
    FROM subscription_plans
    WHERE is_active = 1 AND plan_code IN ("MEMBER_BASIC", "MEMBER_PRO", "API_MEMBER")
    ORDER BY FIELD(plan_code, "MEMBER_BASIC", "MEMBER_PRO", "API_MEMBER")')->fetchAll();
$coverage = $pdo->query('SELECT
    COUNT(*) AS indexed_records,
    COUNT(DISTINCT agency_id) AS agency_count,
    COUNT(DISTINCT category_id) AS category_count
    FROM contracts_clean
    WHERE is_duplicate = 0')->fetch() ?: [];

$pageTitle = 'PatriotContracts | Pricing';
include __DIR__ . '/templates/header.php';
?>
<h1>Pricing</h1>
<p class="muted">Public browsing remains free. Paid plans unlock deeper workflow tools and account-level features.</p>
<section class="card">
  <h2>Public Access (No Account Required)</h2>
  <p class="muted">
    Browse listings, run basic search, and review public contract detail pages.
    Current public index: <?php echo number_format((int) ($coverage['indexed_records'] ?? 0)); ?> records across
    <?php echo number_format((int) ($coverage['agency_count'] ?? 0)); ?> agencies and
    <?php echo number_format((int) ($coverage['category_count'] ?? 0)); ?> categories.
  </p>
  <p><a class="btn btn-secondary" href="<?php echo e(app_url('home.php')); ?>">Browse Public Listings</a></p>
</section>
<div class="grid-3">
<?php foreach ($plans as $plan): ?>
  <?php
  $flags = json_decode((string) ($plan['feature_flags_json'] ?? '{}'), true);
  if (!is_array($flags)) {
      $flags = [];
  }
  $planCode = (string) ($plan['plan_code'] ?? '');
  $ctaLabel = 'Start Membership';
  if ($planCode === 'API_MEMBER') {
      $ctaLabel = 'Start API Membership';
  } elseif ($planCode === 'MEMBER_PRO') {
      $ctaLabel = 'Start Pro Membership';
  } elseif ($planCode === 'MEMBER_BASIC') {
      $ctaLabel = 'Start Basic Membership';
  }
  ?>
  <section class="card">
    <h2><?php echo e((string) $plan['name']); ?></h2>
    <p><strong>$<?php echo number_format((float) $plan['price_monthly'], 2); ?>/month</strong></p>
    <p><?php echo !empty($flags['member_tools']) ? 'Analytics dashboards and member tables included' : 'No member analytics tables in this plan'; ?></p>
    <p><?php echo !empty($flags['advanced_search']) ? 'Advanced search filters included' : 'Advanced search filters not included'; ?></p>
    <p><?php echo !empty($flags['saved_searches']) ? 'Saved searches included' : 'Saved searches not included'; ?></p>
    <p><?php echo !empty($flags['alerts']) ? 'Alerting features included' : 'Alerting features not included'; ?></p>
    <p><?php echo !empty($flags['csv_export']) ? 'CSV export included' : 'CSV export not included'; ?></p>
    <p><?php echo !empty($flags['api_access']) ? 'API access included' : 'API access not included'; ?></p>

    <?php if ($user): ?>
      <form method="post" action="subscribe.php">
        <input type="hidden" name="_csrf" value="<?php echo e(csrf_token()); ?>">
        <input type="hidden" name="plan" value="<?php echo e((string) $plan['plan_code']); ?>">
        <button class="btn" type="submit"><?php echo e($ctaLabel); ?></button>
      </form>
    <?php else: ?>
      <p><a class="btn" href="register.php?plan=<?php echo e((string) $plan['plan_code']); ?>"><?php echo e($ctaLabel); ?></a></p>
    <?php endif; ?>
  </section>
<?php endforeach; ?>
</div>
<section class="card">
  <h2>Data Source References</h2>
  <p class="muted">Public records are sourced from SAM.gov, USAspending.gov, and Grants.gov datasets and then normalized for cleaner review.</p>
  <p class="muted">Member features expand workflow access; they do not replace official source records.</p>
</section>
<?php include __DIR__ . '/templates/footer.php'; ?>
