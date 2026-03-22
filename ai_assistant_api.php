<?php
/**
 * AI Site Assistant API
 * Handles smart responses based on site knowledge
 */

require_once(__DIR__ . '/config.php');
header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];

if ($action === 'chat') {
    try {
        $message = trim($_POST['message'] ?? '');
        if (!$message) {
            echo json_encode(['success' => false, 'error' => 'Empty message']);
            exit;
        }

        // Load knowledge base
        $knowledge_path = __DIR__ . '/ai_knowledge.json';
        if (!file_exists($knowledge_path)) {
            throw new Exception("Knowledge base missing");
        }
        
        $knowledge_raw = file_get_contents($knowledge_path);
        $knowledge = json_decode($knowledge_raw, true);
        if (!$knowledge) {
            throw new Exception("Invalid knowledge base format");
        }
        
        $response = get_ai_response($message, $knowledge);
        
        // Log the interaction in the database
        // We use 'admin' sender_type or 'ai' - chat_widget.php handles anything not 'user' as 'theirs'
        $stmt = $pdo->prepare("INSERT INTO chat_messages (sender_type, user_id, message, is_read) VALUES (?, ?, ?, 1)");
        $stmt->execute(['user', $user_id, $message]); 
        
        $stmt = $pdo->prepare("INSERT INTO chat_messages (sender_type, user_id, message, is_read) VALUES (?, ?, ?, 1)");
        $stmt->execute(['ai', $user_id, $response]); 
        
        echo json_encode([
            'success' => true,
            'response' => $response,
            'sender_type' => 'ai'
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'response' => "I'm having a bit of a literal brain-freeze. Please try again or ask something about XP!"
        ]);
    }
    exit;
}

/**
 * Simple matching logic for the assistant
 * In production, this would call a real LLM API like Gemini
 */
function get_ai_response($text, $knowledge) {
    $text = strtolower($text);
    
    // Check FAQs
    foreach ($knowledge['faqs'] as $faq) {
        foreach ($faq['keywords'] as $keyword) {
            if (strpos($text, strtolower($keyword)) !== false) {
                return $faq['answer'];
            }
        }
    }
    
    // Fallback response
    return "I'm not sure I understand that. Try asking about XP, Quests, Chess, or how to install the app. You can also switch to Live Support to talk to a human!";
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
