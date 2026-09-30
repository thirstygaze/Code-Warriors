<?php
require_once __DIR__ . '/includes/auth.php';
if (user()) { header('Location: menu.php'); exit; }
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST'){
    check_csrf();
    $email=trim($_POST['email']??''); $password=$_POST['password']??'';
    $stmt=db()->prepare("SELECT * FROM users WHERE email=? AND status='active' LIMIT 1"); $stmt->execute([$email]);
    $u=$stmt->fetch();
    if($u && password_verify($password,$u['password_hash'])){
        session_regenerate_id(true);
        unset($u['password_hash']); $_SESSION['user']=$u;
        log_activity($u['id'],'login','User logged in');
        header('Location: menu.php'); exit;
    }
    $error='Invalid email or password.';
}
$title='Login'; require __DIR__.'/includes/header.php';
?>
<div class="card form-card">
<h2>Login</h2>
<?php if($error): ?><div class="alert error"><?=e($error)?></div><?php endif;?>
<form method="post">
<input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<div class="form-group"><label>Email</label><input type="email" name="email" required></div>
<div class="form-group"><label>Password</label><input type="password" name="password" required></div>
<button class="btn">Login</button>
</form>
<p class="muted"> <code></code>.</p>
</div>
<?php require __DIR__.'/includes/footer.php'; ?>
