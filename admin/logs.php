<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';

$adminUser = require_admin_auth(['admin', 'super_admin']);
$logs = db()->query('SELECT * FROM ingest_logs ORDER BY id DESC LIMIT 200')->fetchAll();
$backlog = (int) db()->query('SELECT COUNT(*) FROM contracts_raw WHERE processed = 0')->fetchColumn();

$adminTitle = 'Logs';
include __DIR__ . '/includes/admin_header.php';
?>
<h1>Ingest Logs</h1>
<p class="muted">Current unprocessed raw backlog: <?php echo number_format($backlog); ?></p>
<section class="card admin-table-wrap">
  <table class="admin-table">
    <thead><tr><th>ID</th><th>Script</th><th>Status</th><th>Fetched</th><th>Processed</th><th>Started</th><th>Message</th></tr></thead>
    <tbody>
    <?php foreach ($logs as $log): ?>
      <tr>
        <td><?php echo (int) $log['id']; ?></td>
        <td><?php echo e((string) $log['script_name']); ?></td>
        <td><span class="<?php echo e(admin_status_badge_class((string) $log['status'])); ?>"><?php echo e((string) $log['status']); ?></span></td>
        <td><?php echo (int) $log['records_fetched']; ?></td>
        <td><?php echo (int) $log['records_processed']; ?></td>
        <td><?php echo e((string) $log['started_at']); ?></td>
        <td><?php echo e((string) $log['message']); ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php include __DIR__ . '/includes/admin_footer.php'; ?>