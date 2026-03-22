<?php
/**
 * Batch AI Verification Processor
 * Automatically verifies all pending submissions based on AI keywords and confidence
 * Can be called manually or via cron job
 */

require_once(__DIR__ . '/config.php');
header('Content-Type: application/json');

// Check if AI verification is enabled
function isAIVerifyEnabled() {
    global $pdo;
    $stmt = $pdo->prepare("SELECT setting_value FROM global_settings WHERE setting_key = 'ai_verify_proofs'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return ($result && $result['setting_value'] === '1');
}

// Process all pending submissions
function processPendingSubmissions() {
    global $pdo;
    
    if (!isAIVerifyEnabled()) {
        return [
            'success' => false,
            'error' => 'AI verification is disabled',
            'processed' => 0,
            'approved' => 0,
            'rejected' => 0
        ];
    }
    
    // Get all pending submissions with their quests
    $stmt = $pdo->prepare("
        SELECT s.*, q.keywords, q.difficulty, q.title 
        FROM submissions s
        JOIN quests q ON s.quest_id = q.id
        WHERE s.verification_status = 'pending'
        AND (s.keywords_found IS NULL OR s.confidence_score IS NULL)
        ORDER BY s.submitted_at ASC
        LIMIT 100
    ");
    $stmt->execute();
    $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stats = [
        'processed' => 0,
        'approved' => 0,
        'rejected' => 0,
        'manual_review' => 0,
        'results' => []
    ];
    
    foreach ($pending as $submission) {
        try {
            $result = aiAnalyzeAndVerify($submission);
            $stats['processed']++;
            
            if ($result['status'] === 'approved') {
                $stats['approved']++;
            } elseif ($result['status'] === 'rejected') {
                $stats['rejected']++;
            } else {
                $stats['manual_review']++;
            }
            
            $stats['results'][] = [
                'submission_id' => $submission['id'],
                'status' => $result['status'],
                'confidence' => $result['confidence'],
                'reason' => $result['reason']
            ];
            
        } catch (Exception $e) {
            error_log("Error processing submission {$submission['id']}: " . $e->getMessage());
        }
    }
    
    return [
        'success' => true,
        'processed' => $stats['processed'],
        'approved' => $stats['approved'],
        'rejected' => $stats['rejected'],
        'manual_review' => $stats['manual_review'],
        'results' => $stats['results']
    ];
}

// Analyze and verify a single submission
function aiAnalyzeAndVerify($submission) {
    global $pdo;
    
    $confidence = 0;
    $found_keywords = [];
    $approval_reason = '';
    $verified_status = 'manual_review';
    
    // Get proof content
    $proof_content = '';
    if (!empty($submission['text_proof'])) {
        $proof_content = strtolower($submission['text_proof']);
    } elseif (!empty($submission['file_name'])) {
        $proof_content = strtolower($submission['file_name']);
    }
    
    // Extract and match keywords
    $keywords = array_filter(array_map('trim', explode(',', $submission['keywords'] ?? '')));
    
    if (!empty($keywords) && !empty($proof_content)) {
        $matches = 0;
        foreach ($keywords as $keyword) {
            $keyword_lower = strtolower(trim($keyword));
            if (strlen($keyword_lower) > 2 && strpos($proof_content, $keyword_lower) !== false) {
                $matches++;
                $found_keywords[] = $keyword;
            }
        }
        
        // Calculate confidence
        $confidence = ($matches / count($keywords)) * 100;
        
        // Determine difficulty threshold
        $thresholds = [
            'easy' => 60,
            'medium' => 75,
            'hard' => 85,
            'insane' => 95
        ];
        
        $threshold = $thresholds[$submission['difficulty'] ?? 'medium'] ?? 75;
        
        if ($confidence >= $threshold) {
            $verified_status = 'approved';
            $approval_reason = "Auto-approved: {$matches}/" . count($keywords) . " keywords matched ({$confidence}% confidence)";
        } elseif ($confidence < 30) {
            $verified_status = 'rejected';
            $approval_reason = "Auto-rejected: Insufficient keyword matches ({$confidence}% confidence, threshold: $threshold%)";
        } else {
            $verified_status = 'manual_review';
            $approval_reason = "Partial match - {$matches}/" . count($keywords) . " keywords found ({$confidence}% confidence)";
        }
    } else {
        $verified_status = 'manual_review';
        $approval_reason = 'No keywords defined or no proof content';
    }
    
    // Update submission
    $stmt = $pdo->prepare("
        UPDATE submissions 
        SET verification_status = ?,
            verified_at = NOW(),
            verified_by = NULL,
            verification_notes = ?,
            confidence_score = ?,
            keywords_found = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        $verified_status,
        $approval_reason,
        $confidence / 100,
        implode(', ', $found_keywords),
        $submission['id']
    ]);
    
    // If approved, update user_quest and award XP
    if ($verified_status === 'approved') {
        // Update user_quest
        $stmt = $pdo->prepare("
            UPDATE user_quests SET status = 'approved', completed_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$submission['user_quest_id']]);
        
        // Get quest details
        $stmt = $pdo->prepare("
            SELECT q.xp_reward FROM quests q WHERE q.id = ?
        ");
        $stmt->execute([$submission['quest_id']]);
        $quest = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Award XP
        $xp = $quest['xp_reward'] ?? 34;
        $stmt = $pdo->prepare("
            UPDATE users SET xp = xp + ?, total_completed = total_completed + 1
            WHERE id = ?
        ");
        $stmt->execute([$xp, $submission['user_id']]);
    } 
    // If rejected, update user_quest
    elseif ($verified_status === 'rejected') {
        $stmt = $pdo->prepare("
            UPDATE user_quests SET status = 'rejected' 
            WHERE id = ?
        ");
        $stmt->execute([$submission['user_quest_id']]);
    }
    
    // Log audit
    log_audit('BATCH_AI_VERIFY', 'submission', $submission['id'], [
        'status' => $verified_status,
        'confidence' => $confidence,
        'keywords_found' => implode(', ', $found_keywords)
    ]);
    
    return [
        'status' => $verified_status,
        'confidence' => round($confidence, 1),
        'reason' => $approval_reason
    ];
}

// API handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'process_batch' && is_admin()) {
        $result = processPendingSubmissions();
        echo json_encode($result);
        exit;
    }
}

// For GET requests (cron jobs can call this)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $cron_key = $_GET['key'] ?? '';
    $expected_key = getenv('CRON_KEY') ?? 'default_cron_key';
    
    if ($cron_key === $expected_key && isAIVerifyEnabled()) {
        $result = processPendingSubmissions();
        echo json_encode($result);
        exit;
    }
}

echo json_encode(['success' => false, 'error' => 'Invalid request']);
