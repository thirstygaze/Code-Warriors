<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$stmt = db()->prepare(
    "SELECT o.*, p.status payment_status, ps.pickup_date, ps.pickup_time, ps.label
     FROM orders o
     LEFT JOIN payments p ON p.order_id = o.id
     JOIN pickup_slots ps ON ps.id = o.pickup_slot_id
     WHERE o.user_id = ?
     ORDER BY o.created_at DESC"
);
$stmt->execute([user()['id']]);
$orders = $stmt->fetchAll();

$title = 'My Orders';
require __DIR__ . '/../includes/header.php';
?>
<h1>My Orders</h1>
<?php if (!$orders): ?>
  <div class="notice">You haven't placed any orders yet. <a href="<?= APP_URL ?>/menu.php">Browse the menu</a> to get started.</div>
<?php else: ?>
<div class="table-wrap">
<table class="table">
<tr><th>Order #</th><th>Pickup</th><th>Code</th><th>Total</th><th>Payment</th><th>Status</th><th></th></tr>
<?php foreach ($orders as $o): ?>
<tr>
  <td><?= e($o['order_number']) ?></td>
  <td><?= e(date('M d, g:i A', strtotime($o['pickup_date'] . ' ' . $o['pickup_time']))) ?> &middot; <?= e($o['label']) ?></td>
  <td><?php if ($o['status'] !== 'cancelled'): ?><span class="pickup-code small"><?= e($o['pickup_code']) ?></span><?php else: ?><span class="muted">&mdash;</span><?php endif; ?></td>
  <td><?= peso($o['total_amount']) ?></td>
  <td><span class="<?= status_badge_class($o['payment_status'] === 'paid' ? 'ready' : 'pending') ?>"><?= e(ucfirst($o['payment_status'] ?? 'pending')) ?></span></td>
  <td><span class="<?= status_badge_class($o['status']) ?>"><?= e(ucfirst($o['status'])) ?></span></td>
  <td><a class="btn secondary small" href="<?= APP_URL ?>/student/order.php?id=<?= (int)$o['id'] ?>">View</a></td>
</tr>
<?php endforeach; ?>
</table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
