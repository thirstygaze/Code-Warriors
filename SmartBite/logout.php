<?php
require_once __DIR__ . '/includes/auth.php';
if (user()) log_activity(user()['id'],'logout','User logged out');
$_SESSION=[];
session_destroy();
header('Location: index.php'); exit;
