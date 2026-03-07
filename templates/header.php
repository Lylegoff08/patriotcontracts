<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
$config = app_config();
$user = current_user();
$baseUrl = app_base_url();
$siteName = site_setting(db(), 'site_name', 'PatriotContracts');
$isAdminUser = false;
if ($user) {
    $userRole = strtolower(trim((string) ($user['role'] ?? '')));
    $isAdminUser = in_array($userRole, ['admin', 'super_admin'], true);
}
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
    <a class="brand" href="<?php echo e(app_url('index.php')); ?>"><?php echo e($siteName); ?></a>
    <nav class="site-nav">
      <a class="nav-link<?php echo $isActive(['home.php']); ?>" href="<?php echo e(app_url('home.php')); ?>">Browse Listings</a>
      <a class="nav-link<?php echo $isActive(['search.php']); ?>" href="<?php echo e(app_url('search.php')); ?>">Search</a>
      <a class="nav-link<?php echo $isActive(['stats.php']); ?>" href="<?php echo e(app_url('stats.php')); ?>">Stats</a>
      <?php if ($user): ?>
        <a class="nav-link<?php echo $isActive(['dashboard.php']); ?>" href="<?php echo e(app_url('dashboard.php')); ?>">Dashboard</a>
        <a class="nav-link<?php echo $isActive(['account.php']); ?>" href="<?php echo e(app_url('account.php')); ?>">Account</a>
        <?php if ($isAdminUser): ?>
          <a class="nav-link<?php echo $isActive(['dashboard.php']); ?>" href="<?php echo e(app_url('admin/dashboard.php')); ?>">Admin CMS</a>
        <?php endif; ?>
        <a class="nav-link" href="<?php echo e(app_url('logout.php')); ?>">Logout</a>
      <?php else: ?>
        <a class="nav-link<?php echo $isActive(['pricing.php']); ?>" href="<?php echo e(app_url('pricing.php')); ?>">Pricing</a>
        <a class="nav-link<?php echo $isActive(['login.php']); ?>" href="<?php echo e(app_url('login.php')); ?>">Sign In</a>
        <a class="nav-link<?php echo $isActive(['register.php']); ?>" href="<?php echo e(app_url('register.php')); ?>">Create Account</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main class="container">
