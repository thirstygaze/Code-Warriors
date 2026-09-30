<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['staff', 'admin']);

$myCategoryId = staff_category_id(); // null = admin or "general" staff (unrestricted)

function sync_inventory(PDO $pdo, int $menuItemId, int $stock): void
{
    $s = $pdo->prepare('SELECT id FROM inventory WHERE menu_item_id = ?');
    $s->execute([$menuItemId]);
    if ($s->fetch()) {
        $pdo->prepare('UPDATE inventory SET stock_qty = ? WHERE menu_item_id = ?')->execute([$stock, $menuItemId]);
    } else {
        $pdo->prepare('INSERT INTO inventory (menu_item_id, stock_qty) VALUES (?,?)')->execute([$menuItemId, $stock]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';
    $pdo = db();

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $price = (float)($_POST['price'] ?? 0);
        $stock = max(0, (int)($_POST['stock_qty'] ?? 0));
        $desc = trim($_POST['description'] ?? '');

        if ($name === '' || $categoryId <= 0 || $price <= 0) {
            flash('error', 'Please fill in name, category, and a valid price.');
        } elseif (!can_manage_category($categoryId)) {
            flash('error', "You can only add items to your assigned category.");
        } else {
            try {
                $imageUrl = handle_image_upload('image_file');
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
                header('Location: menu.php');
                exit;
            }
            $pdo->prepare('INSERT INTO menu_items (category_id,name,description,price,stock_qty,image_url,is_available) VALUES (?,?,?,?,?,?,1)')
                ->execute([$categoryId, $name, $desc, $price, $stock, $imageUrl]);
            $newId = (int)$pdo->lastInsertId();
            sync_inventory($pdo, $newId, $stock);
            log_activity(user()['id'], 'menu_create', $name);
            flash('success', "Added \"$name\" to the menu.");
        }
    } elseif ($action === 'update_all') {
        $rows = $_POST['items'] ?? [];
        $stmt = $pdo->prepare('UPDATE menu_items SET price=?, stock_qty=?, is_available=? WHERE id=?');
        $imgStmt = $pdo->prepare('UPDATE menu_items SET image_url=? WHERE id=?');
        $catLookup = $pdo->prepare('SELECT category_id FROM menu_items WHERE id=?');
        foreach ($rows as $id => $row) {
            $id = (int)$id;
            $catLookup->execute([$id]);
            $itemCategoryId = (int)$catLookup->fetchColumn();
            if (!$itemCategoryId || !can_manage_category($itemCategoryId)) continue; // not yours, skip silently

            $price = (float)($row['price'] ?? 0);
            $stock = max(0, (int)($row['stock_qty'] ?? 0));
            $available = isset($row['is_available']) ? 1 : 0;
            $stmt->execute([$price, $stock, $available, $id]);
            sync_inventory($pdo, $id, $stock);
            try {
                $newImage = handle_image_upload("item_image_$id");
                if ($newImage) $imgStmt->execute([$newImage, $id]);
            } catch (Throwable $e) {
                flash('error', "Item #$id: " . $e->getMessage());
            }
        }
        log_activity(user()['id'], 'menu_update', count($rows) . ' item(s) updated');
        flash('success', 'Menu updated.');
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $s = $pdo->prepare('SELECT category_id, name FROM menu_items WHERE id=?');
        $s->execute([$id]);
        $item = $s->fetch();
        if (!$item) {
            flash('error', 'Item not found.');
        } elseif (!can_manage_category((int)$item['category_id'])) {
            flash('error', "You can only delete items in your assigned category.");
        } else {
            try {
                $pdo->prepare('DELETE FROM menu_items WHERE id=?')->execute([$id]);
                log_activity(user()['id'], 'menu_delete', $item['name']);
                flash('success', "Deleted \"{$item['name']}\".");
            } catch (Throwable $e) {
                // FK restrict: this item appears in past order_items.
                flash('error', "Can't delete \"{$item['name']}\" - it's part of past orders. Mark it unavailable instead.");
            }
        }
        header('Location: menu.php');
        exit;
    }
    header('Location: menu.php');
    exit;
}

$categories = db()->query('SELECT * FROM categories ORDER BY name')->fetchAll();
$myCategoryName = null;
if ($myCategoryId !== null) {
    foreach ($categories as $c) if ((int)$c['id'] === $myCategoryId) $myCategoryName = $c['name'];
}

$itemsSql = "SELECT m.*, c.name category_name, COALESCE(i.low_stock_threshold,10) low_stock_threshold
     FROM menu_items m JOIN categories c ON c.id=m.category_id
     LEFT JOIN inventory i ON i.menu_item_id=m.id";
if ($myCategoryId !== null) {
    $stmt = db()->prepare($itemsSql . ' WHERE m.category_id = ? ORDER BY m.name');
    $stmt->execute([$myCategoryId]);
    $items = $stmt->fetchAll();
} else {
    $items = db()->query($itemsSql . ' ORDER BY c.name, m.name')->fetchAll();
}

$title = 'Manage Menu';
require __DIR__ . '/../includes/header.php';
?>
<div class="section-title">
  <h1>Manage Menu</h1>
  <?php if ($myCategoryName): ?><span class="pill green"><?= staff_stall_name() ? e(staff_stall_name()) . ' · ' : '' ?>You manage: <?= e($myCategoryName) ?></span><?php elseif (staff_stall_name()): ?><span class="pill green"><?= e(staff_stall_name()) ?></span><?php endif; ?>
</div>

<div class="card">
<h3>Add a new item</h3>
<form method="post" class="two-col" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
<input type="hidden" name="action" value="create">
<div>
  <div class="form-group"><label>Name</label><input type="text" name="name" required></div>
  <div class="form-group"><label>Category</label>
  <?php if ($myCategoryId !== null): ?>
    <input type="text" value="<?= e($myCategoryName) ?>" disabled>
    <input type="hidden" name="category_id" value="<?= $myCategoryId ?>">
  <?php else: ?>
    <select name="category_id" required>
      <option value="">Select...</option>
      <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
    </select>
  <?php endif; ?>
  </div>
</div>
<div>
  <div class="form-group"><label>Description</label><textarea name="description" rows="3"></textarea></div>
  <div class="form-group"><label>Photo <span class="muted">(JPG, PNG, or WEBP, max 3MB)</span></label><input type="file" name="image_file" accept="image/jpeg,image/png,image/webp"></div>
  <div class="form-group"><label>Price (₱)</label><input type="number" step="0.01" min="0" name="price" required></div>
  <div class="form-group"><label>Starting stock</label><input type="number" min="0" name="stock_qty" value="0"></div>
  <button class="btn" type="submit">Add item</button>
</div>
</form>
</div>

<div class="section-title"><h2>Current items</h2><button class="btn secondary small" type="submit" form="menu-update-form">Save all changes</button></div>
<form method="post" id="menu-update-form" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
<input type="hidden" name="action" value="update_all">
<div class="table-wrap">
<table class="table">
<tr><th>Photo</th><th>Item</th><th>Category</th><th>Price</th><th>Stock</th><th>Available</th><th></th></tr>
<?php foreach ($items as $i): ?>
<tr>
  <td>
    <img src="<?= e($i['image_url'] ?: (APP_URL . '/assets/img/logo.svg')) ?>" alt="" style="width:40px;height:40px;border-radius:8px;object-fit:cover;display:block;margin-bottom:6px">
    <input type="file" name="item_image_<?= $i['id'] ?>" accept="image/jpeg,image/png,image/webp" style="width:150px;font-size:.75rem">
  </td>
  <td><?= e($i['name']) ?><?php if ($i['stock_qty'] <= $i['low_stock_threshold']): ?> <span class="pill orange">Low stock</span><?php endif; ?></td>
  <td><?= e($i['category_name']) ?></td>
  <td><input type="number" step="0.01" min="0" name="items[<?= $i['id'] ?>][price]" value="<?= e($i['price']) ?>" style="width:100px"></td>
  <td><input type="number" min="0" name="items[<?= $i['id'] ?>][stock_qty]" value="<?= (int)$i['stock_qty'] ?>" style="width:80px"></td>
  <td><input type="checkbox" name="items[<?= $i['id'] ?>][is_available]" <?= $i['is_available'] ? 'checked' : '' ?>></td>
  <td><button class="btn danger small" type="submit" form="delete-<?= $i['id'] ?>" onclick="return confirm('Delete <?= e(addslashes($i['name'])) ?>? This can\'t be undone.')">Delete</button></td>
</tr>
<?php endforeach; ?>
</table>
</div>
</form>
<?php foreach ($items as $i): ?>
<form method="post" id="delete-<?= $i['id'] ?>">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="id" value="<?= $i['id'] ?>">
</form>
<?php endforeach; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
