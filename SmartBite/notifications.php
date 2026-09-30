<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([user()['id']]);
    header('Location: notifications.php');
    exit;
}

$stmt = db()->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50');
$stmt->execute([user()['id']]);
$items = $stmt->fetchAll();

db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0')->execute([user()['id']]);

$title = 'Notifications';
require __DIR__ . '/includes/header.php';
?>
<div class="section-title">
  <h1>Notifications</h1>
  <?php if ($items): ?>
  <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><button class="btn secondary small" type="submit">Mark all as read</button></form>
  <?php endif; ?>
</div>
<?php if (!$items): ?>
  <div class="notice">Nothing here yet. We'll let you know as soon as something about your orders changes.</div>
<?php else: ?>
  <div class="table-wrap">
  <?php foreach ($items as $n): ?>
    <div class="notif-row<?= $n['is_read'] ? '' : ' unread' ?>">
      <div class="notif-title"><?= e($n['title']) ?></div>
      <div class="muted"><?= e($n['message']) ?></div>
      <div class="notif-time muted"><?= e(date('M d, g:i A', strtotime($n['created_at']))) ?></div>
    </div>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
