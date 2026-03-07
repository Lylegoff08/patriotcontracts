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
    $contractId = request_int('contract_id', 0);

    if ($contractId > 0) {
        try {
            if ($action === 'toggle_hidden') {
                $stmt = $pdo->prepare('INSERT INTO grant_overrides (contract_id, is_hidden, updated_by, created_at, updated_at)
                    VALUES (:contract_id, 1, :updated_by, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE is_hidden = IF(is_hidden = 1, 0, 1), updated_by = VALUES(updated_by), updated_at = NOW()');
                $stmt->execute(['contract_id' => $contractId, 'updated_by' => (int) $adminUser['id']]);
                admin_log_activity($pdo, (int) $adminUser['id'], 'grant_toggle_hidden', 'grant', $contractId, []);
                $message = 'Grant visibility updated.';
            } elseif ($action === 'toggle_featured') {
                $stmt = $pdo->prepare('INSERT INTO grant_overrides (contract_id, is_featured, updated_by, created_at, updated_at)
                    VALUES (:contract_id, 1, :updated_by, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE is_featured = IF(is_featured = 1, 0, 1), updated_by = VALUES(updated_by), updated_at = NOW()');
                $stmt->execute(['contract_id' => $contractId, 'updated_by' => (int) $adminUser['id']]);
                admin_log_activity($pdo, (int) $adminUser['id'], 'grant_toggle_featured', 'grant', $contractId, []);
                $message = 'Grant featured flag updated.';
            } elseif ($action === 'save_note') {
                $note = trim(request_str('internal_note'));
                $stmt = $pdo->prepare('INSERT INTO grant_overrides (contract_id, internal_notes, updated_by, created_at, updated_at)
                    VALUES (:contract_id, :internal_notes, :updated_by, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE internal_notes = :internal_notes_update, updated_by = VALUES(updated_by), updated_at = NOW()');
                $stmt->execute([
                    'contract_id' => $contractId,
                    'internal_notes' => $note,
                    'internal_notes_update' => $note,
                    'updated_by' => (int) $adminUser['id'],
                ]);
                admin_log_activity($pdo, (int) $adminUser['id'], 'grant_note_updated', 'grant', $contractId, ['length' => strlen($note)]);
                $message = 'Internal note saved.';
            }
        } catch (Throwable $e) {
            $error = 'Unable to update grant.';
        }
    }
}

$page = max(1, request_int('page', 1));
[$page, $perPage, $offset] = paginate($page, 30);
$q = request_str('q');
$where = ["cc.is_duplicate = 0", "cc.source_type = 'grants'"];
$params = [];
if ($q !== '') {
    $where[] = '(cc.title LIKE :q OR a.name LIKE :q OR s.name LIKE :q OR cc.source_record_id LIKE :q)';
    $params['q'] = '%' . $q . '%';
}

$countSql = 'SELECT COUNT(*)
    FROM contracts_clean cc
    LEFT JOIN agencies a ON a.id = cc.agency_id
    LEFT JOIN sources s ON s.id = cc.source_id
    WHERE ' . implode(' AND ', $where);
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

$sql = 'SELECT cc.id,
    COALESCE(NULLIF(go.display_title, ""), cc.title) AS title,
    a.name AS agency_name,
    s.name AS source_name,
    cc.posted_date,
    COALESCE(go.is_hidden, 0) AS is_hidden,
    COALESCE(go.is_featured, 0) AS is_featured,
    COALESCE(NULLIF(go.quality_flag, ""), "") AS quality_flag,
    COALESCE(go.internal_notes, "") AS internal_notes
    FROM contracts_clean cc
    LEFT JOIN grant_overrides go ON go.contract_id = cc.id
    LEFT JOIN agencies a ON a.id = cc.agency_id
    LEFT JOIN sources s ON s.id = cc.source_id
    WHERE ' . implode(' AND ', $where) . '
    ORDER BY cc.posted_date DESC, cc.id DESC
    LIMIT :limit OFFSET :offset';
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue(':' . $k, $v);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$adminTitle = 'Grants';
include __DIR__ . '/includes/admin_header.php';
?>
<h1>Grants Manager</h1>
<?php if ($message): ?><p class="badge badge-ok"><?php echo e($message); ?></p><?php endif; ?>
<?php if ($error): ?><p class="badge badge-danger"><?php echo e($error); ?></p><?php endif; ?>

<section class="card">
  <form method="get" class="admin-form-inline">
    <input type="text" name="q" value="<?php echo e($q); ?>" placeholder="Search title, agency, source">
    <button class="btn" type="submit">Search</button>
  </form>
  <p class="muted">Total <?php echo number_format($totalRows); ?> grants. Page <?php echo (int) $page; ?> of <?php echo (int) $totalPages; ?>.</p>
</section>

<section class="card admin-table-wrap">
  <table class="admin-table">
    <thead>
      <tr>
        <th>ID</th>
        <th>Title</th>
        <th>Agency</th>
        <th>Source</th>
        <th>Posted</th>
        <th>Visibility</th>
        <th>Featured</th>
        <th>Quality Flag</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $row): ?>
        <tr>
          <td><?php echo (int) $row['id']; ?></td>
          <td><?php echo e((string) $row['title']); ?></td>
          <td><?php echo e(display_field_value('agency', $row['agency_name'] ?? null)); ?></td>
          <td><?php echo e(display_field_value('source_name', $row['source_name'] ?? null)); ?></td>
          <td><?php echo e(display_field_value('posted_date', $row['posted_date'] ?? null)); ?></td>
          <td><span class="<?php echo e(admin_status_badge_class((int) $row['is_hidden'] === 1 ? 'hidden' : 'visible')); ?>"><?php echo (int) $row['is_hidden'] === 1 ? 'Hidden' : 'Visible'; ?></span></td>
          <td><span class="<?php echo e(admin_status_badge_class((int) $row['is_featured'] === 1 ? 'active' : 'draft')); ?>"><?php echo (int) $row['is_featured'] === 1 ? 'Featured' : 'No'; ?></span></td>
          <td><?php echo e((string) $row['quality_flag']); ?></td>
          <td>
            <div class="admin-actions">
              <a class="btn btn-secondary" href="<?php echo e(rtrim((string) (app_config()['app']['base_url'] ?? ''), '/')); ?>/contract.php?id=<?php echo (int) $row['id']; ?>" target="_blank" rel="noopener">View</a>
              <a class="btn btn-secondary" href="grant_edit.php?id=<?php echo (int) $row['id']; ?>">Quick Edit</a>
              <form method="post" class="admin-form-inline">
                <input type="hidden" name="_csrf" value="<?php echo e(csrf_token()); ?>">
                <input type="hidden" name="action" value="toggle_hidden">
                <input type="hidden" name="contract_id" value="<?php echo (int) $row['id']; ?>">
                <button class="btn btn-secondary" type="submit"><?php echo (int) $row['is_hidden'] === 1 ? 'Show' : 'Hide'; ?></button>
              </form>
              <form method="post" class="admin-form-inline">
                <input type="hidden" name="_csrf" value="<?php echo e(csrf_token()); ?>">
                <input type="hidden" name="action" value="toggle_featured">
                <input type="hidden" name="contract_id" value="<?php echo (int) $row['id']; ?>">
                <button class="btn btn-secondary" type="submit"><?php echo (int) $row['is_featured'] === 1 ? 'Unfeature' : 'Feature'; ?></button>
              </form>
            </div>
            <form method="post" class="admin-form-inline" style="margin-top:0.35rem;">
              <input type="hidden" name="_csrf" value="<?php echo e(csrf_token()); ?>">
              <input type="hidden" name="action" value="save_note">
              <input type="hidden" name="contract_id" value="<?php echo (int) $row['id']; ?>">
              <input type="text" name="internal_note" value="<?php echo e((string) $row['internal_notes']); ?>" placeholder="Internal note">
              <button class="btn btn-secondary" type="submit">Save Note</button>
            </form>
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