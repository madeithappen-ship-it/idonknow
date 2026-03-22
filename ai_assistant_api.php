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
    $message = trim($_POST['message'] ?? '');
    if (!$message) {
        echo json_encode(['success' => false, 'error' => 'Empty message']);
        exit;
    }

    // Load knowledge base
    $knowledge_path = __DIR__ . '/ai_knowledge.json';
    $knowledge = json_decode(file_get_contents($knowledge_path), true);
    
    $response = get_ai_response($message, $knowledge);
    
    // Log the interaction in the database (optional, for history)
    // We can use the existing chat_messages table with a special 'ai' sender_type
    $stmt = $pdo->prepare("INSERT INTO chat_messages (sender_type, user_id, message) VALUES (?, ?, ?)");
    $stmt->execute(['user', $user_id, $message]); // User's message
    
    $stmt = $pdo->prepare("INSERT INTO chat_messages (sender_type, user_id, message) VALUES (?, ?, ?)");
    $stmt->execute(['ai', $user_id, $response]); // AI's response
    
    echo json_encode([
        'success' => true,
        'response' => $response,
        'sender_type' => 'ai'
    ]);
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
