<?php include __DIR__ . '/templates/header.php'; ?>
<h1>API Docs</h1>
<section class="card">
  <?php $baseApi = app_url('api'); ?>
  <h2>Membership Requirement</h2>
  <p>API endpoints require an active <code>API_MEMBER</code> subscription and an active API key.</p>
  <p>Send API key via <code>api_key</code> query, <code>X-API-Key</code> header, or <code>Authorization: Bearer &lt;key&gt;</code>.</p>

  <h2>Endpoints</h2>
  <ul>
    <li><?php echo e($baseApi . '/contracts.php?category=defense&q=cyber'); ?></li>
    <li><?php echo e($baseApi . '/agencies.php?page=1'); ?></li>
    <li><?php echo e($baseApi . '/vendors.php?page=1'); ?></li>
    <li><?php echo e($baseApi . '/stats.php'); ?></li>
  </ul>

  <h2>Errors</h2>
  <ul>
    <li>401: missing/invalid key</li>
    <li>403: inactive account/membership or API plan required</li>
    <li>429: daily limit exceeded</li>
  </ul>
</section>
<?php include __DIR__ . '/templates/footer.php'; ?>
