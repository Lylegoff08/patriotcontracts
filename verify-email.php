<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/security.php';

$message = '';
$error = '';
$token = request_str('token');

if ($token !== '') {
    [$ok, $msg] = verify_email_token($token);
    if ($ok) {
        $message = $msg;
    } else {
        $error = $msg;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_post();
    [$ok, $msg] = resend_verification_for_current_user();
    if ($ok) {
        $message = $msg;
    } else {
        $error = $msg;
    }
}

$pageTitle = 'PatriotContracts | Verify Email';
include __DIR__ . '/templates/header.php';
?>
<h1>Email Verification</h1>
<section class="card">
  <?php if ($message): ?><p><?php echo e($message); ?></p><?php endif; ?>
  <?php if ($error): ?><p class="warn"><?php echo e($error); ?></p><?php endif; ?>

  <?php if (current_user() && (int) (current_user()['email_verified'] ?? 0) !== 1): ?>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?php echo e(csrf_token()); ?>">
      <button class="btn" type="submit">Resend Verification Email</button>
    </form>
  <?php else: ?>
    <p><a href="<?php echo e(app_url('login.php')); ?>">Login</a></p>
  <?php endif; ?>
</section>
<?php include __DIR__ . '/templates/footer.php'; ?>
