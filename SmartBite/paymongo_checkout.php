<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$orderId = (int)($_GET['order_id'] ?? 0);
$s = db()->prepare("SELECT o.*,u.name,u.email,p.status payment_status FROM orders o JOIN users u ON u.id=o.user_id LEFT JOIN payments p ON p.order_id=o.id WHERE o.id=? AND o.user_id=?");
$s->execute([$orderId, user()['id']]);
$order = $s->fetch();
if (!$order) { flash('error', 'Order not found.'); header('Location: student/orders.php'); exit; }

if ($order['payment_status'] === 'paid') {
    header('Location: payment_success.php?order_id=' . $orderId);
    exit;
}

$s = db()->prepare("SELECT oi.*,m.name FROM order_items oi JOIN menu_items m ON m.id=oi.menu_item_id WHERE oi.order_id=?");
$s->execute([$orderId]);
$items = $s->fetchAll();

$line = [];
foreach ($items as $i) {
    $line[] = [
        'currency' => 'PHP',
        'amount' => (int)round($i['unit_price'] * 100),
        'name' => $i['name'],
        'quantity' => (int)$i['quantity'],
        'description' => 'SmartBite order ' . $order['order_number'],
    ];
}
$payload = ['data' => ['attributes' => [
    'line_items' => $line,
    'payment_method_types' => ['card', 'gcash', 'paymaya'],
    'success_url' => APP_URL . '/payment_success.php?order_id=' . $orderId,
    'cancel_url' => APP_URL . '/payment_cancel.php?order_id=' . $orderId,
    'description' => 'SmartBite ' . $order['order_number'],
    'reference_number' => $order['order_number'],
    'send_email_receipt' => false,
    'show_description' => true,
    'show_line_items' => true,
    'billing' => ['name' => $order['name'], 'email' => $order['email']],
]]];

$ch = curl_init('https://api.paymongo.com/v1/checkout_sessions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':'),
    ],
    CURLOPT_TIMEOUT => 30,
]);
$response = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);
$data = json_decode($response, true);

if ($err || $http >= 400 || empty($data['data']['attributes']['checkout_url'])) {
    log_activity(user()['id'], 'paymongo_error', $response ?: $err);
    $title = 'Payment error';
    require __DIR__ . '/includes/header.php';
    ?>
    <div class="card form-card">
      <h2>We couldn't start PayMongo checkout</h2>
      <p class="muted">Your order <b><?= e($order['order_number']) ?></b> was saved but payment hasn't started yet. Please try again in a moment, or choose cash on pickup instead.</p>
      <div class="actions">
        <a class="btn" href="<?= APP_URL ?>/paymongo_checkout.php?order_id=<?= $orderId ?>">Try again</a>
        <a class="btn secondary" href="<?= APP_URL ?>/student/orders.php">My Orders</a>
      </div>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

$checkoutUrl = $data['data']['attributes']['checkout_url'];
$sessionId = $data['data']['id'];
$s = db()->prepare("UPDATE payments SET provider='paymongo',provider_reference=? WHERE order_id=?");
$s->execute([$sessionId, $orderId]);
header('Location: ' . $checkoutUrl);
exit;
