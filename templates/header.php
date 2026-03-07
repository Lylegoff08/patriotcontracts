<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
$config = app_config();
$user = current_user();
$baseUrl = rtrim((string) $config['app']['base_url'], '/');
$currentPage = basename((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH));
$isActive = static function (array $pages) use ($currentPage): string {
    return in_array($currentPage, $pages, true) ? ' is-active' : '';
};
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo e(page_title($config['app']['name'])); ?></title>
  <link rel="stylesheet" href="<?php echo e($baseUrl); ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?php echo e($baseUrl); ?>/assets/css/main.css">
</head>
<body>
<header class="site-header">
  <div class="container row">
    <a class="brand" href="<?php echo e($baseUrl); ?>/index.php">PatriotContracts</a>
    <nav class="site-nav">
      <a class="nav-link<?php echo $isActive(['home.php']); ?>" href="<?php echo e($baseUrl); ?>/home.php">Home</a>
      <a class="nav-link<?php echo $isActive(['search.php']); ?>" href="<?php echo e($baseUrl); ?>/search.php">Search</a>
      <a class="nav-link<?php echo $isActive(['stats.php']); ?>" href="<?php echo e($baseUrl); ?>/stats.php">Stats</a>
      <?php if ($user): ?>
        <a class="nav-link<?php echo $isActive(['dashboard.php']); ?>" href="<?php echo e($baseUrl); ?>/dashboard.php">Dashboard</a>
        <a class="nav-link<?php echo $isActive(['account.php']); ?>" href="<?php echo e($baseUrl); ?>/account.php">Account</a>
        <a class="nav-link" href="<?php echo e($baseUrl); ?>/logout.php">Logout</a>
      <?php else: ?>
        <a class="nav-link<?php echo $isActive(['pricing.php']); ?>" href="<?php echo e($baseUrl); ?>/pricing.php">Pricing</a>
        <a class="nav-link<?php echo $isActive(['login.php']); ?>" href="<?php echo e($baseUrl); ?>/login.php">Login</a>
        <a class="nav-link<?php echo $isActive(['register.php']); ?>" href="<?php echo e($baseUrl); ?>/register.php">Register</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main class="container">
