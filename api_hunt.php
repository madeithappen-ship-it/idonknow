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
    $x = (float)($input['x'] ?? 1000);
    $y = (float)($input['y'] ?? 1000);
    $seed = $input['seed'] ?? 'cyber_01';
    $treasures = json_encode($input['found_treasures'] ?? []);
    
    $stmt = $pdo->prepare("SELECT id FROM user_hunt_saves WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $existing = $stmt->fetchColumn();
    
    if ($existing) {
        $stmt = $pdo->prepare("UPDATE user_hunt_saves SET player_x = ?, player_y = ?, map_seed = ?, found_treasures = ? WHERE id = ?");
        $stmt->execute([$x, $y, $seed, $treasures, $existing]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO user_hunt_saves (user_id, player_x, player_y, map_seed, found_treasures) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $x, $y, $seed, $treasures]);
    }
    
    echo json_encode(['success' => true]);
    exit;
}
elseif ($action === 'load') {
    $stmt = $pdo->prepare("SELECT * FROM user_hunt_saves WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $state = $stmt->fetch();
    
    if($state) {
        echo json_encode([
            'success' => true, 
            'x' => (float)$state['player_x'], 
            'y' => (float)$state['player_y'],
            'seed' => $state['map_seed'],
            'treasures' => json_decode($state['found_treasures'] ?? '[]', true)
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No save found']);
    }
    exit;
}
elseif ($action === 'check_rewards') {
    $stmt = $pdo->prepare("
        SELECT uq.id FROM user_quests uq
        WHERE uq.user_id = ? AND uq.status = 'approved' AND uq.reward_granted = 0
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
