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
$id = request_int('id', 0);
$stmt = $pdo->prepare('SELECT * FROM site_pages WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $id]);
$pageRow = $stmt->fetch();
if (!$pageRow) {
    http_response_code(404);
    exit('Page not found');
}

$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_post();
    $title = trim(request_str('title'));
    $slug = admin_slugify(request_str('slug'));
    $metaTitle = trim(request_str('meta_title'));
    $metaDescription = trim(request_str('meta_description'));
    $body = trim((string) ($_POST['body'] ?? ''));
    $status = request_str('status', 'draft');
    if (!in_array($status, ['draft', 'published'], true)) {
        $status = 'draft';
    }

    if ($title === '' || $slug === '') {
        $error = 'Title and slug are required.';
    } else {
        try {
            $update = $pdo->prepare('UPDATE site_pages
                SET title = :title,
                    slug = :slug,
                    meta_title = :meta_title,
                    meta_description = :meta_description,
                    body = :body,
                    status = :status,
                    updated_by = :updated_by,
                    updated_at = NOW()
                WHERE id = :id');
            $update->execute([
                'title' => $title,
                'slug' => $slug,
                'meta_title' => $metaTitle !== '' ? $metaTitle : null,
                'meta_description' => $metaDescription !== '' ? $metaDescription : null,
                'body' => $body !== '' ? $body : null,
                'status' => $status,
                'updated_by' => (int) $adminUser['id'],
                'id' => $id,
            ]);
            admin_log_activity($pdo, (int) $adminUser['id'], 'page_updated', 'page', $id, ['slug' => $slug, 'status' => $status]);
            $message = 'Page updated.';
            $stmt->execute(['id' => $id]);
            $pageRow = $stmt->fetch();
        } catch (Throwable $e) {
            $error = 'Unable to update page. Slug may already exist.';
        }
    }
}

$adminTitle = 'Page Edit';
include __DIR__ . '/includes/admin_header.php';
?>
<h1>Edit Page #<?php echo (int) $id; ?></h1>
<?php if ($message): ?><p class="badge badge-ok"><?php echo e($message); ?></p><?php endif; ?>
<?php if ($error): ?><p class="badge badge-danger"><?php echo e($error); ?></p><?php endif; ?>
<section class="card">
  <form method="post" class="admin-form-block">
    <input type="hidden" name="_csrf" value="<?php echo e(csrf_token()); ?>">
    <label>Title <input type="text" name="title" value="<?php echo e((string) $pageRow['title']); ?>" required></label>
    <label>Slug <input type="text" name="slug" value="<?php echo e((string) $pageRow['slug']); ?>" required></label>
    <label>Meta Title <input type="text" name="meta_title" value="<?php echo e((string) ($pageRow['meta_title'] ?? '')); ?>"></label>
    <label>Meta Description <input type="text" name="meta_description" value="<?php echo e((string) ($pageRow['meta_description'] ?? '')); ?>"></label>
    <label>Body
      <textarea name="body" rows="16"><?php echo e((string) ($pageRow['body'] ?? '')); ?></textarea>
    </label>
    <label>Status
      <select name="status">
        <option value="draft" <?php echo (string) $pageRow['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
        <option value="published" <?php echo (string) $pageRow['status'] === 'published' ? 'selected' : ''; ?>>Published</option>
      </select>
    </label>
    <button class="btn" type="submit">Save Page</button>
    <a class="btn btn-secondary" href="pages.php">Back to Pages</a>
  </form>
</section>
<?php include __DIR__ . '/includes/admin_footer.php'; ?>