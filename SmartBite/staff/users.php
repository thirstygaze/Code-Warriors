<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin']);

$categories = db()->query('SELECT * FROM categories ORDER BY name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';
    $pdo = db();

    if ($action === 'create_account') {
        $role = ($_POST['role'] ?? '') === 'staff' ? 'staff' : 'student';
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $studentId = trim($_POST['student_id'] ?? '');
        $catRaw = $_POST['assigned_category_id'] ?? '';
        $assignedCategory = ($role === 'staff' && $catRaw !== '') ? (int)$catRaw : null;
        $stallName = $role === 'staff' ? (trim($_POST['stall_name'] ?? '') ?: null) : null;

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            flash('error', 'Please provide a name, a valid email, and a password of at least 8 characters.');
        } else {
            $s = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $s->execute([$email]);
            if ($s->fetch()) {
                flash('error', 'An account with that email already exists.');
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare(
                    'INSERT INTO users (name,email,student_id,password_hash,role,assigned_category_id,stall_name,status) VALUES (?,?,?,?,?,?,?,\'active\')'
                )->execute([$name, $email, $studentId ?: null, $hash, $role, $assignedCategory, $stallName]);
                log_activity(user()['id'], 'user_create', "$role account: $email");
                flash('success', "Created $role account for $email.");
            }
        }
    } elseif ($action === 'update_staff_categories') {
        $rows = $_POST['staff'] ?? [];
        foreach ($rows as $id => $row) {
            $id = (int)$id;
            $catRaw = $row['assigned_category_id'] ?? '';
            $assignedCategory = $catRaw !== '' ? (int)$catRaw : null;
            $stallName = trim($row['stall_name'] ?? '') ?: null;
            $pdo->prepare("UPDATE users SET assigned_category_id=?, stall_name=? WHERE id=? AND role='staff'")
                ->execute([$assignedCategory, $stallName, $id]);
        }
        log_activity(user()['id'], 'user_update', 'Updated staff category/stall assignments');
        flash('success', 'Staff assignments updated.');
    } elseif ($action === 'toggle_status') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)user()['id']) {
            flash('error', "You can't deactivate your own account.");
        } else {
            $s = $pdo->prepare('SELECT status FROM users WHERE id=?');
            $s->execute([$id]);
            $current = $s->fetchColumn();
            if ($current !== false) {
                $new = $current === 'active' ? 'inactive' : 'active';
                $pdo->prepare('UPDATE users SET status=? WHERE id=?')->execute([$new, $id]);
                log_activity(user()['id'], 'user_status', "User #$id -> $new");
                flash('success', "Account is now $new.");
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)user()['id']) {
            flash('error', "You can't delete your own account.");
        } else {
            $s = $pdo->prepare('SELECT name, email FROM users WHERE id=?');
            $s->execute([$id]);
            $target = $s->fetch();
            if ($target) {
                $result = delete_or_deactivate_user($id);
                log_activity(user()['id'], 'user_' . $result, $target['email']);
                if ($result === 'deleted') {
                    flash('success', "Deleted {$target['name']}'s account.");
                } else {
                    flash('success', "{$target['name']} has order history, so the account was deactivated instead of deleted.");
                }
            }
        }
    }
    header('Location: users.php');
    exit;
}

$users = db()->query(
    "SELECT u.*, c.name category_name FROM users u
     LEFT JOIN categories c ON c.id = u.assigned_category_id
     ORDER BY FIELD(u.role,'admin','staff','student'), u.name"
)->fetchAll();

$title = 'Manage Users';
require __DIR__ . '/../includes/header.php';
?>
<h1>Manage Users</h1>
<p class="muted">Create staff and student accounts, assign each staff member a product category, and deactivate or delete accounts.</p>

<div class="card">
<h3>Create a new account</h3>
<form method="post" class="two-col">
<input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
<input type="hidden" name="action" value="create_account">
<div>
  <div class="form-group"><label>Role</label><select name="role" id="role-select" onchange="var show=this.value==='staff'?'block':'none'; document.getElementById('cat-group').style.display=show; document.getElementById('stall-group').style.display=show;">
    <option value="student">Student</option>
    <option value="staff">Staff</option>
  </select></div>
  <div class="form-group"><label>Name</label><input type="text" name="name" required></div>
  <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
</div>
<div>
  <div class="form-group"><label>Student ID <span class="muted">(optional)</span></label><input type="text" name="student_id"></div>
  <div class="form-group"><label>Password</label><input type="password" name="password" minlength="8" required></div>
  <div class="form-group" id="cat-group" style="display:none">
    <label>Assigned category <span class="muted">(blank = manages all categories)</span></label>
    <select name="assigned_category_id">
      <option value="">All categories (general)</option>
      <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="form-group" id="stall-group" style="display:none">
    <label>Stall name <span class="muted">(e.g. "1st Stall")</span></label>
    <input type="text" name="stall_name" placeholder="1st Stall" maxlength="50">
  </div>
  <button class="btn" type="submit">Create account</button>
</div>
</form>
</div>

<div class="section-title"><h2>All accounts</h2><button class="btn secondary small" type="submit" form="staff-cat-form">Save category assignments</button></div>
<form method="post" id="staff-cat-form">
<input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
<input type="hidden" name="action" value="update_staff_categories">
<div class="table-wrap">
<table class="table">
<tr><th>Name</th><th>Email</th><th>Role</th><th>Category</th><th>Stall</th><th>Status</th><th></th></tr>
<?php foreach ($users as $u): ?>
<tr>
  <td><?= e($u['name']) ?><?= (int)$u['id'] === (int)user()['id'] ? ' <span class="pill blue">You</span>' : '' ?></td>
  <td><?= e($u['email']) ?></td>
  <td><span class="pill"><?= e(ucfirst($u['role'])) ?></span></td>
  <td>
    <?php if ($u['role'] === 'staff'): ?>
      <select name="staff[<?= $u['id'] ?>][assigned_category_id]">
        <option value="">All categories (general)</option>
        <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>" <?= (int)$u['assigned_category_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
      </select>
    <?php else: ?><span class="muted">&mdash;</span><?php endif; ?>
  </td>
  <td>
    <?php if ($u['role'] === 'staff'): ?>
      <input type="text" name="staff[<?= $u['id'] ?>][stall_name]" value="<?= e($u['stall_name'] ?? '') ?>" placeholder="e.g. 1st Stall" maxlength="50" style="width:110px">
    <?php else: ?><span class="muted">&mdash;</span><?php endif; ?>
  </td>
  <td><span class="pill <?= $u['status'] === 'active' ? 'green' : 'red' ?>"><?= e(ucfirst($u['status'])) ?></span></td>
  <td class="actions">
    <button class="btn secondary small" type="submit" form="toggle-<?= $u['id'] ?>"><?= $u['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button>
    <button class="btn danger small" type="submit" form="delete-<?= $u['id'] ?>" onclick="return confirm('Delete <?= e(addslashes($u['name'])) ?>? If they have order history they will be deactivated instead.')">Delete</button>
  </td>
</tr>
<?php endforeach; ?>
</table>
</div>
</form>
<?php foreach ($users as $u): ?>
<form method="post" id="toggle-<?= $u['id'] ?>">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="action" value="toggle_status">
  <input type="hidden" name="id" value="<?= $u['id'] ?>">
</form>
<form method="post" id="delete-<?= $u['id'] ?>">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="id" value="<?= $u['id'] ?>">
</form>
<?php endforeach; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
