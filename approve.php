<?php
/**
 * Submission Approval Handler
 * 
 * Allows admins to approve proofs
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
        SET verification_status = 'approved',
            verified_at = NOW(),
            verified_by = ?
        WHERE id = ?
    ");
    
    $stmt->execute([$_SESSION['admin_id'], $submission_id]);
    
    // Update user_quest to approved
    $stmt = $pdo->prepare("
        UPDATE user_quests SET status = 'approved', completed_at = NOW() WHERE id = ?
    ");
    $stmt->execute([$submission['user_quest_id']]);
    
    // Award XP
    $stmt = $pdo->prepare("
        SELECT uq.*, q.xp_reward 
        FROM user_quests uq
        JOIN quests q ON uq.quest_id = q.id 
        WHERE uq.id = ?
    ");
    $stmt->execute([$submission['user_quest_id']]);
    $user_quest = $stmt->fetch();
    
    if ($user_quest) {
        award_xp($submission['user_id'], $user_quest);
    }
    
    // Log action
    log_audit('APPROVE_SUBMISSION', 'submission', $submission_id, [
        'user_id' => $submission['user_id']
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Submission approved']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}

/**
 * Award XP and check for level ups
 */
function award_xp($user_id, $user_quest) {
    global $pdo;
    
    try {
        $xp_earned = isset($user_quest['xp_reward']) ? (int)$user_quest['xp_reward'] : 10;
        
        $stmt = $pdo->prepare("
            UPDATE users SET xp = xp + ?, total_completed = total_completed + 1
            WHERE id = ?
        ");
        $stmt->execute([(int)$xp_earned, $user_id]);
        
        // Check for level up (100 XP per level)
        $stmt = $pdo->prepare("SELECT xp, level FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        $new_level = floor($user['xp'] / 100) + 1;
        if ($new_level > $user['level']) {
            $stmt = $pdo->prepare("UPDATE users SET level = ? WHERE id = ?");
            $stmt->execute([$new_level, $user_id]);
        }
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}