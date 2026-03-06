<?php
$pageTitle = 'PatriotContracts | Billing Cancelled';
include __DIR__ . '/../templates/header.php';
?>
<h1>Checkout Cancelled</h1>
<section class="card">
  <p>No subscription changes were applied.</p>
  <p><a class="btn" href="<?php echo e(app_config()['app']['base_url']); ?>/pricing.php">Back to Pricing</a></p>
</section>
<?php include __DIR__ . '/../templates/footer.php'; ?>
