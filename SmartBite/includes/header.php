<?php
require_once __DIR__ . '/auth.php';
$me = user();
$current = basename($_SERVER['SCRIPT_NAME']);
$inStaffArea = strpos($_SERVER['SCRIPT_NAME'], '/staff/') !== false;
function nav_active(string $file, string $current): string { return $file === $current ? ' active' : ''; }
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= isset($title) ? e($title) . ' | ' . APP_NAME : APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="icon" type="image/png" href="<?= APP_URL ?>/assets/img/logo.png">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body>
<header class="site-header">
  <div class="nav-wrap">
    <a class="brand" href="<?= APP_URL ?>/index.php"><img class="brand-mark" src="<?= APP_URL ?>/assets/img/logo.png" alt="">SmartBite</a>
    <ul class="nav-links">
      <li><a class="<?= (!$inStaffArea && $current === 'menu.php') ? ' active' : '' ?>" href="<?= APP_URL ?>/menu.php">Menu</a></li>
      <?php if ($me): ?>
        <li><a class="<?= nav_active('cart.php', $current) ?>" href="<?= APP_URL ?>/cart.php">Cart</a></li>
        <?php if ($me['role'] === 'student'): ?>
          <li><a class="<?= nav_active('orders.php', $current) ?>" href="<?= APP_URL ?>/student/orders.php">My Orders</a></li>
          <li><a class="<?= nav_active('account.php', $current) ?>" href="<?= APP_URL ?>/student/account.php">My Account</a></li>
        <?php endif; ?>
        <?php if (in_array($me['role'], ['staff', 'admin'], true)): ?>
          <li><a class="<?= ($inStaffArea && $current === 'index.php') ? ' active' : '' ?>" href="<?= APP_URL ?>/staff/index.php">Orders Board</a></li>
          <li><a class="<?= ($inStaffArea && $current === 'menu.php') ? ' active' : '' ?>" href="<?= APP_URL ?>/staff/menu.php">Manage Menu</a></li>
          <li><a class="<?= ($inStaffArea && $current === 'sales.php') ? ' active' : '' ?>" href="<?= APP_URL ?>/staff/sales.php">Sales</a></li>
        <?php endif; ?>
        <?php if ($me['role'] === 'admin'): ?>
          <li><a class="<?= ($inStaffArea && $current === 'users.php') ? ' active' : '' ?>" href="<?= APP_URL ?>/staff/users.php">Manage Users</a></li>
        <?php endif; ?>
      <?php endif; ?>
    </ul>
    <div class="nav-right">
      <?php if ($me):
        $unread = unread_notification_count($me['id']);
      ?>
        <a class="nav-bell" href="<?= APP_URL ?>/notifications.php" title="Notifications" aria-label="Notifications">
          🔔<?php if ($unread > 0): ?><span class="badge"><?= $unread > 9 ? '9+' : $unread ?></span><?php endif; ?>
        </a>
        <span class="nav-user"> <b><?= e(explode(' ', $me['name'])[0]) ?></b><?php if ($stall = staff_stall_name()): ?> · <?= e($stall) ?><?php endif; ?></span>
        <a class="btn secondary small" href="<?= APP_URL ?>/logout.php">Log out</a>
      <?php else: ?>
        <a class="btn secondary small" href="<?= APP_URL ?>/login.php">Log in</a>
        <a class="btn small" href="<?= APP_URL ?>/register.php">Sign up</a>
      <?php endif; ?>
    </div>
  </div>
</header>
<main class="container">
<?php foreach (get_flashes() as $f): ?>
  <div class="alert <?= e($f['type']) ?>"><?= e($f['message']) ?></div>
<?php endforeach; ?>
