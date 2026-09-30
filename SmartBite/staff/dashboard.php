<?php
require_once __DIR__.'/../includes/auth.php';require_role(['staff','admin']);
if($_SERVER['REQUEST_METHOD']==='POST'){
 check_csrf();$id=(int)$_POST['order_id'];$status=$_POST['status'];
 if(in_array($status,['pending','preparing','ready','completed','cancelled'],true)){
  db()->prepare("UPDATE orders SET status=? WHERE id=?")->execute([$status,$id]);
  $s=db()->prepare("SELECT user_id,order_number FROM orders WHERE id=?");$s->execute([$id]);$o=$s->fetch();
  if($o) db()->prepare("INSERT INTO notifications(user_id,title,message) VALUES(?,?,?)")->execute([$o['user_id'],'Order update','Order '.$o['order_number'].' is now '.$status.'.']);
  flash('success','Order updated.');
 }
 header('Location: dashboard.php');exit;
}
$orders=db()->query("SELECT o.*,u.name,ps.pickup_date,ps.pickup_time FROM orders o JOIN users u ON u.id=o.user_id LEFT JOIN pickup_slots ps ON ps.id=o.pickup_slot_id ORDER BY FIELD(o.status,'pending','preparing','ready','completed','cancelled'),o.created_at DESC")->fetchAll();
$title='Staff Dashboard';require __DIR__.'/../includes/header.php';
?>
<div class="section-title"><h1>Staff Dashboard</h1><a class="btn" href="menu.php">Manage Menu</a></div>
<div class="table-wrap"><table class="table"><tr><th>Order</th><th>Student</th><th>Pickup</th><th>Total</th><th>Status</th><th>Action</th></tr>
<?php foreach($orders as $o): ?><tr><td><?=e($o['order_number'])?></td><td><?=e($o['name'])?></td><td><?=e(date('M d',strtotime($o['pickup_date'])).' '.date('g:i A',strtotime($o['pickup_time'])))?></td><td>₱<?=number_format($o['total_amount'],2)?></td><td><span class="pill"><?=e($o['status'])?></span></td><td>
<form method="post" class="actions"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="order_id" value="<?=$o['id']?>"><select name="status"><option>pending</option><option>preparing</option><option>ready</option><option>completed</option><option>cancelled</option></select><button class="btn">Update</button></form>
</td></tr><?php endforeach;?></table></div>
<?php require __DIR__.'/../includes/footer.php'; ?>
