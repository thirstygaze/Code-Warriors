<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
$me = user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $password = $_POST['password'] ?? '';
    $s = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
    $s->execute([$me['id']]);
    $hash = $s->fetchColumn();

    if (!$hash || !password_verify($password, $hash)) {
        flash('error', 'That password is incorrect. Your account was not deleted.');
        header('Location: account.php');
        exit;
    }

    $result = delete_or_deactivate_user((int)$me['id']);
    log_activity(null, 'self_' . $result, $me['email'] . ' (was user #' . $me['id'] . ')');
    $_SESSION = [];
    session_destroy();
    session_start();
    if ($result === 'deleted') {
        flash('success', 'Your account has been deleted. Come back any time!');
    } else {
        flash('success', 'Your account has order history, so it was deactivated instead of fully deleted. Contact the canteen if you need it reactivated.');
    }
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

$title = 'My Account';
require __DIR__ . '/../includes/header.php';
?>
<h1>My Account</h1>
<div class="two-col">
  <div class="card">
    <h3>Profile</h3>
    <p><b>Name:</b> <?= e($me['name']) ?></p>
    <p><b>Email:</b> <?= e($me['email']) ?></p>
    <?php if (!empty($me['student_id'])): ?><p><b>Student ID:</b> <?= e($me['student_id']) ?></p><?php endif; ?>
  </div>
  <div class="card">
    <h3>Delete my account</h3>
    <p class="muted">This can't be undone. If you have past orders on record, we'll deactivate your account instead of fully deleting it (so order history stays consistent), and you won't be able to log back in.</p>
    <form method="post" onsubmit="return confirm('Are you sure you want to delete your account?');">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <div class="form-group"><label>Confirm your password</label><input type="password" name="password" required></div>
      <button class="btn danger" type="submit">Delete my account</button>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
