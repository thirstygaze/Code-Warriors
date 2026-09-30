<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['staff', 'admin']);

$today = date('Y-m-d');
$revenueToday = db()->prepare(
    "SELECT COALESCE(SUM(o.total_amount),0) FROM orders o JOIN payments p ON p.order_id=o.id
     WHERE p.status='paid' AND DATE(o.created_at)=?"
);
$revenueToday->execute([$today]);
$revenueToday = (float)$revenueToday->fetchColumn();

$revenueTotal = (float)db()->query(
    "SELECT COALESCE(SUM(o.total_amount),0) FROM orders o JOIN payments p ON p.order_id=o.id WHERE p.status='paid'"
)->fetchColumn();

$stmt = db()->prepare('SELECT COUNT(*) FROM orders WHERE DATE(created_at)=?');
$stmt->execute([$today]);
$ordersToday = (int)$stmt->fetchColumn();

$pendingCount = (int)db()->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn();

$topItems = db()->query(
    "SELECT m.name, SUM(oi.quantity) qty, SUM(oi.subtotal) revenue
     FROM order_items oi JOIN menu_items m ON m.id=oi.menu_item_id
     JOIN orders o ON o.id=oi.order_id
     WHERE o.status <> 'cancelled'
     GROUP BY m.id ORDER BY qty DESC LIMIT 8"
)->fetchAll();

$title = 'Sales Report';
require __DIR__ . '/../includes/header.php';
?>
<h1>Sales Report</h1>
<div class="stats">
  <div class="stat-tile"><div class="num"><?= peso($revenueToday) ?></div><div class="label">Revenue today (paid)</div></div>
  <div class="stat-tile"><div class="num"><?= peso($revenueTotal) ?></div><div class="label">Revenue all-time (paid)</div></div>
  <div class="stat-tile"><div class="num"><?= $ordersToday ?></div><div class="label">Orders today</div></div>
  <div class="stat-tile"><div class="num"><?= $pendingCount ?></div><div class="label">Pending orders</div></div>
</div>

<div class="section-title"><h2>Top-selling items</h2></div>
<div class="table-wrap">
<table class="table">
<tr><th>Item</th><th>Units sold</th><th>Revenue</th></tr>
<?php foreach ($topItems as $t): ?>
<tr><td><?= e($t['name']) ?></td><td><?= (int)$t['qty'] ?></td><td><?= peso($t['revenue']) ?></td></tr>
<?php endforeach; ?>
<?php if (!$topItems): ?><tr><td colspan="3" class="muted">No sales yet.</td></tr><?php endif; ?>
</table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
