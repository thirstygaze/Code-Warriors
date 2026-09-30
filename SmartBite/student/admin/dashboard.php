<?php
require_once __DIR__.'/../includes/auth.php';require_role(['admin']);
$pdo=db();
$users=$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$orders=$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$sales=$pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status<>'cancelled'")->fetchColumn();
$low=$pdo->query("SELECT COUNT(*) FROM menu_items WHERE stock_qty<=10")->fetchColumn();
$daily=$pdo->query("SELECT DATE(created_at) day,COUNT(*) orders,COALESCE(SUM(total_amount),0) sales FROM orders WHERE created_at>=CURDATE()-INTERVAL 6 DAY AND status<>'cancelled' GROUP BY DATE(created_at) ORDER BY day DESC")->fetchAll();
$title='Admin Dashboard';require __DIR__.'/../includes/header.php';
?>
<h1>Admin Dashboard</h1>
<div class="grid">
<div class="card"><div class="muted">Users</div><div class="stat"><?=$users?></div></div>
<div class="card"><div class="muted">Orders</div><div class="stat"><?=$orders?></div></div>
<div class="card"><div class="muted">Recorded Sales</div><div class="stat">₱<?=number_format($sales,2)?></div></div>
<div class="card"><div class="muted">Low Stock Items</div><div class="stat"><?=$low?></div></div>
</div>
<br><div class="card"><h3>Sales — Last 7 Days</h3><div class="table-wrap"><table class="table"><tr><th>Date</th><th>Orders</th><th>Sales</th></tr>
<?php foreach($daily as $d):?><tr><td><?=e($d['day'])?></td><td><?=$d['orders']?></td><td>₱<?=number_format($d['sales'],2)?></td></tr><?php endforeach;?>
</table></div></div>
<?php require __DIR__.'/../includes/footer.php'; ?>
