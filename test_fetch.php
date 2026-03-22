<?php
$_SESSION['user_id'] = 1;
include 'config.php';
$pdo->query("UPDATE users SET status='active' WHERE id=1");
