<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/security.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_post();
    create_password_reset(request_str('email'));
    $message = 'If that email exists, a reset link has been sent.';
}

$pageTitle = 'PatriotContracts | Forgot Password';
include __DIR__ . '/templates/header.php';
?>
<h1>Forgot Password</h1>
<section class="card">
  <?php if ($message): ?><p><?php echo e($message); ?></p><?php endif; ?>
  <form method="post" class="form">
    <input type="hidden" name="_csrf" value="<?php echo e(csrf_token()); ?>">
    <label>Email <input type="email" name="email" required></label>
    <button class="btn" type="submit">Send Reset Link</button>
  </form>
</section>
<?php include __DIR__ . '/templates/footer.php'; ?>
