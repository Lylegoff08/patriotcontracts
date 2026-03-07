<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';

$adminUser = require_admin_auth(['admin', 'super_admin']);
$pdo = db();

$page = max(1, request_int('page', 1));
[$page, $perPage, $offset] = paginate($page, 100);

$count = (int) $pdo->query('SELECT COUNT(*) FROM admin_activity_log')->fetchColumn();
$totalPages = max(1, (int) ceil($count / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    [, , $offset] = paginate($page, $perPage);
}

$stmt = $pdo->prepare('SELECT l.*, u.email AS admin_email
    FROM admin_activity_log l
    JOIN users u ON u.id = l.admin_user_id
    ORDER BY l.id DESC
    LIMIT :limit OFFSET :offset');
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$adminTitle = 'Activity Log';
include __DIR__ . '/includes/admin_header.php';
?>
<h1>Admin Activity Log</h1>
<section class="card admin-table-wrap">
  <table class="admin-table">
    <thead>
      <tr>
        <th>ID</th>
        <th>When</th>
        <th>Admin</th>
        <th>Action</th>
        <th>Entity</th>
        <th>Details</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $row): ?>
        <tr>
          <td><?php echo (int) $row['id']; ?></td>
          <td><?php echo e((string) $row['created_at']); ?></td>
          <td><?php echo e((string) $row['admin_email']); ?> (#<?php echo (int) $row['admin_user_id']; ?>)</td>
          <td><?php echo e((string) $row['action']); ?></td>
          <td><?php echo e((string) $row['entity_type']); ?><?php if (!empty($row['entity_id'])): ?> #<?php echo (int) $row['entity_id']; ?><?php endif; ?></td>
          <td><code><?php echo e((string) ($row['details_json'] ?? '')); ?></code></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <div class="admin-actions" style="margin-top:0.8rem;">
    <?php if ($page > 1): ?><a class="btn btn-secondary" href="?page=<?php echo (int) ($page - 1); ?>">Previous</a><?php endif; ?>
    <?php if ($page < $totalPages): ?><a class="btn btn-secondary" href="?page=<?php echo (int) ($page + 1); ?>">Next</a><?php endif; ?>
  </div>
</section>
<?php include __DIR__ . '/includes/admin_footer.php'; ?>