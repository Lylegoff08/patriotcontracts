<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$user = current_user();
$pageTitle = 'PatriotContracts | Billing Success';
include __DIR__ . '/../templates/header.php';
?>
<h1>Payment Processing</h1>
<section class="card">
  <p>Stripe checkout completed. Membership activation is processed by webhook and may take a few seconds.</p>
  <?php if ($user): ?>
    <p><a class="btn" href="<?php echo e(app_url('dashboard.php')); ?>">Go to Dashboard</a></p>
  <?php else: ?>
    <p><a class="btn" href="<?php echo e(app_url('login.php')); ?>">Login</a></p>
  <?php endif; ?>
</section>
<?php include __DIR__ . '/../templates/footer.php'; ?>
