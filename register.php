<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/stripe.php';

if (current_user()) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

$plan = strtoupper(request_str('plan', 'MEMBER_BASIC'));
if (!in_array($plan, membership_plan_codes(), true)) {
    $plan = 'MEMBER_BASIC';
}

$error = '';
$googleOauthEnabled = oauth_provider_configured('google');
$appleOauthEnabled = oauth_provider_configured('apple');
$facebookOauthEnabled = oauth_provider_configured('facebook');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_post();
    $plan = strtoupper(request_str('plan', 'MEMBER_BASIC'));
    if (!in_array($plan, membership_plan_codes(), true)) {
        $error = 'Invalid plan selected';
    } else {
        [$ok, $msg, $userId] = register_email_user(request_str('email'), request_str('password'), request_str('full_name'));
        if (!$ok) {
            $error = $msg;
        } else {
            mark_user_logged_in((int) $userId);
            try {
                $session = create_checkout_session(db(), (int) $userId, $plan);
                $url = (string) ($session['url'] ?? '');
                if ($url === '') {
                    throw new RuntimeException('Checkout URL missing');
                }
                header('Location: ' . $url);
                exit;
            } catch (Throwable $e) {
                $error = 'Account created, but checkout could not start: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'PatriotContracts | Register';
include __DIR__ . '/templates/header.php';
?>
<h1>Create Member Account</h1>
<section class="card">
  <?php if ($error): ?><p class="warn"><?php echo e($error); ?></p><?php endif; ?>
  <p class="muted">Choose a plan, create your account, and continue to secure checkout. Public browsing remains available without registration.</p>
  <form method="post" class="form">
    <input type="hidden" name="_csrf" value="<?php echo e(csrf_token()); ?>">
    <label>Full name <input type="text" name="full_name" value="<?php echo e(request_str('full_name')); ?>"></label>
    <label>Email <input type="email" name="email" required value="<?php echo e(request_str('email')); ?>"></label>
    <label>Password <input type="password" name="password" required minlength="8"></label>
    <label>Plan
      <select name="plan">
        <option value="MEMBER_BASIC" <?php echo $plan === 'MEMBER_BASIC' ? 'selected' : ''; ?>>Member Basic (Core)</option>
        <option value="MEMBER_PRO" <?php echo $plan === 'MEMBER_PRO' ? 'selected' : ''; ?>>Member Pro (Advanced)</option>
        <option value="API_MEMBER" <?php echo $plan === 'API_MEMBER' ? 'selected' : ''; ?>>API Member (Programmatic)</option>
      </select>
    </label>
    <button class="btn" type="submit">Continue to Checkout</button>
  </form>
  <p class="muted">After payment, your account is activated with the selected plan features.</p>
  <p><a class="btn btn-secondary" href="<?php echo e(app_url('pricing.php')); ?>">Compare Plans</a></p>
  <hr>
  <?php if ($googleOauthEnabled || $appleOauthEnabled || $facebookOauthEnabled): ?>
    <p>Or register with:</p>
    <?php if ($googleOauthEnabled): ?><p><a class="btn btn-secondary" href="<?php echo e(app_url('oauth/google/start.php?plan=' . urlencode($plan))); ?>">Google</a></p><?php endif; ?>
    <?php if ($appleOauthEnabled): ?><p><a class="btn btn-secondary" href="<?php echo e(app_url('oauth/apple/start.php?plan=' . urlencode($plan))); ?>">Apple</a></p><?php endif; ?>
    <?php if ($facebookOauthEnabled): ?><p><a class="btn btn-secondary" href="<?php echo e(app_url('oauth/facebook/start.php?plan=' . urlencode($plan))); ?>">Facebook</a></p><?php endif; ?>
  <?php endif; ?>
  <p><a href="<?php echo e(app_url('login.php')); ?>">Already have an account? Sign in</a></p>
</section>
<?php include __DIR__ . '/templates/footer.php'; ?>
