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

$id = (int)($_POST['user_quest_id'] ?? 0);
$user_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("UPDATE user_quests SET status = 'skipped' WHERE id = ? AND user_id = ? AND status IN ('assigned', 'in_progress')");
    $stmt->execute([$id, $user_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Could not skip quest']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
