<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';

$adminUser = require_admin_auth(['admin', 'super_admin']);
$pdo = db();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_post();
    $action = request_str('action');
    $userId = request_int('user_id', 0);

    if ($userId > 0) {
        try {
            if ($action === 'toggle_active') {
                if ($userId === (int) $adminUser['id']) {
                    throw new RuntimeException('Cannot deactivate yourself.');
                }
                $stmt = $pdo->prepare('UPDATE users SET is_active = IF(is_active = 1, 0, 1), updated_at = NOW() WHERE id = :id');
                $stmt->execute(['id' => $userId]);
                admin_log_activity($pdo, (int) $adminUser['id'], 'user_status_toggled', 'user', $userId, []);
                $message = 'User status updated.';
            } elseif ($action === 'change_admin_role') {
                if (!admin_can_manage_roles($adminUser)) {
                    throw new RuntimeException('Only super admin can change admin roles.');
                }
                $newRole = strtolower(trim(request_str('admin_role')));
                if (!in_array($newRole, ['', 'editor', 'admin', 'super_admin'], true)) {
                    throw new RuntimeException('Invalid admin role.');
                }
                $stmt = $pdo->prepare('UPDATE users SET admin_role = :admin_role, updated_at = NOW() WHERE id = :id');
                $stmt->execute([
                    'admin_role' => $newRole !== '' ? $newRole : null,
                    'id' => $userId,
                ]);
                admin_log_activity($pdo, (int) $adminUser['id'], 'admin_role_changed', 'user', $userId, ['admin_role' => $newRole]);
                $message = 'Admin role updated.';
            }
        } catch (Throwable $e) {
            $error = $e->getMessage() ?: 'Unable to update user.';
        }
    }
}

$page = max(1, request_int('page', 1));
[$page, $perPage, $offset] = paginate($page, 50);
$q = request_str('q');
$where = ['1=1'];
$params = [];
if ($q !== '') {
    $where[] = '(u.email LIKE :q OR u.full_name LIKE :q)';
    $params['q'] = '%' . $q . '%';
}

$countSql = 'SELECT COUNT(*) FROM users u WHERE ' . implode(' AND ', $where);
$countStmt = $pdo->prepare($countSql);
foreach ($params as $k => $v) {
    $countStmt->bindValue(':' . $k, $v);
}
$countStmt->execute();
$totalRows = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    [, , $offset] = paginate($page, $perPage);
}

$sql = 'SELECT u.id, u.full_name, u.email, u.role, u.admin_role, u.account_status, u.is_active, u.created_at,
    s.plan_code, s.status AS subscription_status
    FROM users u
    LEFT JOIN (
        SELECT s1.user_id, p.plan_code, s1.status
        FROM subscriptions s1
        JOIN subscription_plans p ON p.id = s1.plan_id
        JOIN (
            SELECT user_id, MAX(id) AS max_id
            FROM subscriptions
            GROUP BY user_id
        ) latest ON latest.max_id = s1.id
    ) s ON s.user_id = u.id
    WHERE ' . implode(' AND ', $where) . '
    ORDER BY u.id DESC
    LIMIT :limit OFFSET :offset';
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue(':' . $k, $v);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$adminTitle = 'Users';
include __DIR__ . '/includes/admin_header.php';
?>
<h1>Users</h1>
<?php if ($message): ?><p class="badge badge-ok"><?php echo e($message); ?></p><?php endif; ?>
<?php if ($error): ?><p class="badge badge-danger"><?php echo e($error); ?></p><?php endif; ?>
<section class="card">
  <form method="get" class="admin-form-inline">
    <input type="text" name="q" value="<?php echo e($q); ?>" placeholder="Search name/email">
    <button class="btn" type="submit">Search</button>
  </form>
  <p class="muted">Total <?php echo number_format($totalRows); ?> users.</p>
</section>

<section class="card admin-table-wrap">
  <table class="admin-table">
    <thead>
      <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Email</th>
        <th>Role</th>
        <th>Plan</th>
        <th>Account Status</th>
        <th>Joined</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $row): ?>
        <tr>
          <td><?php echo (int) $row['id']; ?></td>
          <td><?php echo e((string) ($row['full_name'] ?: '')); ?></td>
          <td><?php echo e((string) $row['email']); ?></td>
          <td>
            app: <?php echo e((string) $row['role']); ?><br>
            admin: <?php echo e((string) ($row['admin_role'] ?: '-')); ?>
          </td>
          <td><?php echo e((string) ($row['plan_code'] ?: '-')); ?><br><span class="muted"><?php echo e((string) ($row['subscription_status'] ?: '-')); ?></span></td>
          <td>
            <span class="<?php echo e(admin_status_badge_class((int) $row['is_active'] === 1 ? 'active' : 'suspended')); ?>"><?php echo (int) $row['is_active'] === 1 ? 'active' : 'inactive'; ?></span>
            <div><?php echo e((string) $row['account_status']); ?></div>
          </td>
          <td><?php echo e((string) $row['created_at']); ?></td>
          <td>
            <form method="post" class="admin-form-inline" data-confirm="Toggle this user account status?">
              <input type="hidden" name="_csrf" value="<?php echo e(csrf_token()); ?>">
              <input type="hidden" name="action" value="toggle_active">
              <input type="hidden" name="user_id" value="<?php echo (int) $row['id']; ?>">
              <button class="btn btn-secondary" type="submit"><?php echo (int) $row['is_active'] === 1 ? 'Deactivate' : 'Activate'; ?></button>
            </form>
            <?php if (admin_can_manage_roles($adminUser)): ?>
              <form method="post" class="admin-form-inline" style="margin-top:0.35rem;">
                <input type="hidden" name="_csrf" value="<?php echo e(csrf_token()); ?>">
                <input type="hidden" name="action" value="change_admin_role">
                <input type="hidden" name="user_id" value="<?php echo (int) $row['id']; ?>">
                <select name="admin_role">
                  <option value="" <?php echo ($row['admin_role'] ?? null) === null || (string) $row['admin_role'] === '' ? 'selected' : ''; ?>>none</option>
                  <option value="editor" <?php echo (string) $row['admin_role'] === 'editor' ? 'selected' : ''; ?>>editor</option>
                  <option value="admin" <?php echo (string) $row['admin_role'] === 'admin' ? 'selected' : ''; ?>>admin</option>
                  <option value="super_admin" <?php echo (string) $row['admin_role'] === 'super_admin' ? 'selected' : ''; ?>>super_admin</option>
                </select>
                <button class="btn btn-secondary" type="submit">Set Role</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="admin-actions" style="margin-top:0.8rem;">
    <?php if ($page > 1): ?><a class="btn btn-secondary" href="?<?php echo e(http_build_query(['q' => $q, 'page' => $page - 1])); ?>">Previous</a><?php endif; ?>
    <?php if ($page < $totalPages): ?><a class="btn btn-secondary" href="?<?php echo e(http_build_query(['q' => $q, 'page' => $page + 1])); ?>">Next</a><?php endif; ?>
  </div>
</section>
<?php include __DIR__ . '/includes/admin_footer.php'; ?>