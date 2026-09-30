<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
if (empty($_SESSION['cart'])) { header('Location: cart.php'); exit; }

$slots = db()->query("SELECT * FROM pickup_slots WHERE is_active=1 AND pickup_date>=CURDATE() ORDER BY pickup_date,pickup_time")->fetchAll();
$ids = array_keys($_SESSION['cart']);
$ph = implode(',', array_fill(0, count($ids), '?'));
$s = db()->prepare("SELECT * FROM menu_items WHERE id IN ($ph) AND is_available=1");
$s->execute($ids);
$items = $s->fetchAll();
$total = 0;
foreach ($items as $i) $total += $i['price'] * $_SESSION['cart'][$i['id']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $slot = (int)$_POST['pickup_slot_id'];
    $method = $_POST['payment_method'] ?? 'paymongo';
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $s = $pdo->prepare("SELECT * FROM pickup_slots WHERE id=? AND is_active=1 FOR UPDATE");
        $s->execute([$slot]);
        $ps = $s->fetch();
        if (!$ps) throw new Exception('Invalid pickup slot.');

        $count = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE pickup_slot_id=? AND status <> 'cancelled'");
        $count->execute([$slot]);
        if ((int)$count->fetchColumn() >= (int)$ps['max_orders']) {
            throw new Exception('That pickup slot is full. Please choose another time.');
        }

        
        $lockStock = $pdo->prepare('SELECT stock_qty, name, is_available FROM menu_items WHERE id=? FOR UPDATE');
        foreach ($_SESSION['cart'] as $itemId => $qty) {
            $lockStock->execute([$itemId]);
            $row = $lockStock->fetch();
            if (!$row || !$row['is_available']) throw new Exception('An item in your cart is no longer available.');
            if ($qty > $row['stock_qty']) throw new Exception("Not enough stock left for {$row['name']}.");
        }

        $priced = $pdo->prepare("SELECT * FROM menu_items WHERE id IN ($ph)");
        $priced->execute($ids);
        $priceById = [];
        foreach ($priced->fetchAll() as $row) $priceById[$row['id']] = $row;

        $deduct = $pdo->prepare('UPDATE menu_items SET stock_qty = stock_qty - ? WHERE id=?');
        $inv = $pdo->prepare('UPDATE inventory SET stock_qty = stock_qty - ? WHERE menu_item_id=?');
        $recomputedTotal = 0;
        $lines = [];
        foreach ($_SESSION['cart'] as $itemId => $qty) {
            $unitPrice = $priceById[$itemId]['price'];
            $recomputedTotal += $unitPrice * $qty;
            $lines[] = ['id' => $itemId, 'qty' => $qty, 'unit_price' => $unitPrice];
            $deduct->execute([$qty, $itemId]);
            $inv->execute([$qty, $itemId]);
        }

        $orderNo = 'SB' . date('YmdHis') . strtoupper(bin2hex(random_bytes(2)));
        $pickupCode = generate_pickup_code($pdo);
        $s = $pdo->prepare("INSERT INTO orders (order_number,pickup_code,user_id,pickup_slot_id,status,total_amount) VALUES (?,?,?,?,'pending',?)");
        $s->execute([$orderNo, $pickupCode, user()['id'], $slot, $recomputedTotal]);
        $orderId = $pdo->lastInsertId();

        $oi = $pdo->prepare("INSERT INTO order_items (order_id,menu_item_id,quantity,unit_price,subtotal) VALUES (?,?,?,?,?)");
        foreach ($lines as $l) {
            $oi->execute([$orderId, $l['id'], $l['qty'], $l['unit_price'], $l['unit_price'] * $l['qty']]);
        }

        $pay = $pdo->prepare("INSERT INTO payments (order_id,method,status,amount) VALUES (?,?, 'pending',?)");
        $pay->execute([$orderId, $method === 'cash' ? 'cash' : 'paymongo', $recomputedTotal]);

        $pdo->commit();
        log_activity(user()['id'], 'create_order', $orderNo);
        $_SESSION['pending_order_id'] = (int)$orderId;

        if ($method === 'cash') {
            unset($_SESSION['cart']);
            flash('success', "Order $orderNo placed. Show pickup code $pickupCode at the counter and pay on pickup.");
            header('Location: student/orders.php');
            exit;
        }
        unset($_SESSION['cart']);
        header('Location: paymongo_checkout.php?order_id=' . $orderId);
        exit;
    } catch (Throwable $e) {
        $pdo->rollBack();
        flash('error', $e->getMessage());
        header('Location: checkout.php');
        exit;
    }
}
$title = 'Checkout';
require __DIR__ . '/includes/header.php';
?>
<h1>Checkout</h1>
<div class="two-col">
<div class="card">
<h3>Pickup time</h3>
<form method="post">
<input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
<div class="form-group"><label>Choose pickup slot</label><select name="pickup_slot_id" required>
<option value="">Select...</option>
<?php foreach ($slots as $s): ?><option value="<?= $s['id'] ?>"><?= e(date('M d, Y', strtotime($s['pickup_date'])) . ' • ' . date('g:i A', strtotime($s['pickup_time'])) . ' • ' . $s['label']) ?></option><?php endforeach; ?>
</select></div>
<div class="form-group"><label>Payment method</label><select name="payment_method"><option value="paymongo">PayMongo Checkout</option><option value="cash">Cash on Pickup</option></select></div>
<button class="btn" type="submit">Place Order &amp; Continue</button>
</form>
</div>
<div class="card"><h3>Order Summary</h3>
<?php foreach ($items as $i): ?><p><?= e($i['name']) ?> &times; <?= ($_SESSION['cart'][$i['id']]) ?> <b class="float"><?= peso($i['price'] * $_SESSION['cart'][$i['id']]) ?></b></p><?php endforeach; ?>
<hr><h2><?= peso($total) ?></h2></div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
