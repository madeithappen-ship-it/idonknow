<?php
require_once(__DIR__ . '/config.php');
header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

if ($action === 'save') {
    $state = $input['state_json'] ?? '';
    $score = $input['score'] ?? 0;
    
    $stmt = $pdo->prepare("SELECT id FROM user_solitaire WHERE user_id = ? AND status = 'playing' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$user_id]);
    $existing = $stmt->fetchColumn();
    
    if ($existing) {
        $stmt = $pdo->prepare("UPDATE user_solitaire SET state_json = ?, xp_earned = ? WHERE id = ?");
        $stmt->execute([$state, $score, $existing]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO user_solitaire (user_id, state_json, xp_earned) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $state, $score]);
    }
    
    echo json_encode(['success' => true]);
    exit;
}
elseif ($action === 'load') {
    $stmt = $pdo->prepare("SELECT state_json FROM user_solitaire WHERE user_id = ? AND status = 'playing' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$user_id]);
    $state = $stmt->fetchColumn();
    echo json_encode(['success' => true, 'state' => $state]);
    exit;
}
elseif ($action === 'check_rewards') {
    $stmt = $pdo->prepare("
        SELECT uq.id FROM user_quests uq
        JOIN quests q ON uq.quest_id = q.id
        WHERE uq.user_id = ? AND uq.status = 'approved' AND q.difficulty = 'insane' AND uq.reward_granted = 0
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $rewardId = $stmt->fetchColumn();
    
    if ($rewardId) {
        $stmt = $pdo->prepare("UPDATE user_quests SET reward_granted = 1 WHERE id = ?");
        $stmt->execute([$rewardId]);
        echo json_encode(['success' => true, 'reward' => true]);
    } else {
        echo json_encode(['success' => true, 'reward' => false]);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
