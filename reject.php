<?php
/**
 * Submission Rejection Handler
 * 
 * Allows admins to reject proofs with notes
 */

require_once(__DIR__ . '/config.php');

header('Content-Type: application/json');

if (!is_admin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$submission_id = $_POST['submission_id'] ?? null;
$notes = $_POST['notes'] ?? '';

if (!$submission_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Submission ID required']);
    exit;
}

// Get submission
$stmt = $pdo->prepare("SELECT * FROM submissions WHERE id = ?");
$stmt->execute([$submission_id]);
$submission = $stmt->fetch();

if (!$submission) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Submission not found']);
    exit;
}

try {
    // Update submission status
    $stmt = $pdo->prepare("
        UPDATE submissions
        SET verification_status = 'rejected',
            verified_at = NOW(),
            verified_by = ?,
            verification_notes = ?
        WHERE id = ?
    ");
    
    $stmt->execute([$_SESSION['admin_id'], $notes, $submission_id]);
    
    // Reset user_quest to allow retry (max 3 attempts)
    $stmt = $pdo->prepare("
        SELECT attempts FROM user_quests WHERE id = ?
    ");
    $stmt->execute([$submission['user_quest_id']]);
    $current_attempt = $stmt->fetch()['attempts'] ?? 0;
    
    if ($current_attempt < 3) {
        // Allow retry
        $stmt = $pdo->prepare("
            UPDATE user_quests
            SET status = 'in_progress'
            WHERE id = ?
        ");
        $stmt->execute([$submission['user_quest_id']]);
    } else {
        // Too many attempts, expire quest
        $stmt = $pdo->prepare("
            UPDATE user_quests
            SET status = 'expired'
            WHERE id = ?
        ");
        $stmt->execute([$submission['user_quest_id']]);
    }
    
    // Log action
    log_audit('REJECT_SUBMISSION', 'submission', $submission_id, [
        'user_id' => $submission['user_id'],
        'notes' => $notes,
        'attempt' => $current_attempt + 1
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Submission rejected']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}