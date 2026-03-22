<?php
require_once(__DIR__ . '/config.php');
$_SESSION['user_id'] = 4;
ob_start();
include 'get_quest.php';
$out = ob_get_clean();
echo "OUTPUT: [" . $out . "]";
