<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/membership.php';

$user = require_login();
$pdo = db();
$userId = (int) $user['id'];

$message = '';
$error = '';
$oneTimeApiKey = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_post();
    $action = request_str('action');

    if ($action === 'create_saved_search') {
        if (!user_has_feature($pdo, $userId, 'saved_searches')) {
            $error = 'Pro feature: saved searches';
        } else {
            $name = request_str('name');
            $queryJson = request_str('query_json');
            if ($name === '' || $queryJson === '') {
                $error = 'Name and query are required';
            } else {
                $stmt = $pdo->prepare('INSERT INTO saved_searches (user_id, name, query_json, created_at, updated_at) VALUES (:user_id, :name, :query_json, NOW(), NOW())');
                $stmt->execute(['user_id' => $userId, 'name' => $name, 'query_json' => $queryJson]);
                $message = 'Saved search created';
            }
        }
    } elseif ($action === 'create_alert') {
        if (!user_has_feature($pdo, $userId, 'alerts')) {
            $error = 'Pro feature: alerts';
        } else {
            $savedSearchId = request_int('saved_search_id', 0);
            $frequency = request_str('frequency', 'daily');
            $stmt = $pdo->prepare('INSERT INTO alerts (user_id, saved_search_id, alert_type, frequency, is_active, created_at)
                VALUES (:user_id, :saved_search_id, "email", :frequency, 1, NOW())');
            $stmt->execute([
                'user_id' => $userId,
                'saved_search_id' => $savedSearchId > 0 ? $savedSearchId : null,
                'frequency' => $frequency,
            ]);
            $message = 'Alert created';
        }
    } elseif ($action === 'generate_api_key') {
        [$ok, $msgOrKey] = generate_api_key_for_user($pdo, $userId);
        if ($ok) {
            $oneTimeApiKey = $msgOrKey;
            $message = 'API key created. Copy it now; it will not be shown again.';
        } else {
            $error = $msgOrKey;
        }
    } elseif ($action === 'revoke_api_key') {
        $keyId = request_int('key_id');
        if (revoke_api_key_for_user($pdo, $userId, $keyId)) {
            $message = 'API key revoked';
        } else {
            $error = 'Unable to revoke API key';
        }
    }
}

$subscription = fetch_active_subscription_for_user($pdo, $userId);
$features = feature_flags_for_subscription($subscription);

$savedStmt = $pdo->prepare('SELECT id, name, query_json, created_at FROM saved_searches WHERE user_id = :user_id ORDER BY id DESC LIMIT 25');
$savedStmt->execute(['user_id' => $userId]);
$savedSearches = $savedStmt->fetchAll();

$alertsStmt = $pdo->prepare('SELECT id, saved_search_id, frequency, is_active, last_run_at, created_at FROM alerts WHERE user_id = :user_id ORDER BY id DESC LIMIT 25');
$alertsStmt->execute(['user_id' => $userId]);
$alerts = $alertsStmt->fetchAll();

$apiKeys = list_api_keys_for_user($pdo, $userId);

$pageTitle = 'PatriotContracts | Dashboard';
include __DIR__ . '/templates/header.php';
?>
<h1>Dashboard</h1>
<section class="card">
  <?php if ($message): ?><p><?php echo e($message); ?></p><?php endif; ?>
  <?php if ($error): ?><p class="warn"><?php echo e($error); ?></p><?php endif; ?>
  <?php if ($oneTimeApiKey): ?><p><strong>New API key:</strong> <code><?php echo e($oneTimeApiKey); ?></code></p><?php endif; ?>
  <p><strong>Email:</strong> <?php echo e((string) $user['email']); ?></p>
  <p><strong>Account Status:</strong> <?php echo e((string) $user['account_status']); ?></p>
  <p><strong>Email Verified:</strong> <?php echo (int) ($user['email_verified'] ?? 0) === 1 ? 'Yes' : 'No'; ?></p>
  <p><strong>Current Plan:</strong> <?php echo e((string) ($subscription['plan_code'] ?? 'None')); ?></p>
  <p><strong>Subscription Status:</strong> <?php echo e((string) ($subscription['status'] ?? 'none')); ?></p>
  <p><strong>Renewal:</strong> <?php echo e((string) ($subscription['current_period_end'] ?? '-')); ?></p>
</section>

<section class="card">
  <h2>Feature Access</h2>
  <p>Member tools: <?php echo !empty($features['member_tools']) ? 'Enabled' : 'Locked'; ?></p>
  <p>Advanced search: <?php echo !empty($features['advanced_search']) ? 'Enabled' : 'Locked (Pro feature)'; ?></p>
  <p>Saved searches: <?php echo !empty($features['saved_searches']) ? 'Enabled' : 'Locked (Pro feature)'; ?></p>
  <p>Alerts: <?php echo !empty($features['alerts']) ? 'Enabled' : 'Locked (Pro feature)'; ?></p>
  <p>CSV export: <?php echo !empty($features['csv_export']) ? 'Enabled' : 'Locked (Pro feature)'; ?></p>
  <p>API access: <?php echo !empty($features['api_access']) ? 'Enabled' : 'Locked (API plan required)'; ?></p>
</section>

<section class="card">
  <h2>Saved Searches</h2>
  <?php if (!user_has_feature($pdo, $userId, 'saved_searches')): ?>
    <p class="muted">Pro feature. Upgrade to save searches.</p>
  <?php else: ?>
    <form method="post" class="form">
      <input type="hidden" name="_csrf" value="<?php echo e(csrf_token()); ?>">
      <input type="hidden" name="action" value="create_saved_search">
      <label>Name <input type="text" name="name" required></label>
      <label>Query JSON <textarea name="query_json" rows="3" placeholder='{"keyword":"cyber","mode":"open"}' required></textarea></label>
      <button class="btn" type="submit">Save Search</button>
    </form>
  <?php endif; ?>
  <?php foreach ($savedSearches as $s): ?>
    <p><strong><?php echo e((string) $s['name']); ?></strong> <span class="muted"><?php echo e((string) $s['created_at']); ?></span><br><code><?php echo e((string) $s['query_json']); ?></code></p>
  <?php endforeach; ?>
</section>

<section class="card">
  <h2>Alerts</h2>
  <?php if (!user_has_feature($pdo, $userId, 'alerts')): ?>
    <p class="muted">Pro feature. Upgrade to create alerts.</p>
  <?php else: ?>
    <form method="post" class="form">
      <input type="hidden" name="_csrf" value="<?php echo e(csrf_token()); ?>">
      <input type="hidden" name="action" value="create_alert">
      <label>Saved Search
        <select name="saved_search_id">
          <option value="0">None</option>
          <?php foreach ($savedSearches as $s): ?>
            <option value="<?php echo (int) $s['id']; ?>"><?php echo e((string) $s['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Frequency
        <select name="frequency">
          <option value="daily">Daily</option>
          <option value="weekly">Weekly</option>
        </select>
      </label>
      <button class="btn" type="submit">Create Alert</button>
    </form>
  <?php endif; ?>
  <?php foreach ($alerts as $a): ?>
    <p>#<?php echo (int) $a['id']; ?> | <?php echo e((string) $a['frequency']); ?> | <?php echo (int) $a['is_active'] === 1 ? 'active' : 'inactive'; ?> | last run: <?php echo e((string) $a['last_run_at']); ?></p>
  <?php endforeach; ?>
</section>

<section class="card">
  <h2>API Keys</h2>
  <?php if (!user_has_feature($pdo, $userId, 'api_keys')): ?>
    <p class="muted">API plan required.</p>
  <?php else: ?>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?php echo e(csrf_token()); ?>">
      <input type="hidden" name="action" value="generate_api_key">
      <button class="btn" type="submit">Generate New API Key</button>
    </form>
  <?php endif; ?>
  <?php foreach ($apiKeys as $k): ?>
    <p><code><?php echo e((string) $k['api_key_prefix']); ?>...</code> | <?php echo e((string) $k['status']); ?> | limit <?php echo (int) $k['daily_limit']; ?>/day | last used <?php echo e((string) $k['last_used_at']); ?></p>
    <?php if ((string) $k['status'] === 'active'): ?>
      <form method="post" style="margin-bottom:0.8rem;">
        <input type="hidden" name="_csrf" value="<?php echo e(csrf_token()); ?>">
        <input type="hidden" name="action" value="revoke_api_key">
        <input type="hidden" name="key_id" value="<?php echo (int) $k['id']; ?>">
        <button class="btn btn-secondary" type="submit">Revoke</button>
      </form>
    <?php endif; ?>
  <?php endforeach; ?>
</section>
<?php include __DIR__ . '/templates/footer.php'; ?>
