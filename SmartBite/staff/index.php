<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['staff', 'admin']);

$next = ['pending' => 'preparing', 'preparing' => 'ready'];
$next_label = ['pending' => 'Start preparing', 'preparing' => 'Mark ready'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $orderId = (int)($_POST['order_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $pdo = db();

    $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
    $pdo->beginTransaction();
    try {
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order) throw new Exception('Order not found.');

        if ($action === 'advance' && isset($next[$order['status']])) {
            $to = $next[$order['status']];
            $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$to, $orderId]);
            log_activity(user()['id'], 'order_status', "Order {$order['order_number']} -> $to");
            $friendly = $to === 'ready' ? 'ready for pickup' : $to;
            notify((int)$order['user_id'], 'Order update', "Your order {$order['order_number']} is now $friendly.");
        } elseif ($action === 'complete' && $order['status'] === 'ready') {
            $code = strtoupper(trim($_POST['code'] ?? ''));
            if ($code === '' || $code !== $order['pickup_code']) {
                throw new Exception('That pickup code does not match this order. Ask the student to double-check it.');
            }
            $pdo->prepare("UPDATE orders SET status = 'completed' WHERE id = ?")->execute([$orderId]);
            log_activity(user()['id'], 'order_status', "Order {$order['order_number']} -> completed (code verified)");
            notify((int)$order['user_id'], 'Order picked up', "Order {$order['order_number']} was marked completed. Enjoy!");
        } elseif ($action === 'cancel' && in_array($order['status'], ['pending', 'preparing'], true)) {
            $items = $pdo->prepare('SELECT menu_item_id, quantity FROM order_items WHERE order_id = ?');
            $items->execute([$orderId]);
            $restore = $pdo->prepare('UPDATE menu_items SET stock_qty = stock_qty + ? WHERE id = ?');
            foreach ($items->fetchAll() as $it) {
                $restore->execute([$it['quantity'], $it['menu_item_id']]);
            }
            $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?")->execute([$orderId]);
            log_activity(user()['id'], 'order_cancel', "Order {$order['order_number']} cancelled, stock restored");
            notify((int)$order['user_id'], 'Order cancelled', "Your order {$order['order_number']} was cancelled. Any payment will be refunded/held per school policy.");
        }
        $pdo->commit();
        flash('success', 'Order updated.');
    } catch (Throwable $e) {
        $pdo->rollBack();
        flash('error', $e->getMessage());
    }
    header('Location: index.php');
    exit;
}

$stmt = db()->query(
    "SELECT o.*, u.name customer_name, p.status payment_status, ps.pickup_time, ps.label
     FROM orders o
     JOIN users u ON u.id = o.user_id
     LEFT JOIN payments p ON p.order_id = o.id
     JOIN pickup_slots ps ON ps.id = o.pickup_slot_id
     WHERE o.status <> 'cancelled'
     ORDER BY o.created_at ASC"
);
$all = $stmt->fetchAll();

$itemsByOrder = [];
if ($all) {
    $ids = array_column($all, 'id');
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $s = db()->prepare("SELECT oi.order_id, oi.quantity, m.name FROM order_items oi JOIN menu_items m ON m.id = oi.menu_item_id WHERE oi.order_id IN ($ph)");
    $s->execute($ids);
    foreach ($s->fetchAll() as $row) $itemsByOrder[$row['order_id']][] = $row;
}

$columns = ['pending' => 'Pending', 'preparing' => 'Preparing', 'ready' => 'Ready', 'completed' => 'Completed'];
$byStatus = array_fill_keys(array_keys($columns), []);
foreach ($all as $o) $byStatus[$o['status']][] = $o;

$title = 'Orders Board';
require __DIR__ . '/../includes/header.php';
?>
<div class="section-title"><h1>Orders Board</h1><?php if ($stall = staff_stall_name()): ?><span class="pill green"><?= e($stall) ?></span><?php endif; ?><span class="muted">Every staff member can manage any order here, regardless of category. Cancelling restores stock automatically. Completing an order requires the student's pickup code.</span></div>
<div class="kanban">
<?php foreach ($columns as $key => $label): ?>
  <div class="kanban-col">
    <h3><?= e($label) ?> <span class="count"><?= count($byStatus[$key]) ?></span></h3>
    <?php foreach ($byStatus[$key] as $o): ?>
      <div class="order-ticket">
        <div class="num"><?= e($o['order_number']) ?></div>
        <div class="muted"><?= e($o['customer_name']) ?> &middot; <?= e(date('g:i A', strtotime($o['pickup_time']))) ?> <?= e($o['label']) ?></div>
        <ul>
        <?php foreach (($itemsByOrder[$o['id']] ?? []) as $it): ?>
          <li><?= (int)$it['quantity'] ?>&times; <?= e($it['name']) ?></li>
        <?php endforeach; ?>
        </ul>
        <div><?= peso($o['total_amount']) ?> &middot; <span class="pill <?= $o['payment_status'] === 'paid' ? 'green' : 'orange' ?>"><?= e(ucfirst($o['payment_status'] ?? 'pending')) ?></span></div>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
          <?php if (isset($next[$key])): ?>
            <button class="btn small" type="submit" name="action" value="advance"><?= e($next_label[$key]) ?></button>
          <?php endif; ?>
          <?php if ($key === 'ready'): ?>
            <input type="text" name="code" placeholder="Pickup code" maxlength="6" style="width:90px;text-transform:uppercase" required>
            <button class="btn small" type="submit" name="action" value="complete">Verify &amp; complete</button>
          <?php endif; ?>
          <?php if (in_array($key, ['pending', 'preparing'], true)): ?>
            <button class="btn danger small" type="submit" name="action" value="cancel" onclick="return confirm('Cancel this order and restore stock?')">Cancel</button>
          <?php endif; ?>
        </form>
      </div>
    <?php endforeach; ?>
    <?php if (!$byStatus[$key]): ?><p class="muted">Nothing here.</p><?php endif; ?>
  </div>
<?php endforeach; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
