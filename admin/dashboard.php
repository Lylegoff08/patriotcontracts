<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';

$adminUser = require_admin_auth();
$pdo = db();

function safe_count(PDO $pdo, string $sql): int
{
    try {
        return (int) $pdo->query($sql)->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function safe_row(PDO $pdo, string $sql): ?array
{
    try {
        $row = $pdo->query($sql)->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

$totalListings = safe_count($pdo, "SELECT COUNT(*) FROM contracts_clean WHERE is_duplicate = 0 AND source_type <> 'grants'");
$totalGrants = safe_count($pdo, "SELECT COUNT(*) FROM contracts_clean WHERE is_duplicate = 0 AND source_type = 'grants'");
$hiddenListings = safe_count($pdo, 'SELECT COUNT(*) FROM listing_overrides WHERE is_hidden = 1');
$featuredListings = safe_count($pdo, 'SELECT COUNT(*) FROM listing_overrides WHERE is_featured = 1');
$missingImportant = safe_count($pdo, 'SELECT COUNT(*) FROM contracts_clean
    WHERE is_duplicate = 0
      AND (
        title IS NULL OR title = ""
        OR agency_id IS NULL
        OR source_url IS NULL OR source_url = ""
      )');
$totalUsers = safe_count($pdo, 'SELECT COUNT(*) FROM users');
$activeSubscribers = safe_count($pdo, 'SELECT COUNT(DISTINCT user_id) FROM subscriptions WHERE status IN ("active", "trialing")');
$backlogRaw = safe_count($pdo, 'SELECT COUNT(*) FROM contracts_raw WHERE processed = 0');
$backlogDuplicates = safe_count($pdo, 'SELECT COUNT(*) FROM contracts_clean WHERE is_duplicate = 1');
$latestIngest = safe_row($pdo, 'SELECT script_name, status, records_fetched, records_processed, finished_at FROM ingest_logs ORDER BY id DESC LIMIT 1');

$adminTitle = 'Dashboard';
include __DIR__ . '/includes/admin_header.php';
?>
<h1>Admin Dashboard</h1>
<div class="admin-grid-cards">
  <div class="admin-card"><h3><?php echo number_format($totalListings); ?></h3><p>Total Listings</p></div>
  <div class="admin-card"><h3><?php echo number_format($totalGrants); ?></h3><p>Total Grants</p></div>
  <div class="admin-card"><h3><?php echo number_format($hiddenListings); ?></h3><p>Hidden Listings</p></div>
  <div class="admin-card"><h3><?php echo number_format($featuredListings); ?></h3><p>Featured Listings</p></div>
  <div class="admin-card"><h3><?php echo number_format($missingImportant); ?></h3><p>Missing Important Fields</p></div>
  <div class="admin-card"><h3><?php echo number_format($totalUsers); ?></h3><p>Total Users</p></div>
  <div class="admin-card"><h3><?php echo number_format($activeSubscribers); ?></h3><p>Active Subscribers</p></div>
  <div class="admin-card"><h3><?php echo number_format($backlogRaw); ?></h3><p>Raw Backlog</p></div>
  <div class="admin-card"><h3><?php echo number_format($backlogDuplicates); ?></h3><p>Duplicate Marked</p></div>
</div>

<section class="card">
  <h2>Latest Ingestion Status</h2>
  <?php if ($latestIngest): ?>
    <p>
      <strong><?php echo e((string) $latestIngest['script_name']); ?></strong>
      <span class="<?php echo e(admin_status_badge_class((string) $latestIngest['status'])); ?>"><?php echo e((string) $latestIngest['status']); ?></span>
      fetched=<?php echo (int) $latestIngest['records_fetched']; ?> processed=<?php echo (int) $latestIngest['records_processed']; ?>
      finished=<?php echo e((string) $latestIngest['finished_at']); ?>
    </p>
  <?php else: ?>
    <p>No ingest status available.</p>
  <?php endif; ?>
</section>

<section class="card">
  <h2>Quick Links</h2>
  <div class="admin-actions">
    <a class="btn" href="listings.php">Listings</a>
    <a class="btn" href="grants.php">Grants</a>
    <a class="btn" href="pages.php">Pages</a>
    <a class="btn" href="settings.php">Settings</a>
    <a class="btn" href="users.php">Users</a>
    <a class="btn" href="health.php">Health</a>
    <a class="btn" href="logs.php">Logs</a>
    <a class="btn" href="run_ingest.php">Run Ingest</a>
  </div>
</section>
<?php include __DIR__ . '/includes/admin_footer.php'; ?>
