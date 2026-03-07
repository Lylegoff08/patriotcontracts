<?php
$currentAdminPage = basename((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH));
$activeClass = static function (array $pages) use ($currentAdminPage): string {
    return in_array($currentAdminPage, $pages, true) ? 'is-active' : '';
};
$baseUrl = rtrim((string) (app_config()['app']['base_url'] ?? ''), '/');
?>
<aside class="admin-sidebar">
  <a class="admin-brand" href="<?php echo e($baseUrl); ?>/admin/dashboard.php">PatriotContracts Admin</a>
  <nav>
    <a class="<?php echo $activeClass(['dashboard.php']); ?>" href="<?php echo e($baseUrl); ?>/admin/dashboard.php">Dashboard</a>
    <?php if (admin_can_manage_moderation($adminUser)): ?>
      <a class="<?php echo $activeClass(['listings.php', 'listing_edit.php']); ?>" href="<?php echo e($baseUrl); ?>/admin/listings.php">Listings</a>
      <a class="<?php echo $activeClass(['grants.php', 'grant_edit.php']); ?>" href="<?php echo e($baseUrl); ?>/admin/grants.php">Grants</a>
    <?php endif; ?>
    <?php if (admin_can_manage_content($adminUser)): ?>
      <a class="<?php echo $activeClass(['pages.php', 'page_edit.php']); ?>" href="<?php echo e($baseUrl); ?>/admin/pages.php">Pages</a>
    <?php endif; ?>
    <?php if (admin_can_manage_settings($adminUser)): ?>
      <a class="<?php echo $activeClass(['settings.php']); ?>" href="<?php echo e($baseUrl); ?>/admin/settings.php">Settings</a>
    <?php endif; ?>
    <?php if (admin_can_manage_users($adminUser)): ?>
      <a class="<?php echo $activeClass(['users.php']); ?>" href="<?php echo e($baseUrl); ?>/admin/users.php">Users</a>
      <a class="<?php echo $activeClass(['activity_log.php']); ?>" href="<?php echo e($baseUrl); ?>/admin/activity_log.php">Activity Log</a>
    <?php endif; ?>
    <?php if (admin_can_view_ingestion($adminUser)): ?>
      <a class="<?php echo $activeClass(['health.php']); ?>" href="<?php echo e($baseUrl); ?>/admin/health.php">Health</a>
      <a class="<?php echo $activeClass(['logs.php']); ?>" href="<?php echo e($baseUrl); ?>/admin/logs.php">Logs</a>
      <a class="<?php echo $activeClass(['recategorize.php']); ?>" href="<?php echo e($baseUrl); ?>/admin/recategorize.php">Recategorize</a>
      <a class="<?php echo $activeClass(['run_ingest.php']); ?>" href="<?php echo e($baseUrl); ?>/admin/run_ingest.php">Run Ingest</a>
    <?php endif; ?>
  </nav>
</aside>
