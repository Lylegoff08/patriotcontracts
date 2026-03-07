<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/membership.php';
$pdo = db();
$viewer = current_user();
$hasMemberStats = false;
if ($viewer && (string) ($viewer['account_status'] ?? '') === 'active') {
    $hasMemberStats = user_has_feature($pdo, (int) $viewer['id'], 'member_tools');
}

$coverage = $pdo->query('SELECT
  COUNT(*) AS indexed_records,
  COUNT(DISTINCT agency_id) AS agencies,
  COUNT(DISTINCT vendor_id) AS vendors,
  COUNT(DISTINCT category_id) AS categories,
  MAX(posted_date) AS latest_posted
  FROM contracts_clean
  WHERE is_duplicate = 0')->fetch() ?: [];

$categoryStats = $pdo->query('SELECT cat.name, COUNT(*) AS total, COALESCE(SUM(cc.award_amount),0) AS amount, COALESCE(AVG(NULLIF(cc.award_amount,0)),0) AS avg_amount
  FROM contracts_clean cc JOIN contract_categories cat ON cat.id = cc.category_id
  WHERE cc.is_duplicate = 0 GROUP BY cat.id ORDER BY total DESC')->fetchAll();

$agencyByCount = $pdo->query('SELECT a.name, COUNT(*) AS total, COALESCE(SUM(cc.award_amount),0) AS amount
  FROM contracts_clean cc JOIN agencies a ON a.id = cc.agency_id
  WHERE cc.is_duplicate = 0 GROUP BY a.id ORDER BY total DESC LIMIT 25')->fetchAll();

$agencyByValue = $pdo->query('SELECT a.name, COUNT(*) AS total, COALESCE(SUM(cc.award_amount),0) AS amount
  FROM contracts_clean cc JOIN agencies a ON a.id = cc.agency_id
  WHERE cc.is_duplicate = 0 GROUP BY a.id ORDER BY amount DESC LIMIT 25')->fetchAll();

$agencyRecent = $pdo->query('SELECT a.name, COUNT(*) AS total, COALESCE(SUM(cc.award_amount),0) AS amount
  FROM contracts_clean cc JOIN agencies a ON a.id = cc.agency_id
  WHERE cc.is_duplicate = 0 AND cc.posted_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
  GROUP BY a.id ORDER BY total DESC LIMIT 25')->fetchAll();

$locationStats = $pdo->query('SELECT place_state, COUNT(*) AS total, COALESCE(SUM(award_amount),0) AS amount
  FROM contracts_clean WHERE is_duplicate = 0 AND place_state IS NOT NULL AND place_state <> ""
  GROUP BY place_state ORDER BY total DESC LIMIT 25')->fetchAll();

$pipeline = $pdo->query('SELECT
  SUM(is_biddable_now = 1 AND (response_deadline IS NULL OR response_deadline >= CURDATE()) AND is_awarded = 0 AND status NOT IN ("closed", "archived", "expired", "cancelled")) AS open_now,
  SUM(is_upcoming_signal = 1 AND is_awarded = 0) AS early_signals,
  SUM(is_awarded = 1) AS recent_awards,
  SUM(deadline_soon = 1 AND response_deadline BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)) AS due_soon
  FROM contracts_clean WHERE is_duplicate = 0')->fetch();

$setAside = $pdo->query('SELECT
  SUM(LOWER(CONCAT(COALESCE(set_aside_code,"")," ",COALESCE(set_aside_label,""))) LIKE "%small%") AS small_business,
  SUM(LOWER(CONCAT(COALESCE(set_aside_code,"")," ",COALESCE(set_aside_label,""))) LIKE "%8(a)%") AS a8,
  SUM(LOWER(CONCAT(COALESCE(set_aside_code,"")," ",COALESCE(set_aside_label,""))) LIKE "%veteran%") AS veteran,
  SUM(LOWER(CONCAT(COALESCE(set_aside_code,"")," ",COALESCE(set_aside_label,""))) LIKE "%women%") AS women
  FROM contracts_clean WHERE is_duplicate = 0')->fetch();

$valueDist = $pdo->query('SELECT
  SUM(COALESCE(value_max, award_amount, 0) < 100000) AS under_100k,
  SUM(COALESCE(value_max, award_amount, 0) >= 100000 AND COALESCE(value_max, award_amount, 0) < 1000000) AS from_100k_to_1m,
  SUM(COALESCE(value_max, award_amount, 0) >= 1000000 AND COALESCE(value_max, award_amount, 0) < 10000000) AS from_1m_to_10m,
  SUM(COALESCE(value_max, award_amount, 0) >= 10000000) AS over_10m
  FROM contracts_clean WHERE is_duplicate = 0')->fetch();

$timeWindows = $pdo->query('SELECT
  SUM(posted_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)) AS last_7_days,
  SUM(posted_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) AS last_30_days
  FROM contracts_clean WHERE is_duplicate = 0')->fetch();

include __DIR__ . '/templates/header.php';
?>
<h1>Stats</h1>
<p class="muted">Public metrics are derived from indexed procurement records and refreshed through ingestion cycles.</p>
<div class="grid-2">
  <section class="card">
    <h2>Coverage Snapshot</h2>
    <p>Indexed records: <strong><?php echo number_format((int) ($coverage['indexed_records'] ?? 0)); ?></strong></p>
    <p>Agencies: <strong><?php echo number_format((int) ($coverage['agencies'] ?? 0)); ?></strong></p>
    <p>Vendors: <strong><?php echo number_format((int) ($coverage['vendors'] ?? 0)); ?></strong></p>
    <p>Categories: <strong><?php echo number_format((int) ($coverage['categories'] ?? 0)); ?></strong></p>
    <?php if (($latest = display_field_or_null('posted_date', $coverage['latest_posted'] ?? null)) !== null): ?>
      <p>Latest posted record date: <strong><?php echo e($latest); ?></strong></p>
    <?php endif; ?>
  </section>
  <section class="card">
    <h2>Public Pipeline Snapshot</h2>
    <p>Open opportunities: <?php echo number_format((int) ($pipeline['open_now'] ?? 0)); ?></p>
    <p>Early signals: <?php echo number_format((int) ($pipeline['early_signals'] ?? 0)); ?></p>
    <p>Recent awards: <?php echo number_format((int) ($pipeline['recent_awards'] ?? 0)); ?></p>
    <p>Due soon: <?php echo number_format((int) ($pipeline['due_soon'] ?? 0)); ?></p>
    <p class="muted">Source families: SAM.gov, USAspending.gov, Grants.gov public records.</p>
  </section>
</div>
<?php if (!$hasMemberStats): ?>
  <section class="card">
    <h2>Member Analytics Preview</h2>
    <p class="muted">Free users can see coverage and pipeline snapshots. Member plans unlock full agency, category, location, and value analytics tables.</p>
    <p><a class="btn" href="pricing.php">View Membership Plans</a></p>
  </section>
<?php endif; ?>
<?php if ($hasMemberStats): ?>
<div class="grid-2">
  <section class="card">
    <h2>Category Stats</h2>
    <table class="table"><thead><tr><th>Category</th><th>Contracts</th><th>Total Value</th><th>Avg Value</th></tr></thead><tbody>
      <?php foreach ($categoryStats as $row): ?>
      <tr><td><?php echo e($row['name']); ?></td><td><?php echo (int) $row['total']; ?></td><td>$<?php echo number_format((float) $row['amount'], 2); ?></td><td>$<?php echo number_format((float) $row['avg_amount'], 2); ?></td></tr>
      <?php endforeach; ?>
    </tbody></table>
  </section>
  <section class="card">
    <h2>Top Agencies by Count</h2>
    <table class="table"><thead><tr><th>Agency</th><th>Contracts</th><th>Amount</th></tr></thead><tbody>
      <?php foreach ($agencyByCount as $row): ?>
      <tr><td><?php echo e(display_field_value('agency', $row['name'] ?? null)); ?></td><td><?php echo (int) $row['total']; ?></td><td>$<?php echo number_format((float) $row['amount'], 2); ?></td></tr>
      <?php endforeach; ?>
    </tbody></table>
  </section>
</div>
<div class="grid-2">
  <section class="card">
    <h2>Top Agencies by Value</h2>
    <table class="table"><thead><tr><th>Agency</th><th>Contracts</th><th>Amount</th></tr></thead><tbody>
      <?php foreach ($agencyByValue as $row): ?>
      <tr><td><?php echo e(display_field_value('agency', $row['name'] ?? null)); ?></td><td><?php echo (int) $row['total']; ?></td><td>$<?php echo number_format((float) $row['amount'], 2); ?></td></tr>
      <?php endforeach; ?>
    </tbody></table>
  </section>
  <section class="card">
    <h2>Top Agencies by Recent Activity (30 Days)</h2>
    <table class="table"><thead><tr><th>Agency</th><th>Recent Contracts</th><th>Amount</th></tr></thead><tbody>
      <?php foreach ($agencyRecent as $row): ?>
      <tr><td><?php echo e(display_field_value('agency', $row['name'] ?? null)); ?></td><td><?php echo (int) $row['total']; ?></td><td>$<?php echo number_format((float) $row['amount'], 2); ?></td></tr>
      <?php endforeach; ?>
    </tbody></table>
  </section>
</div>
<div class="grid-2">
  <section class="card">
    <h2>Location Stats (State)</h2>
    <table class="table"><thead><tr><th>State</th><th>Contracts</th><th>Total Value</th></tr></thead><tbody>
      <?php foreach ($locationStats as $row): ?>
      <tr><td><?php echo e((string) $row['place_state']); ?></td><td><?php echo (int) $row['total']; ?></td><td>$<?php echo number_format((float) $row['amount'], 2); ?></td></tr>
      <?php endforeach; ?>
    </tbody></table>
  </section>
  <section class="card">
    <h2>Pipeline Stats</h2>
    <p>Open opportunities: <?php echo number_format((int) ($pipeline['open_now'] ?? 0)); ?></p>
    <p>Early signals: <?php echo number_format((int) ($pipeline['early_signals'] ?? 0)); ?></p>
    <p>Recent awards: <?php echo number_format((int) ($pipeline['recent_awards'] ?? 0)); ?></p>
    <p>Due soon: <?php echo number_format((int) ($pipeline['due_soon'] ?? 0)); ?></p>
    <h3>Set-Aside Stats</h3>
    <p>Small business: <?php echo number_format((int) ($setAside['small_business'] ?? 0)); ?></p>
    <p>8(a): <?php echo number_format((int) ($setAside['a8'] ?? 0)); ?></p>
    <p>Veteran-owned: <?php echo number_format((int) ($setAside['veteran'] ?? 0)); ?></p>
    <p>Women-owned: <?php echo number_format((int) ($setAside['women'] ?? 0)); ?></p>
    <h3>Time Windows</h3>
    <p>Last 7 days: <?php echo number_format((int) ($timeWindows['last_7_days'] ?? 0)); ?></p>
    <p>Last 30 days: <?php echo number_format((int) ($timeWindows['last_30_days'] ?? 0)); ?></p>
    <h3>Value Distribution</h3>
    <p>Under $100k: <?php echo number_format((int) ($valueDist['under_100k'] ?? 0)); ?></p>
    <p>$100k to $1M: <?php echo number_format((int) ($valueDist['from_100k_to_1m'] ?? 0)); ?></p>
    <p>$1M to $10M: <?php echo number_format((int) ($valueDist['from_1m_to_10m'] ?? 0)); ?></p>
    <p>Over $10M: <?php echo number_format((int) ($valueDist['over_10m'] ?? 0)); ?></p>
  </section>
</div>
<?php else: ?>
<div class="grid-2">
  <section class="card">
    <h2>Pipeline Snapshot</h2>
    <p>Open opportunities: <?php echo number_format((int) ($pipeline['open_now'] ?? 0)); ?></p>
    <p>Early signals: <?php echo number_format((int) ($pipeline['early_signals'] ?? 0)); ?></p>
    <p>Recent awards: <?php echo number_format((int) ($pipeline['recent_awards'] ?? 0)); ?></p>
    <p>Due soon: <?php echo number_format((int) ($pipeline['due_soon'] ?? 0)); ?></p>
  </section>
  <section class="card">
    <h2>Recent Activity Windows</h2>
    <p>Last 7 days: <?php echo number_format((int) ($timeWindows['last_7_days'] ?? 0)); ?></p>
    <p>Last 30 days: <?php echo number_format((int) ($timeWindows['last_30_days'] ?? 0)); ?></p>
    <p class="muted">Member feature: full category, agency, location, and value analytics.</p>
  </section>
</div>
<div class="grid-2">
  <section class="card">
    <h2>Top Categories (Public Preview)</h2>
    <?php foreach (array_slice($categoryStats, 0, 5) as $row): ?>
      <p><?php echo e((string) $row['name']); ?>: <?php echo number_format((int) $row['total']); ?></p>
    <?php endforeach; ?>
  </section>
  <section class="card">
    <h2>Top Agencies (Public Preview)</h2>
    <?php foreach (array_slice($agencyByCount, 0, 5) as $row): ?>
      <p><?php echo e(display_field_value('agency', $row['name'] ?? null)); ?>: <?php echo number_format((int) $row['total']); ?></p>
    <?php endforeach; ?>
    <p class="muted">Sign in with a member plan to view full ranked tables and value breakdowns.</p>
  </section>
</div>
<?php endif; ?>
<?php include __DIR__ . '/templates/footer.php'; ?>
