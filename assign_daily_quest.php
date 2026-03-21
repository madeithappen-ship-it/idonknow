<?php
require_once(__DIR__ . '/config.php');

header('Content-Type: application/json');

if (!is_logged_in() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'Invalid security token']);
    exit;
}

$quest_id = (int)$_POST['quest_id'];
$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');

try {
    // Check if they already have it today
    $stmt = $pdo->prepare("SELECT id FROM user_quests WHERE user_id = ? AND quest_id = ? AND DATE(assigned_at) = ?");
    $stmt->execute([$user_id, $quest_id, $today]);
    
    if (!$stmt->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO user_quests (user_id, quest_id, status) VALUES (?, ?, 'assigned')");
        $stmt->execute([$user_id, $quest_id]);
    }
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
