<?php
require_once(__DIR__ . '/config.php');

if (!is_admin() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

$id = (int)($_POST['id'] ?? 0);
if ($id) {
    // Prevent breaking active quests referencing this deleted log
    $stmt = $pdo->prepare("UPDATE user_quests SET submission_id = NULL WHERE submission_id = ?");
    $stmt->execute([$id]);
    
    // Clear physics storage payload if it exists locally to free space
    $stmt = $pdo->prepare("SELECT file_path FROM submissions WHERE id = ?");
    $stmt->execute([$id]);
    $path = $stmt->fetchColumn();
    if ($path && file_exists(__DIR__ . '/' . $path)) {
        unlink(__DIR__ . '/' . $path);
    }
    
    // Detach element safely
    $stmt = $pdo->prepare("DELETE FROM submissions WHERE id = ?");
    $stmt->execute([$id]);
    
    @log_audit('DELETE_SUBMISSION', 'system', $id, []);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid ID']);
}
