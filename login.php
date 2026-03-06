<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/security.php';

if (current_user()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_post();
    [$ok, $msg] = login_user(request_str('email'), request_str('password'));
    if ($ok) {
        $next = request_str('next', 'dashboard.php');
        if ($next === '' || str_contains($next, '://')) {
            $next = 'dashboard.php';
        }
        header('Location: ' . $next);
        exit;
    }
    $error = $msg;
}

$pageTitle = 'PatriotContracts | Login';
include __DIR__ . '/templates/header.php';
?>
<h1>Login</h1>
<section class="card">
  <?php if ($error): ?><p class="warn"><?php echo e($error); ?></p><?php endif; ?>
  <form method="post" class="form">
    <input type="hidden" name="_csrf" value="<?php echo e(csrf_token()); ?>">
    <label>Email <input type="email" name="email" required></label>
    <label>Password <input type="password" name="password" required></label>
    <input type="hidden" name="next" value="<?php echo e(request_str('next')); ?>">
    <button class="btn" type="submit">Login</button>
  </form>
  <p><a href="forgot-password.php">Forgot password?</a></p>
  <hr>
  <p>Or continue with:</p>
  <p><a class="btn btn-secondary" href="oauth/google/start.php">Google</a></p>
  <p><a class="btn btn-secondary" href="oauth/apple/start.php">Apple</a></p>
  <p><a class="btn btn-secondary" href="oauth/facebook/start.php">Facebook</a></p>
  <p><a href="register.php">Create account</a></p>
</section>
<?php include __DIR__ . '/templates/footer.php'; ?>
