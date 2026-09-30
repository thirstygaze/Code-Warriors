<?php
require_once __DIR__ . '/includes/auth.php';
$stmt=db()->query("SELECT m.*, c.name category_name FROM menu_items m JOIN categories c ON c.id=m.category_id WHERE m.is_available=1 ORDER BY c.name,m.name");
$items=$stmt->fetchAll();
$title='Menu'; require __DIR__.'/includes/header.php';
?>
<div class="section-title"><div><h1>Daily Menu</h1><p class="muted">Available items are shown below.</p></div><a class="btn" href="cart.php">View Cart</a></div>
<div class="grid">
<?php foreach($items as $item): ?>
<div class="card">
<?php if($item['image_url']): ?><img class="menu-img" src="<?=e($item['image_url'])?>" alt="<?=e($item['name'])?>"><?php endif;?>
<h3><?=e($item['name'])?></h3><span class="pill"><?=e($item['category_name'])?></span>
<p class="muted"><?=e($item['description'])?></p>
<div class="price"><?=peso($item['price'])?></div>
<p><span class="pill <?=($item['stock_qty']>0?'green':'orange')?>"><?= $item['stock_qty']>0 ? 'Available' : 'Out of stock' ?></span></p>
<?php if(user() && $item['stock_qty']>0): ?>
<form method="post" action="cart.php" class="actions">
<input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<input type="hidden" name="action" value="add"><input type="hidden" name="item_id" value="<?=$item['id']?>">
<input type="number" name="qty" min="1" max="<?=e($item['stock_qty'])?>" value="1" style="width:80px">
<button class="btn">Add to Cart</button>
</form>
<?php elseif(!user()): ?><a class="btn" href="login.php">Login to Order</a><?php endif;?>
</div>
<?php endforeach;?>
</div>
<?php require __DIR__.'/includes/footer.php'; ?>
