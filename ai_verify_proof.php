<?php
/**
 * AI Proof Auto-Verification
 * Automatically verifies submissions based on keywords and confidence scoring
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

// Analyze proof against quest requirements
function aiAnalyzeProof($submission, $quest) {
    $confidence = 0;
    $found_keywords = [];
    $approval_reasoning = [];
    
    // Get proof content
    $proof_content = '';
    
    if (!empty($submission['text_proof'])) {
        $proof_content = strtolower($submission['text_proof']);
    } elseif (!empty($submission['file_path'])) {
        // For images, we'd use OCR or AI vision - for now, use filename
        $proof_content = strtolower($submission['file_name']);
    }
    
    if (empty($proof_content)) {
        return ['approved' => false, 'confidence' => 0, 'reason' => 'Empty proof content'];
    }
    
    // Extract keywords from quest
    $keywords = array_filter(array_map('trim', explode(',', $quest['keywords'] ?? '')));
    
    if (!empty($keywords)) {
        $matches = 0;
        foreach ($keywords as $keyword) {
            $keyword_lower = strtolower(trim($keyword));
            if (strlen($keyword_lower) > 2 && strpos($proof_content, $keyword_lower) !== false) {
                $matches++;
                $found_keywords[] = $keyword_lower;
                $approval_reasoning[] = "Found keyword: '$keyword_lower'";
            }
        }
        
        // Calculate confidence based on keyword matches
        $confidence = min(100, ($matches / max(1, count($keywords))) * 100);
    }
    
    // Challenge types with higher confidence thresholds
    $difficulty_multiplier = [
        'easy' => 0.8,        // 80% confidence needed
        'medium' => 0.85,     // 85% confidence needed
        'hard' => 0.90,       // 90% confidence needed
        'insane' => 0.95      // 95% confidence needed
    ];
    
    $threshold = $difficulty_multiplier[$quest['difficulty'] ?? 'medium'] * 100;
    
    $approved = ($confidence >= $threshold);
    
    if (!$approved && $confidence >= 60) {
        $approval_reasoning[] = "Partial match - below $threshold% threshold (got {$confidence}%)";
    } elseif ($approved) {
        $approval_reasoning[] = "Approved - confidence: {$confidence}% (threshold: $threshold%)";
    } else {
        $approval_reasoning[] = "Rejected - insufficient keyword matches";
    }
    
    return [
        'approved' => $approved,
        'confidence' => round($confidence, 1),
        'threshold' => $threshold,
        'found_keywords' => $found_keywords,
        'reasoning' => implode('; ', $approval_reasoning)
    ];
}

// Auto-verify a submission
function aiAutoVerifySubmission($submission_id) {
    global $pdo;
    
    // Get submission and quest
    $stmt = $pdo->prepare("
        SELECT s.*, q.keywords, q.difficulty, q.title, q.description 
        FROM submissions s
        JOIN quests q ON s.quest_id = q.id
        WHERE s.id = ?
    ");
    $stmt->execute([$submission_id]);
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$submission) {
        return ['success' => false, 'error' => 'Submission not found'];
    }
    
    if ($submission['verification_status'] !== 'pending') {
        return ['success' => false, 'error' => 'Submission already processed'];
    }
    
    // Run AI analysis
    $analysis = aiAnalyzeProof($submission, $submission);
    
    $verified_status = $analysis['approved'] ? 'approved' : 'rejected';
    $verification_notes = $analysis['reasoning'];
    $confidence_score = $analysis['confidence'] / 100;
    
    try {
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
            $verification_notes,
            $confidence_score,
            implode(', ', $analysis['found_keywords']),
            $submission_id
        ]);
        
        // If approved, update user_quest and award XP
        if ($analysis['approved']) {
            $stmt = $pdo->prepare("
                UPDATE user_quests SET status = 'approved', completed_at = NOW() 
                WHERE id = ?
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
            $user_quest = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user_quest) {
                $stmt = $pdo->prepare("
                    UPDATE users SET xp = xp + ?, total_completed = total_completed + 1
                    WHERE id = ?
                ");
                $stmt->execute([34, $submission['user_id']]);
            }
        } else {
            // Mark as rejected
            $stmt = $pdo->prepare("
                UPDATE user_quests SET status = 'rejected' 
                WHERE id = ?
            ");
            $stmt->execute([$submission['user_quest_id']]);
        }
        
        // Log audit
        log_audit('AI_VERIFY_SUBMISSION', 'submission', $submission_id, [
            'status' => $verified_status,
            'confidence' => $confidence_score,
            'reasoning' => $verification_notes
        ]);
        
        return [
            'success' => true,
            'submission_id' => $submission_id,
            'verified_status' => $verified_status,
            'confidence' => $analysis['confidence'],
            'reasoning' => $verification_notes
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// API Endpoints
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'auto_verify' && is_admin()) {
        $submission_id = $_POST['submission_id'] ?? null;
        
        if (!isAIVerifyEnabled()) {
            echo json_encode(['success' => false, 'error' => 'AI verification is disabled']);
            exit;
        }
        
        if (!$submission_id) {
            echo json_encode(['success' => false, 'error' => 'Submission ID required']);
            exit;
        }
        
        $result = aiAutoVerifySubmission($submission_id);
        echo json_encode($result);
        exit;
    }
}

echo json_encode(['success' => false, 'error' => 'Invalid request']);
