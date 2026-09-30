<?php
require_once __DIR__.'/includes/auth.php'; require_login();
if (!isset($_SESSION['cart'])) $_SESSION['cart']=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
    check_csrf(); $action=$_POST['action']??'';
    if($action==='add'){
        $id=(int)$_POST['item_id']; $qty=max(1,(int)$_POST['qty']);
        $stmt=db()->prepare("SELECT stock_qty FROM menu_items WHERE id=? AND is_available=1");$stmt->execute([$id]);$i=$stmt->fetch();
        if($i){$_SESSION['cart'][$id]=min($i['stock_qty'],($_SESSION['cart'][$id]??0)+$qty);}
    } elseif($action==='remove'){unset($_SESSION['cart'][(int)$_POST['item_id']]);}
    elseif($action==='update'){
        foreach(($_POST['qty']??[]) as $id=>$qty){
            $id=(int)$id;$qty=(int)$qty;
            $s=db()->prepare("SELECT stock_qty FROM menu_items WHERE id=?");$s->execute([$id]);$stock=$s->fetchColumn();
            if(!$stock || $qty<=0) unset($_SESSION['cart'][$id]); else $_SESSION['cart'][$id]=min($qty,(int)$stock);
        }
    }
    header('Location: cart.php');exit;
}
$cart=[];$total=0;
if($_SESSION['cart']){
    $ids=array_keys($_SESSION['cart']);$ph=implode(',',array_fill(0,count($ids),'?'));
    $s=db()->prepare("SELECT * FROM menu_items WHERE id IN ($ph)");$s->execute($ids);
    foreach($s->fetchAll() as $i){$qty=$_SESSION['cart'][$i['id']];$sub=$qty*$i['price'];$i['qty']=$qty;$i['subtotal']=$sub;$cart[]=$i;$total+=$sub;}
}
$title='Cart';require __DIR__.'/includes/header.php';
?>
<h1>Your Cart</h1>
<?php if(!$cart): ?><div class="notice">Your cart is empty. <a href="menu.php">Browse the menu.</a></div>
<?php else: ?>
<form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="update">
<div class="table-wrap"><table class="table"><tr><th></th><th>Item</th><th>Price</th><th>Qty</th><th>Subtotal</th><th></th></tr>
<?php foreach($cart as $i): ?><tr><td><?php if($i['image_url']): ?><img src="<?=e($i['image_url'])?>" alt="" style="width:44px;height:44px;border-radius:8px;object-fit:cover"><?php endif; ?></td><td><?=e($i['name'])?></td><td><?=peso($i['price'])?></td><td><input type="number" name="qty[<?=$i['id']?>]" min="0" max="<?=$i['stock_qty']?>" value="<?=$i['qty']?>"></td><td><?=peso($i['subtotal'])?></td><td><button class="btn danger" type="submit" formaction="cart_remove.php" name="item_id" value="<?=$i['id']?>">Remove</button></td></tr><?php endforeach;?>
</table></div>
<div class="section-title"><button class="btn secondary" type="submit">Update Cart</button><h2>Total: <?=peso($total)?></h2></div>
</form>
<form method="get" action="checkout.php"><button class="btn" type="submit">Proceed to Checkout</button></form>
<?php endif; ?>
<?php require __DIR__.'/includes/footer.php'; ?>
