<?php
if (!isset($adminUser) || !is_array($adminUser)) {
    $adminUser = require_admin_auth();
}
$config = app_config();
$baseUrl = rtrim((string) ($config['app']['base_url'] ?? ''), '/');
$adminTitle = isset($adminTitle) && is_string($adminTitle) ? $adminTitle : 'Admin';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo e($adminTitle . ' | PatriotContracts Admin'); ?></title>
  <link rel="stylesheet" href="<?php echo e($baseUrl); ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?php echo e($baseUrl); ?>/assets/css/main.css">
  <link rel="stylesheet" href="<?php echo e($baseUrl); ?>/admin/assets/css/admin.css">
</head>
<body class="admin-body">
<div class="admin-shell">
  <?php include __DIR__ . '/admin_sidebar.php'; ?>
  <div class="admin-main">
    <header class="admin-topbar">
      <div>
        <strong><?php echo e($adminTitle); ?></strong>
      </div>
      <div class="muted">
        <?php echo e((string) ($adminUser['full_name'] ?: $adminUser['email'])); ?>
        (<?php echo e((string) $adminUser['effective_admin_role']); ?>)
        <a href="<?php echo e($baseUrl); ?>/admin/logout.php">Logout</a>
      </div>
    </header>
    <main class="admin-content">
