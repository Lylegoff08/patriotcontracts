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

    try {
        $keys = $_POST['setting_key'] ?? [];
        $values = $_POST['setting_value'] ?? [];
        if (is_array($keys) && is_array($values)) {
            $stmt = $pdo->prepare('INSERT INTO site_settings (setting_key, setting_value, updated_by, created_at, updated_at)
                VALUES (:setting_key, :setting_value, :updated_by, NOW(), NOW())
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = NOW()');
            foreach ($keys as $idx => $key) {
                $settingKey = trim((string) $key);
                if ($settingKey === '') {
                    continue;
                }
                $settingValue = trim((string) ($values[$idx] ?? ''));
                $stmt->execute([
                    'setting_key' => $settingKey,
                    'setting_value' => $settingValue,
                    'updated_by' => (int) $adminUser['id'],
                ]);
            }
        }

        $newKey = trim(request_str('new_setting_key'));
        $newValue = trim(request_str('new_setting_value'));
        if ($newKey !== '') {
            $newStmt = $pdo->prepare('INSERT INTO site_settings (setting_key, setting_value, updated_by, created_at, updated_at)
                VALUES (:setting_key, :setting_value, :updated_by, NOW(), NOW())
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = NOW()');
            $newStmt->execute([
                'setting_key' => $newKey,
                'setting_value' => $newValue,
                'updated_by' => (int) $adminUser['id'],
            ]);
        }

        admin_log_activity($pdo, (int) $adminUser['id'], 'settings_updated', 'site_setting', null, []);
        $message = 'Settings saved.';
    } catch (Throwable $e) {
        $error = 'Unable to save settings.';
    }
}

$settings = $pdo->query('SELECT * FROM site_settings ORDER BY setting_key ASC')->fetchAll();

$adminTitle = 'Settings';
include __DIR__ . '/includes/admin_header.php';
?>
<h1>Site Settings</h1>
<?php if ($message): ?><p class="badge badge-ok"><?php echo e($message); ?></p><?php endif; ?>
<?php if ($error): ?><p class="badge badge-danger"><?php echo e($error); ?></p><?php endif; ?>
<section class="card">
  <p class="muted">Non-secret site content/settings only. Sensitive credentials stay in config files.</p>
  <form method="post" class="admin-form-block">
    <input type="hidden" name="_csrf" value="<?php echo e(csrf_token()); ?>">
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead><tr><th>Key</th><th>Value</th></tr></thead>
        <tbody>
          <?php foreach ($settings as $row): ?>
            <tr>
              <td>
                <input type="text" name="setting_key[]" value="<?php echo e((string) $row['setting_key']); ?>" readonly>
              </td>
              <td>
                <input type="text" name="setting_value[]" value="<?php echo e((string) ($row['setting_value'] ?? '')); ?>">
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <h3>Add Setting</h3>
    <div class="admin-form-inline">
      <input type="text" name="new_setting_key" placeholder="setting_key">
      <input type="text" name="new_setting_value" placeholder="value">
    </div>
    <p style="margin-top:0.8rem;"><button class="btn" type="submit">Save Settings</button></p>
  </form>
</section>
<?php include __DIR__ . '/includes/admin_footer.php'; ?>