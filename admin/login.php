<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';

$admin = admin_current_user();
if ($admin) {
    $baseUrl = rtrim((string) (app_config()['app']['base_url'] ?? ''), '/');
    header('Location: ' . $baseUrl . '/admin/dashboard.php');
    exit;
}

$error = '';
$baseUrl = rtrim((string) (app_config()['app']['base_url'] ?? ''), '/');
$baseAdminPath = $baseUrl . '/admin/';
$next = request_str('next', $baseAdminPath . 'dashboard.php');
if ($next === '' || str_contains($next, '://') || str_starts_with($next, '//')) {
    $next = $baseAdminPath . 'dashboard.php';
}
if (!(str_starts_with($next, '/admin/') || str_starts_with($next, $baseAdminPath))) {
    $next = $baseAdminPath . 'dashboard.php';
}
if (str_starts_with($next, '/admin/')) {
    $next = $baseUrl . $next;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_post();
    [$ok, $msg] = login_user(request_str('email'), request_str('password'));
    if ($ok) {
        $admin = admin_current_user();
        if ($admin) {
            header('Location: ' . $next);
            exit;
        }
        logout_user();
        $error = 'Account does not have admin access.';
    } else {
        $error = $msg;
    }
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Login | PatriotContracts</title>
  <link rel="stylesheet" href="<?php echo e($baseUrl); ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?php echo e($baseUrl); ?>/assets/css/main.css">
  <link rel="stylesheet" href="<?php echo e($baseUrl); ?>/admin/assets/css/admin.css">
</head>
<body class="admin-body">
  <main class="container" style="max-width:520px;padding-top:2rem;">
    <section class="card">
      <h1>Admin Login</h1>
      <?php if ($error): ?><p class="warn"><?php echo e($error); ?></p><?php endif; ?>
      <form method="post" class="admin-form-block">
        <input type="hidden" name="_csrf" value="<?php echo e(csrf_token()); ?>">
        <input type="hidden" name="next" value="<?php echo e($next); ?>">
        <label>Email <input type="email" name="email" required></label>
        <label>Password <input type="password" name="password" required></label>
        <button class="btn" type="submit">Login</button>
      </form>
      <p class="muted"><a href="<?php echo e($baseUrl); ?>/login.php">Use member login page</a></p>
    </section>
  </main>
</body>
</html>
