<?php
require_once __DIR__.'/includes/auth.php'; require_login();
if($_SERVER['REQUEST_METHOD']==='POST'){check_csrf();unset($_SESSION['cart'][(int)($_POST['item_id']??0)]);}
header('Location: cart.php');exit;
