<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';

$adminUser = require_admin_auth(['admin', 'super_admin']);
$pdo = db();
$id = request_int('id', 0);

$stmt = $pdo->prepare("SELECT cc.*, a.name AS agency_name, cat.name AS category_name
    FROM contracts_clean cc
    LEFT JOIN agencies a ON a.id = cc.agency_id
    LEFT JOIN contract_categories cat ON cat.id = cc.category_id
    WHERE cc.id = :id AND cc.source_type = 'grants' LIMIT 1");
$stmt->execute(['id' => $id]);
$grant = $stmt->fetch();
if (!$grant) {
    http_response_code(404);
    exit('Grant not found');
}

$overrideStmt = $pdo->prepare('SELECT * FROM grant_overrides WHERE contract_id = :id LIMIT 1');
$overrideStmt->execute(['id' => $id]);
$override = $overrideStmt->fetch() ?: [];
$categories = $pdo->query('SELECT id, name FROM contract_categories ORDER BY name')->fetchAll();

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_post();

    $displayTitle = trim(request_str('display_title'));
    $displaySummary = trim((string) ($_POST['display_summary'] ?? ''));
    $categoryOverride = request_int('category_override', 0);
    $tagsOverride = trim(request_str('tags_override'));
    $qualityFlag = trim(request_str('quality_flag'));
    $internalNotes = trim((string) ($_POST['internal_notes'] ?? ''));
    $isFeatured = request_int('is_featured', 0) === 1 ? 1 : 0;
    $isHidden = request_int('is_hidden', 0) === 1 ? 1 : 0;

    $save = $pdo->prepare('INSERT INTO grant_overrides
        (contract_id, display_title, display_summary, category_override, tags_override, is_featured, is_hidden, quality_flag, internal_notes, updated_by, created_at, updated_at)
        VALUES
        (:contract_id, :display_title, :display_summary, :category_override, :tags_override, :is_featured, :is_hidden, :quality_flag, :internal_notes, :updated_by, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
          display_title = VALUES(display_title),
          display_summary = VALUES(display_summary),
          category_override = VALUES(category_override),
          tags_override = VALUES(tags_override),
          is_featured = VALUES(is_featured),
          is_hidden = VALUES(is_hidden),
          quality_flag = VALUES(quality_flag),
          internal_notes = VALUES(internal_notes),
          updated_by = VALUES(updated_by),
          updated_at = NOW()');
    $save->execute([
        'contract_id' => $id,
        'display_title' => $displayTitle !== '' ? $displayTitle : null,
        'display_summary' => $displaySummary !== '' ? $displaySummary : null,
        'category_override' => $categoryOverride > 0 ? $categoryOverride : null,
        'tags_override' => $tagsOverride !== '' ? $tagsOverride : null,
        'is_featured' => $isFeatured,
        'is_hidden' => $isHidden,
        'quality_flag' => $qualityFlag !== '' ? $qualityFlag : null,
        'internal_notes' => $internalNotes !== '' ? $internalNotes : null,
        'updated_by' => (int) $adminUser['id'],
    ]);

    admin_log_activity($pdo, (int) $adminUser['id'], 'grant_override_updated', 'grant', $id, [
        'is_hidden' => $isHidden,
        'is_featured' => $isFeatured,
        'quality_flag' => $qualityFlag,
    ]);

    header('Location: grant_edit.php?id=' . $id . '&saved=1');
    exit;
}

if (request_int('saved', 0) === 1) {
    $message = 'Grant override saved.';
    $overrideStmt->execute(['id' => $id]);
    $override = $overrideStmt->fetch() ?: [];
}

$adminTitle = 'Grant Edit';
include __DIR__ . '/includes/admin_header.php';
?>
<h1>Grant #<?php echo (int) $id; ?></h1>
<?php if ($message): ?><p class="badge badge-ok"><?php echo e($message); ?></p><?php endif; ?>
<section class="card">
  <p><strong>Raw Title:</strong> <?php echo e((string) $grant['title']); ?></p>
  <p><strong>Agency:</strong> <?php echo e(display_field_value('agency', $grant['agency_name'] ?? null)); ?></p>
  <p><strong>Category:</strong> <?php echo e(display_field_value('category', $grant['category_name'] ?? null)); ?></p>
  <p><strong>Source Type:</strong> <?php echo e((string) $grant['source_type']); ?></p>
</section>

<section class="card">
  <form method="post" class="admin-form-block">
    <input type="hidden" name="_csrf" value="<?php echo e(csrf_token()); ?>">
    <label>Display Title
      <input type="text" name="display_title" value="<?php echo e((string) ($override['display_title'] ?? '')); ?>" maxlength="500">
    </label>
    <label>Display Summary
      <textarea name="display_summary" rows="5"><?php echo e((string) ($override['display_summary'] ?? '')); ?></textarea>
    </label>
    <label>Category Override
      <select name="category_override">
        <option value="0">Use raw category</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?php echo (int) $cat['id']; ?>" <?php echo (int) ($override['category_override'] ?? 0) === (int) $cat['id'] ? 'selected' : ''; ?>><?php echo e((string) $cat['name']); ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Tags Override (comma-separated)
      <input type="text" name="tags_override" value="<?php echo e((string) ($override['tags_override'] ?? '')); ?>">
    </label>
    <label>Quality Flag
      <input type="text" name="quality_flag" value="<?php echo e((string) ($override['quality_flag'] ?? '')); ?>" placeholder="review, verified, low-confidence">
    </label>
    <label>Internal Notes
      <textarea name="internal_notes" rows="6"><?php echo e((string) ($override['internal_notes'] ?? '')); ?></textarea>
    </label>
    <label><input type="checkbox" name="is_featured" value="1" <?php echo (int) ($override['is_featured'] ?? 0) === 1 ? 'checked' : ''; ?>> Featured</label>
    <label><input type="checkbox" name="is_hidden" value="1" <?php echo (int) ($override['is_hidden'] ?? 0) === 1 ? 'checked' : ''; ?>> Hidden</label>
    <button class="btn" type="submit">Save Override</button>
    <a class="btn btn-secondary" href="grants.php">Back to Grants</a>
  </form>
</section>
<?php include __DIR__ . '/includes/admin_footer.php'; ?>