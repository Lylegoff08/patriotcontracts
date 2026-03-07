<?php include __DIR__ . '/templates/header.php'; ?>
<h1>API Access</h1>
<section class="card">
  <ol>
    <li>Subscribe to the <strong>API_MEMBER</strong> plan.</li>
    <li>After payment is active, login and open Dashboard.</li>
    <li>Generate an API key (shown once on creation).</li>
    <li>Use the key against <code><?php echo e(app_url('api')); ?>/*</code> endpoints.</li>
  </ol>
  <p class="muted">API usage is logged and daily request limits are enforced server-side.</p>
</section>
<?php include __DIR__ . '/templates/footer.php'; ?>
