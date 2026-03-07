<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/security.php';

if (current_user()) {
    header('Location: ' . app_url('dashboard.php'));
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
$googleOauthEnabled = oauth_provider_configured('google');
$appleOauthEnabled = oauth_provider_configured('apple');
$facebookOauthEnabled = oauth_provider_configured('facebook');
include __DIR__ . '/templates/header.php';
?>
<h1>Sign In</h1>
<p class="muted">Access your dashboard, saved workflows, and member-only analytics after sign-in.</p>
<section class="card">
  <?php if ($error): ?><p class="warn"><?php echo e($error); ?></p><?php endif; ?>
  <form method="post" class="form">
    <input type="hidden" name="_csrf" value="<?php echo e(csrf_token()); ?>">
    <label>Email <input type="email" name="email" required></label>
    <label>Password <input type="password" name="password" required></label>
    <input type="hidden" name="next" value="<?php echo e(request_str('next')); ?>">
    <button class="btn" type="submit">Sign In</button>
  </form>
  <p class="muted">Need public-only access? You can browse listings without signing in.</p>
  <p><a class="btn btn-secondary" href="<?php echo e(app_url('home.php')); ?>">Browse Public Listings</a></p>
  <p><a href="<?php echo e(app_url('forgot-password.php')); ?>">Forgot password?</a></p>
  <hr>
  <?php if ($googleOauthEnabled || $appleOauthEnabled || $facebookOauthEnabled): ?>
    <p>Or sign in with:</p>
    <?php if ($googleOauthEnabled): ?><p><a class="btn btn-secondary" href="<?php echo e(app_url('oauth/google/start.php')); ?>">Google</a></p><?php endif; ?>
    <?php if ($appleOauthEnabled): ?><p><a class="btn btn-secondary" href="<?php echo e(app_url('oauth/apple/start.php')); ?>">Apple</a></p><?php endif; ?>
    <?php if ($facebookOauthEnabled): ?><p><a class="btn btn-secondary" href="<?php echo e(app_url('oauth/facebook/start.php')); ?>">Facebook</a></p><?php endif; ?>
  <?php endif; ?>
  <p><a href="<?php echo e(app_url('register.php')); ?>">Create a member account</a></p>
</section>
<?php include __DIR__ . '/templates/footer.php'; ?>
