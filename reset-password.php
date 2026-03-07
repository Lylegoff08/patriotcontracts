<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/security.php';

$token = request_str('token');
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_post();
    $token = request_str('token');
    $password = request_str('password');
    $confirm = request_str('password_confirm');

    if ($password !== $confirm) {
        $error = 'Passwords do not match';
    } else {
        [$ok, $msg] = reset_password_with_token($token, $password);
        if ($ok) {
            $message = $msg;
        } else {
            $error = $msg;
        }
    }
}

$pageTitle = 'PatriotContracts | Reset Password';
include __DIR__ . '/templates/header.php';
?>
<h1>Reset Password</h1>
<section class="card">
  <?php if ($message): ?><p><?php echo e($message); ?>. <a href="<?php echo e(app_url('login.php')); ?>">Login</a></p><?php endif; ?>
  <?php if ($error): ?><p class="warn"><?php echo e($error); ?></p><?php endif; ?>

  <?php if (!$message): ?>
    <form method="post" class="form">
      <input type="hidden" name="_csrf" value="<?php echo e(csrf_token()); ?>">
      <input type="hidden" name="token" value="<?php echo e($token); ?>">
      <label>New Password <input type="password" name="password" required minlength="8"></label>
      <label>Confirm Password <input type="password" name="password_confirm" required minlength="8"></label>
      <button class="btn" type="submit">Reset Password</button>
    </form>
  <?php endif; ?>
</section>
<?php include __DIR__ . '/templates/footer.php'; ?>
