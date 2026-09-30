<?php
$title = 'SmartBite | School Canteen';
require_once __DIR__ . '/includes/header.php';
?>
<section class="hero">
  <img src="<?= APP_URL ?>/assets/img/logo.png" alt="SmartBite logo" style="width:88px;height:88px;background:#fff;border-radius:50%;padding:14px;margin-bottom:18px;box-shadow:0 2px 10px rgba(0,0,0,.15)">
  <span class="pill green">School Canteen Ordering System</span>
  <h1>Order ahead.<br><span>Eat on time.</span></h1>
  <p>SmartBite lets students browse the daily menu, order online, choose pickup times, and pay through PayMongo. Staff manage orders and admins monitor sales and inventory.</p>
  <div class="actions">
    <a class="btn" href="<?= APP_URL ?>/menu.php">Browse Menu</a>
    <?php if (!user()): ?><a class="btn secondary" href="<?= APP_URL ?>/register.php">Create Account</a><?php endif; ?>
  </div>
</section>
<div class="grid">
  <div class="card"><h3>🛒 Easy ordering</h3><p class="muted">Build a cart and place an order before recess or lunch.</p></div>
  <div class="card"><h3>💳 PayMongo</h3><p class="muted">Use a PayMongo Checkout Session for supported online payment methods.</p></div>
  <div class="card"><h3>📦 Order tracking</h3><p class="muted">Track Pending → Preparing → Ready → Completed.</p></div>
  <div class="card"><h3>📊 Management</h3><p class="muted">Staff and admins can manage menu, orders, inventory, and sales.</p></div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
