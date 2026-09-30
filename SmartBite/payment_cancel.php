<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$orderId = (int)($_GET['order_id'] ?? 0);
$s = db()->prepare('SELECT * FROM orders WHERE id=? AND user_id=?');
$s->execute([$orderId, user()['id']]);
$order = $s->fetch();
$title = 'Payment Cancelled';
require __DIR__ . '/includes/header.php';
?>
<div class="card form-card">
<h2>Payment Cancelled</h2>
<p class="muted">No payment confirmation was recorded. Your order is still saved — you can pick up where you left off.</p>
<div class="actions">
  <?php if ($order): ?><a class="btn" href="<?= APP_URL ?>/paymongo_checkout.php?order_id=<?= (int)$order['id'] ?>">Try payment again</a><?php endif; ?>
  <a class="btn secondary" href="<?= APP_URL ?>/student/orders.php">My Orders</a>
</div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
