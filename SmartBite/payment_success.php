<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$orderId = (int)($_GET['order_id'] ?? 0);
$s = db()->prepare("SELECT o.*,p.provider_reference,p.status payment_status FROM orders o LEFT JOIN payments p ON p.order_id=o.id WHERE o.id=? AND o.user_id=?");
$s->execute([$orderId, user()['id']]);
$order = $s->fetch();

if ($order && $order['payment_status'] === 'paid') {
    unset($_SESSION['cart']);
}

$title = 'Payment Result';
require __DIR__ . '/includes/header.php';
?>
<div class="card form-card">
<h2>Payment Return</h2>
<?php if ($order && $order['payment_status'] === 'paid'): ?>
  <div class="alert success">Payment confirmed. Order <?= e($order['order_number']) ?> is now in the queue.</div>
  <p class="muted">Show this code at the canteen counter when you pick up your order:</p>
  <div class="pickup-code"><?= e($order['pickup_code']) ?></div>
  <a class="btn" href="<?= APP_URL ?>/student/orders.php">View My Orders</a>
<?php elseif ($order): ?>
  <div class="alert" id="verifying-alert">You returned from PayMongo. Payment is being verified. Your order number is <b><?= e($order['order_number']) ?></b>.</div>
  <p class="muted" id="verifying-note">This page checks automatically every few seconds — no need to refresh.</p>
  <a class="btn secondary" href="<?= APP_URL ?>/student/orders.php">View My Orders</a>
  <script>
    
    let attempts = 0;
    const timer = setInterval(() => {
      attempts++;
      if (attempts > 20) { clearInterval(timer); return; }
      fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.text())
        .then(html => { if (html.includes('Payment confirmed')) window.location.reload(); })
        .catch(() => {});
    }, 3000);
  </script>
<?php else: ?>
  <div class="alert error">Order not found.</div>
  <a class="btn" href="<?= APP_URL ?>/student/orders.php">View My Orders</a>
<?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
