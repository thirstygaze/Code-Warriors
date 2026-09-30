<?php
require_once __DIR__ . '/includes/auth.php';
if (user()) { header('Location: menu.php'); exit; }

$errors = [];
$name = $email = $studentId = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $studentId = trim($_POST['student_id'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if ($name === '') $errors[] = 'Please enter your name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with that email already exists.';
        }
    }

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = db()->prepare(
            "INSERT INTO users (name, email, student_id, password_hash, role, status) VALUES (?,?,?,?,'student','active')"
        );
        $stmt->execute([$name, $email, $studentId ?: null, $hash]);
        $userId = (int)db()->lastInsertId();
        log_activity($userId, 'register', 'Account created');
        flash('success', 'Account created. You can now log in.');
        header('Location: login.php');
        exit;
    }
}

$title = 'Create Account';
require __DIR__ . '/includes/header.php';
?>
<div class="card form-card">
<h2>Create your account</h2>
<?php foreach ($errors as $err): ?><div class="alert error"><?= e($err) ?></div><?php endforeach; ?>
<form method="post">
<input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
<div class="form-group"><label>Full name</label><input type="text" name="name" value="<?= e($name) ?>" required></div>
<div class="form-group"><label>Email</label><input type="email" name="email" value="<?= e($email) ?>" required></div>
<div class="form-group"><label>Student ID <span class="muted">(optional)</span></label><input type="text" name="student_id" value="<?= e($studentId) ?>"></div>
<div class="form-group"><label>Password</label><input type="password" name="password" minlength="8" required></div>
<div class="form-group"><label>Confirm password</label><input type="password" name="confirm" minlength="8" required></div>
<button class="btn" type="submit">Create account</button>
</form>
<p class="muted">Already have an account? <a href="login.php">Log in</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
