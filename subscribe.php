<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/stripe.php';
require_once __DIR__ . '/includes/security.php';

$user = require_login();

$plan = strtoupper(request_str('plan', 'MEMBER_BASIC'));
if (!in_array($plan, membership_plan_codes(), true)) {
    $plan = 'MEMBER_BASIC';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    include __DIR__ . '/templates/header.php';
    echo '<h1>Subscribe</h1><section class="card"><p>Invalid request.</p></section>';
    include __DIR__ . '/templates/footer.php';
    exit;
}

require_csrf_post();

try {
    $session = create_checkout_session(db(), (int) $user['id'], $plan);
    $url = (string) ($session['url'] ?? '');
    if ($url === '') {
        throw new RuntimeException('Stripe checkout URL missing.');
    }
    header('Location: ' . $url);
    exit;
} catch (Throwable $e) {
    $error = $e->getMessage();
    include __DIR__ . '/templates/header.php';
    ?>
    <h1>Subscribe</h1>
    <section class="card">
      <p class="warn">Unable to start checkout: <?php echo e($error); ?></p>
      <p><a href="pricing.php">Back to Pricing</a></p>
    </section>
    <?php
    include __DIR__ . '/templates/footer.php';
}
