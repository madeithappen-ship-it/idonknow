<?php
/**
 * Proof Submission Handler
 * 
 * Handles image uploads, validates them, and submits for verification
 */

require_once(__DIR__ . '/config.php');

header('Content-Type: application/json');

// Must be logged in
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_quest_id = $_POST['user_quest_id'] ?? null;

if (!$user_quest_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Quest ID required']);
    exit;
}

// Verify user owns this quest
$stmt = $pdo->prepare("
    SELECT uq.*, q.id as quest_id FROM user_quests uq
    JOIN quests q ON uq.quest_id = q.id
    WHERE uq.id = ? AND uq.user_id = ?
");
$stmt->execute([$user_quest_id, $user_id]);
$user_quest = $stmt->fetch();

if (!$user_quest) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Check if file is uploaded
if (!isset($_FILES['proof']) || $_FILES['proof']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error']);
    exit;
}

$file = $_FILES['proof'];
$max_size = config('max_upload_size');
$allowed_mimes = config('allowed_mimes');

// Validate file size
if ($file['size'] > $max_size) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'File too large (max 5MB)']);
    exit;
}

// Validate MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime_type, $allowed_mimes)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid file type (image only)']);
    exit;
}

// Validate image dimensions (prevent tiny images)
$image_info = getimagesize($file['tmp_name']);
if (!$image_info || $image_info[0] < 200 || $image_info[1] < 200) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Image too small (min 200x200px)']);
    exit;
}

// Generate secure filename
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'proof_' . $user_id . '_' . $user_quest['quest_id'] . '_' . time() . '.' . $ext;
$upload_path = config('upload_dir') . 'proofs/' . $filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save file']);
    exit;
}

// Store submission in database
try {
    // Delete any previous pending submissions for this quest
    $stmt = $pdo->prepare("
        DELETE FROM submissions
        WHERE user_quest_id = ? AND verification_status = 'pending'
    ");
    $stmt->execute([$user_quest_id]);
    
    // Create new submission
    $stmt = $pdo->prepare("
        INSERT INTO submissions (
            user_id, user_quest_id, quest_id, 
            file_path, file_name, file_size, mime_type,
            verification_status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    
    $stmt->execute([
        $user_id,
        $user_quest_id,
        $user_quest['quest_id'],
        $upload_path,
        $filename,
        $file['size'],
        $mime_type
    ]);
    
    $submission_id = $pdo->lastInsertId();
    
    // Update user_quest status
    $stmt = $pdo->prepare("
        UPDATE user_quests 
        SET status = 'submitted', submission_id = ?, last_attempt = NOW(), attempts = attempts + 1
        WHERE id = ?
    ");
    $stmt->execute([$submission_id, $user_quest_id]);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Proof submitted successfully',
        'submission_id' => $submission_id
    ]);
    
} catch (Exception $e) {
    // Remove uploaded file on database error
    @unlink($upload_path);
    
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}

/**
 * Auto-verify submission based on image analysis
 */
function auto_verify_submission($submission_id, $user_quest) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM submissions WHERE id = ?");
        $stmt->execute([$submission_id]);
        $submission = $stmt->fetch();
        
        $confidence = 0.5; // Base confidence
        
        // Check if file exists and is valid
        if (file_exists($submission['file_path'])) {
            $confidence += 0.2;
        }
        
        // Check image dimensions
        $image_info = getimagesize($submission['file_path']);
        if ($image_info && $image_info[0] > 0 && $image_info[1] > 0) {
            $confidence += 0.1;
        }
        
        // Check file size is not suspicious
        if ($submission['file_size'] > 50000 && $submission['file_size'] < 5000000) {
            $confidence += 0.1;
        }
        
        // Additional checks based on keywords in quest
        if (!empty($user_quest['keywords'])) {
            $keywords = explode(',', $user_quest['keywords']);
            // In production, you'd use OCR or AI vision API here
            // For now, just increase confidence slightly
            $confidence += 0.05;
        }
        
        return min($confidence, 0.95);
        
    } catch (Exception $e) {
        return 0.5;
    }
}

/**
 * Award XP and check for level ups
 */
function award_xp($user_id, $user_quest) {
    global $pdo;
    
    try {
        $xp_earned = $user_quest['xp_reward'] * $user_quest['difficulty_multiplier'];
        
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
