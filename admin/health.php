<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';

$adminUser = require_admin_auth(['admin', 'super_admin']);
$pdo = db();
$sources = $pdo->query('SELECT id, name, slug FROM sources ORDER BY name')->fetchAll();
$unprocessedRaw = (int) $pdo->query('SELECT COUNT(*) FROM contracts_raw WHERE processed = 0')->fetchColumn();
$duplicateCount = (int) $pdo->query('SELECT COUNT(*) FROM contracts_clean WHERE is_duplicate = 1')->fetchColumn();
$usersCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$activeMembers = (int) $pdo->query('SELECT COUNT(DISTINCT user_id) FROM subscriptions WHERE status IN ("active","trialing")')->fetchColumn();
$pendingEmailVerifications = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE email_verified = 0 AND provider = "email"')->fetchColumn();
$apiUsage24h = (int) $pdo->query('SELECT COUNT(*) FROM api_usage WHERE requested_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)')->fetchColumn();
$recentApiKeys = $pdo->query('SELECT api_key_prefix, status, created_at FROM api_keys ORDER BY id DESC LIMIT 10')->fetchAll();
$webhookIssues = $pdo->query('SELECT event_type, created_at FROM webhook_events WHERE processed_at IS NULL ORDER BY id DESC LIMIT 20')->fetchAll();

$adminTitle = 'Health';
include __DIR__ . '/includes/admin_header.php';
?>
<h1>Ingest Health</h1>
<section class="card">
  <h2>Pipeline Order</h2>
  <ol>
    <li>Ingest sources</li>
    <li>Normalize</li>
    <li>Recategorize</li>
    <li>Calculate stats</li>
    <li>Run alerts</li>
  </ol>
  <p>Unprocessed raw backlog: <?php echo number_format($unprocessedRaw); ?></p>
  <p>Duplicate-marked records: <?php echo number_format($duplicateCount); ?></p>
</section>

<section class="card">
  <h2>Membership / API Snapshot</h2>
  <p>Users: <?php echo number_format($usersCount); ?></p>
  <p>Active memberships: <?php echo number_format($activeMembers); ?></p>
  <p>Pending email verifications: <?php echo number_format($pendingEmailVerifications); ?></p>
  <p>API requests (24h): <?php echo number_format($apiUsage24h); ?></p>
</section>

<section class="card">
  <h2>Recent API Keys</h2>
  <?php foreach ($recentApiKeys as $k): ?>
    <p><code><?php echo e((string) $k['api_key_prefix']); ?>...</code> | <?php echo e((string) $k['status']); ?> | <?php echo e((string) $k['created_at']); ?></p>
  <?php endforeach; ?>
</section>

<section class="card">
  <h2>Webhook Issues</h2>
  <?php if (!$webhookIssues): ?>
    <p>No unprocessed webhook events.</p>
  <?php else: ?>
    <?php foreach ($webhookIssues as $w): ?>
      <p><?php echo e((string) $w['event_type']); ?> at <?php echo e((string) $w['created_at']); ?></p>
    <?php endforeach; ?>
  <?php endif; ?>
</section>

<section class="card admin-table-wrap">
  <h2>Source Status</h2>
  <table class="admin-table">
    <thead><tr><th>Source</th><th>Last Success</th><th>Last Failure</th><th>Last Run Records</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($sources as $source): ?>
      <?php
      $successStmt = $pdo->prepare('SELECT script_name, finished_at, records_fetched, records_processed, message FROM ingest_logs WHERE source_id = :sid AND status = "success" ORDER BY id DESC LIMIT 1');
      $successStmt->execute(['sid' => (int) $source['id']]);
      $success = $successStmt->fetch();

      $errorStmt = $pdo->prepare('SELECT script_name, finished_at, message FROM ingest_logs WHERE source_id = :sid AND status = "error" ORDER BY id DESC LIMIT 1');
      $errorStmt->execute(['sid' => (int) $source['id']]);
      $error = $errorStmt->fetch();

      $status = $success ? 'healthy' : 'unknown';
      if ($error && (!$success || strtotime((string) $error['finished_at']) > strtotime((string) $success['finished_at']))) {
          $status = 'error';
      }
      ?>
      <tr>
        <td><?php echo e((string) $source['name']); ?></td>
        <td><?php echo e((string) ($success['finished_at'] ?? '-')); ?></td>
        <td><?php echo e((string) ($error['finished_at'] ?? '-')); ?></td>
        <td>
          <?php if ($success): ?>
            fetched=<?php echo (int) $success['records_fetched']; ?> processed=<?php echo (int) $success['records_processed']; ?>
          <?php else: ?>
            -
          <?php endif; ?>
        </td>
        <td><span class="<?php echo e(admin_status_badge_class($status)); ?>"><?php echo e($status); ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php include __DIR__ . '/includes/admin_footer.php'; ?>