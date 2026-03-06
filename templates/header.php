<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
$config = app_config();
$user = current_user();
$baseUrl = rtrim((string) $config['app']['base_url'], '/');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo e(page_title($config['app']['name'])); ?></title>
  <link rel="stylesheet" href="<?php echo e($baseUrl); ?>/assets/css/style.css">
</head>
<body>
<header class="site-header">
  <div class="container row">
    <a class="brand" href="<?php echo e($baseUrl); ?>/index.php">PatriotContracts</a>
    <nav>
      <a href="<?php echo e($baseUrl); ?>/index.php">Home</a>
      <a href="<?php echo e($baseUrl); ?>/search.php">Search</a>
      <a href="<?php echo e($baseUrl); ?>/stats.php">Stats</a>
      <?php if ($user): ?>
        <a href="<?php echo e($baseUrl); ?>/dashboard.php">Dashboard</a>
        <a href="<?php echo e($baseUrl); ?>/account.php">Account</a>
        <a href="<?php echo e($baseUrl); ?>/logout.php">Logout</a>
      <?php else: ?>
        <a href="<?php echo e($baseUrl); ?>/pricing.php">Pricing</a>
        <a href="<?php echo e($baseUrl); ?>/login.php">Login</a>
        <a href="<?php echo e($baseUrl); ?>/register.php">Register</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main class="container">
