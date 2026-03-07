<?php
require_once __DIR__ . '/../includes/auth.php';

logout_user();
$base = rtrim((string) (app_config()['app']['base_url'] ?? ''), '/');
header('Location: ' . $base . '/admin/login.php');
exit;