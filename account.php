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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_post();
    $action = request_str('action');

    if ($action === 'update_profile') {
        $fullName = request_str('full_name');
        $stmt = $pdo->prepare('UPDATE users SET full_name = :full_name, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['full_name' => $fullName, 'id' => $userId]);
        $message = 'Profile updated';
    } elseif ($action === 'change_password') {
        if ((string) ($user['provider'] ?? 'email') !== 'email') {
            $error = 'Password changes are only for email/password accounts';
        } else {
            $current = request_str('current_password');
            $new = request_str('new_password');
            $confirm = request_str('confirm_password');

            if ($new !== $confirm) {
                $error = 'Passwords do not match';
            } elseif (strlen($new) < 8) {
                $error = 'New password must be at least 8 characters';
            } else {
                $check = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
                $check->execute(['id' => $userId]);
                $currentHash = (string) ($check->fetchColumn() ?: '');
                if (!password_verify($current, $currentHash)) {
                    $error = 'Current password is incorrect';
                } else {
                    $newHash = password_hash($new, app_config()['security']['password_algo'] ?? PASSWORD_DEFAULT);
                    $update = $pdo->prepare('UPDATE users SET password_hash = :hash, updated_at = NOW() WHERE id = :id');
                    $update->execute(['hash' => $newHash, 'id' => $userId]);
                    $message = 'Password changed';
                }
            }
        }
    } elseif ($action === 'resend_verification') {
        [$ok, $msg] = resend_verification_for_current_user();
        if ($ok) {
            $message = $msg;
        } else {
            $error = $msg;
        }
    }
}

$user = current_user() ?: $user;
$subscription = fetch_active_subscription_for_user($pdo, $userId);
$apiKeys = list_api_keys_for_user($pdo, $userId);

$pageTitle = 'PatriotContracts | Account';
include __DIR__ . '/templates/header.php';
?>
<h1>Account</h1>
<section class="card">
  <?php if ($message): ?><p><?php echo e($message); ?></p><?php endif; ?>
  <?php if ($error): ?><p class="warn"><?php echo e($error); ?></p><?php endif; ?>
  <form method="post" class="form">
    <input type="hidden" name="_csrf" value="<?php echo e(csrf_token()); ?>">
    <input type="hidden" name="action" value="update_profile">
    <label>Email <input type="email" value="<?php echo e((string) $user['email']); ?>" disabled></label>
    <label>Full Name <input type="text" name="full_name" value="<?php echo e((string) ($user['full_name'] ?? '')); ?>"></label>
    <button class="btn" type="submit">Update Profile</button>
  </form>
</section>

<section class="card">
  <h2>Verification and Status</h2>
  <p>Email verified: <?php echo (int) ($user['email_verified'] ?? 0) === 1 ? 'Yes' : 'No'; ?></p>
  <p>Account status: <?php echo e((string) ($user['account_status'] ?? '')); ?></p>
  <p>Provider: <?php echo e((string) ($user['provider'] ?? 'email')); ?></p>
  <?php if ((int) ($user['email_verified'] ?? 0) !== 1): ?>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?php echo e(csrf_token()); ?>">
      <input type="hidden" name="action" value="resend_verification">
      <button class="btn btn-secondary" type="submit">Resend Verification Email</button>
    </form>
  <?php endif; ?>
</section>

<?php if ((string) ($user['provider'] ?? 'email') === 'email'): ?>
<section class="card">
  <h2>Change Password</h2>
  <form method="post" class="form">
    <input type="hidden" name="_csrf" value="<?php echo e(csrf_token()); ?>">
    <input type="hidden" name="action" value="change_password">
    <label>Current Password <input type="password" name="current_password" required></label>
    <label>New Password <input type="password" name="new_password" required minlength="8"></label>
    <label>Confirm New Password <input type="password" name="confirm_password" required minlength="8"></label>
    <button class="btn" type="submit">Change Password</button>
  </form>
</section>
<?php endif; ?>

<section class="card">
  <h2>Subscription</h2>
  <p>Plan: <?php echo e((string) ($subscription['plan_code'] ?? 'None')); ?></p>
  <p>Status: <?php echo e((string) ($subscription['status'] ?? 'none')); ?></p>
  <p>Current period end: <?php echo e((string) ($subscription['current_period_end'] ?? '-')); ?></p>
  <p><a class="btn" href="pricing.php">Change Plan</a></p>
</section>

<section class="card">
  <h2>API Keys</h2>
  <?php if (!user_has_feature($pdo, $userId, 'api_keys')): ?>
    <p class="muted">API plan required for API keys.</p>
  <?php else: ?>
    <?php foreach ($apiKeys as $k): ?>
      <p><code><?php echo e((string) $k['api_key_prefix']); ?>...</code> | <?php echo e((string) $k['status']); ?> | limit <?php echo (int) $k['daily_limit']; ?>/day</p>
    <?php endforeach; ?>
    <p><a class="btn" href="dashboard.php">Manage in Dashboard</a></p>
  <?php endif; ?>
</section>
<?php include __DIR__ . '/templates/footer.php'; ?>
