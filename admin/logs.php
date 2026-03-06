<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
$user = current_user();
if (!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    die('Admin access required');
}

$logs = db()->query('SELECT * FROM ingest_logs ORDER BY id DESC LIMIT 200')->fetchAll();
$backlog = (int) db()->query('SELECT COUNT(*) FROM contracts_raw WHERE processed = 0')->fetchColumn();
include __DIR__ . '/../templates/header.php';
?>
<h1>Ingest Logs</h1>
<p class="muted">Current unprocessed raw backlog: <?php echo number_format($backlog); ?></p>
<table class="table">
  <thead><tr><th>ID</th><th>Script</th><th>Status</th><th>Fetched</th><th>Processed</th><th>Started</th><th>Message</th></tr></thead>
  <tbody>
  <?php foreach ($logs as $log): ?>
    <tr>
      <td><?php echo (int) $log['id']; ?></td>
      <td><?php echo e($log['script_name']); ?></td>
      <td><?php echo e($log['status']); ?></td>
      <td><?php echo (int) $log['records_fetched']; ?></td>
      <td><?php echo (int) $log['records_processed']; ?></td>
      <td><?php echo e($log['started_at']); ?></td>
      <td><?php echo e((string) $log['message']); ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php include __DIR__ . '/../templates/footer.php'; ?>
