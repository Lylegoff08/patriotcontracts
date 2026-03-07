<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';

$adminUser = require_admin_auth();
if (!admin_can_manage_content($adminUser)) {
    http_response_code(403);
    exit('Admin permission denied');
}

$pdo = db();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_post();
    $title = trim(request_str('title'));
    $slug = admin_slugify(request_str('slug'));
    if ($slug === '') {
        $slug = admin_slugify($title);
    }

    if ($title === '' || $slug === '') {
        $error = 'Title and slug are required.';
    } else {
        try {
            $stmt = $pdo->prepare('INSERT INTO site_pages (title, slug, status, created_by, updated_by, created_at, updated_at)
                VALUES (:title, :slug, "draft", :created_by, :updated_by, NOW(), NOW())');
            $stmt->execute([
                'title' => $title,
                'slug' => $slug,
                'created_by' => (int) $adminUser['id'],
                'updated_by' => (int) $adminUser['id'],
            ]);
            $pageId = (int) $pdo->lastInsertId();
            admin_log_activity($pdo, (int) $adminUser['id'], 'page_created', 'page', $pageId, ['slug' => $slug]);
            header('Location: page_edit.php?id=' . $pageId);
            exit;
        } catch (Throwable $e) {
            $error = 'Unable to create page. Slug may already exist.';
        }
    }
}

$rows = $pdo->query('SELECT p.*, u.email AS updated_by_email
    FROM site_pages p
    LEFT JOIN users u ON u.id = p.updated_by
    ORDER BY p.updated_at DESC, p.id DESC')->fetchAll();

$adminTitle = 'Pages';
include __DIR__ . '/includes/admin_header.php';
?>
<h1>Pages</h1>
<?php if ($message): ?><p class="badge badge-ok"><?php echo e($message); ?></p><?php endif; ?>
<?php if ($error): ?><p class="badge badge-danger"><?php echo e($error); ?></p><?php endif; ?>

<section class="card">
  <h2>Create Page</h2>
  <form method="post" class="admin-form-inline">
    <input type="hidden" name="_csrf" value="<?php echo e(csrf_token()); ?>">
    <input type="text" name="title" placeholder="Page title" required>
    <input type="text" name="slug" placeholder="slug (optional)">
    <button class="btn" type="submit">Create</button>
  </form>
</section>

<section class="card admin-table-wrap">
  <table class="admin-table">
    <thead>
      <tr>
        <th>ID</th>
        <th>Title</th>
        <th>Slug</th>
        <th>Status</th>
        <th>Updated</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $row): ?>
        <tr>
          <td><?php echo (int) $row['id']; ?></td>
          <td><?php echo e((string) $row['title']); ?></td>
          <td><code><?php echo e((string) $row['slug']); ?></code></td>
          <td><span class="<?php echo e(admin_status_badge_class((string) $row['status'])); ?>"><?php echo e((string) $row['status']); ?></span></td>
          <td><?php echo e((string) $row['updated_at']); ?><?php if (!empty($row['updated_by_email'])): ?> by <?php echo e((string) $row['updated_by_email']); ?><?php endif; ?></td>
          <td><a class="btn btn-secondary" href="page_edit.php?id=<?php echo (int) $row['id']; ?>">Edit</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php include __DIR__ . '/includes/admin_footer.php'; ?>