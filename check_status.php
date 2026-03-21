<?php
require_once(__DIR__ . '/config.php');

if (!is_logged_in()) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("
    SELECT status 
    FROM user_quests 
    WHERE user_id = ? 
    ORDER BY assigned_at DESC 
    LIMIT 1
");
$stmt->execute([$user_id]);
$quest = $stmt->fetch();

header('Content-Type: application/json');
echo json_encode(['status' => $quest ? $quest['status'] : null]);
