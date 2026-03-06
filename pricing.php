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

$pageTitle = 'PatriotContracts | Pricing';
include __DIR__ . '/templates/header.php';
?>
<h1>Pricing</h1>
<p class="muted">Free browsing is available without account login. Member tools require a paid plan.</p>
<div class="grid-3">
<?php foreach ($plans as $plan): ?>
  <?php
  $flags = json_decode((string) ($plan['feature_flags_json'] ?? '{}'), true);
  if (!is_array($flags)) {
      $flags = [];
  }
  ?>
  <section class="card">
    <h2><?php echo e((string) $plan['name']); ?></h2>
    <p><strong>$<?php echo number_format((float) $plan['price_monthly'], 2); ?>/month</strong></p>
    <p>Code: <?php echo e((string) $plan['plan_code']); ?></p>
    <p><?php echo !empty($flags['member_tools']) ? 'Member tools included' : 'Member feature'; ?></p>
    <p><?php echo !empty($flags['advanced_search']) ? 'Advanced search included' : 'Advanced search: Pro feature'; ?></p>
    <p><?php echo !empty($flags['saved_searches']) ? 'Saved searches included' : 'Saved searches: Pro feature'; ?></p>
    <p><?php echo !empty($flags['alerts']) ? 'Alerts included' : 'Alerts: Pro feature'; ?></p>
    <p><?php echo !empty($flags['csv_export']) ? 'CSV export included' : 'Upgrade to export CSV'; ?></p>
    <p><?php echo !empty($flags['api_access']) ? 'API access included' : 'API plan required'; ?></p>

    <?php if ($user): ?>
      <form method="post" action="subscribe.php">
        <input type="hidden" name="_csrf" value="<?php echo e(csrf_token()); ?>">
        <input type="hidden" name="plan" value="<?php echo e((string) $plan['plan_code']); ?>">
        <button class="btn" type="submit">Subscribe</button>
      </form>
    <?php else: ?>
      <p><a class="btn" href="register.php?plan=<?php echo e((string) $plan['plan_code']); ?>">Choose Plan</a></p>
    <?php endif; ?>
  </section>
<?php endforeach; ?>
</div>
<?php include __DIR__ . '/templates/footer.php'; ?>
