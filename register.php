<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/stripe.php';

if (current_user()) {
    header('Location: dashboard.php');
    exit;
}

$plan = strtoupper(request_str('plan', 'MEMBER_BASIC'));
if (!in_array($plan, membership_plan_codes(), true)) {
    $plan = 'MEMBER_BASIC';
}

$error = '';
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
<h1>Register</h1>
<section class="card">
  <?php if ($error): ?><p class="warn"><?php echo e($error); ?></p><?php endif; ?>
  <p>Choose a paid plan to unlock member tools. Free browsing is always available without an account.</p>
  <form method="post" class="form">
    <input type="hidden" name="_csrf" value="<?php echo e(csrf_token()); ?>">
    <label>Full name <input type="text" name="full_name" value="<?php echo e(request_str('full_name')); ?>"></label>
    <label>Email <input type="email" name="email" required value="<?php echo e(request_str('email')); ?>"></label>
    <label>Password <input type="password" name="password" required minlength="8"></label>
    <label>Plan
      <select name="plan">
        <option value="MEMBER_BASIC" <?php echo $plan === 'MEMBER_BASIC' ? 'selected' : ''; ?>>Member Basic</option>
        <option value="MEMBER_PRO" <?php echo $plan === 'MEMBER_PRO' ? 'selected' : ''; ?>>Member Pro</option>
        <option value="API_MEMBER" <?php echo $plan === 'API_MEMBER' ? 'selected' : ''; ?>>API Member</option>
      </select>
    </label>
    <button class="btn" type="submit">Continue to Checkout</button>
  </form>
  <hr>
  <p>Or continue with:</p>
  <p><a class="btn btn-secondary" href="oauth/google/start.php?plan=<?php echo e($plan); ?>">Google</a></p>
  <p><a class="btn btn-secondary" href="oauth/apple/start.php?plan=<?php echo e($plan); ?>">Apple</a></p>
  <p><a class="btn btn-secondary" href="oauth/facebook/start.php?plan=<?php echo e($plan); ?>">Facebook</a></p>
  <p><a href="login.php">Already have an account?</a></p>
</section>
<?php include __DIR__ . '/templates/footer.php'; ?>
