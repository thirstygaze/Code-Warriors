<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$orderId = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare(
    "SELECT o.*, p.status payment_status, p.method, ps.pickup_date, ps.pickup_time, ps.label
     FROM orders o
     LEFT JOIN payments p ON p.order_id = o.id
     JOIN pickup_slots ps ON ps.id = o.pickup_slot_id
     WHERE o.id = ? AND o.user_id = ?"
);
$stmt->execute([$orderId, user()['id']]);
$order = $stmt->fetch();
if (!$order) { flash('error', 'Order not found.'); header('Location: orders.php'); exit; }

$stmt = db()->prepare(
    "SELECT oi.*, m.name FROM order_items oi JOIN menu_items m ON m.id = oi.menu_item_id WHERE oi.order_id = ?"
);
$stmt->execute([$orderId]);
$items = $stmt->fetchAll();

$steps = ['pending' => 'Order placed', 'preparing' => 'Preparing', 'ready' => 'Ready for pickup', 'completed' => 'Completed'];
$order_of = array_flip(array_keys($steps));
$title = 'Order ' . $order['order_number'];
require __DIR__ . '/../includes/header.php';
?>
<div class="section-title">
  <h1>Order <?= e($order['order_number']) ?></h1>
  <a class="btn secondary small" href="<?= APP_URL ?>/student/orders.php">Back to my orders</a>
</div>

<?php if ($order['status'] === 'cancelled'): ?>
  <div class="alert error">This order was cancelled.</div>
<?php else: ?>
  <ul class="order-steps">
  <?php foreach ($steps as $key => $label):
      $cls = $order_of[$key] < $order_of[$order['status']] ? 'done' : ($key === $order['status'] ? 'current' : '');
  ?>
    <li class="<?= $cls ?>"><?= e($label) ?></li>
  <?php endforeach; ?>
  </ul>
  <?php if (in_array($order['status'], ['pending', 'preparing', 'ready'], true)): ?>
    <p class="muted">Show this code at the counter when picking up:</p>
    <div class="pickup-code"><?= e($order['pickup_code']) ?></div>
  <?php endif; ?>
<?php endif; ?>

<div class="two-col">
  <div class="card">
    <h3>Items</h3>
    <div class="table-wrap">
    <table class="table">
    <tr><th>Item</th><th>Qty</th><th>Subtotal</th></tr>
    <?php foreach ($items as $i): ?>
      <tr><td><?= e($i['name']) ?></td><td><?= (int)$i['quantity'] ?></td><td><?= peso($i['subtotal']) ?></td></tr>
    <?php endforeach; ?>
    </table>
    </div>
    <div class="section-title"><span></span><h2><?= peso($order['total_amount']) ?></h2></div>
  </div>
  <div class="card">
    <h3>Pickup</h3>
    <p><?= e(date('M d, Y', strtotime($order['pickup_date']))) ?> &middot; <?= e(date('g:i A', strtotime($order['pickup_time']))) ?> &middot; <?= e($order['label']) ?></p>
    <h3>Payment</h3>
    <p>
      <span class="pill <?= $order['payment_status'] === 'paid' ? 'green' : 'orange' ?>"><?= e(ucfirst($order['payment_status'] ?? 'pending')) ?></span>
      via <?= e($order['method'] ?? 'paymongo') ?>
    </p>
    <?php if ($order['payment_status'] !== 'paid' && $order['status'] !== 'cancelled'): ?>
      <a class="btn small" href="<?= APP_URL ?>/paymongo_checkout.php?order_id=<?= (int)$order['id'] ?>">Pay now</a>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
