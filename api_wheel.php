<?php
require_once(__DIR__ . '/config.php');
header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user = get_user();
$user_id = $user['id'];

if ($user['level'] < 20 && !is_admin()) {
    echo json_encode(['success' => false, 'error' => 'Chaos Wheel unlocks at Level 20']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

if ($action === 'spin') {
    // Check if spun today
    $today = date('Y-m-d');
    if ($user['last_spin_date'] === $today) {
        echo json_encode(['success' => false, 'error' => 'You already spun the Chaos Wheel today! Come back tomorrow.']);
        exit;
    }
    
    // Generate reward logic
    $rand = mt_rand(1, 100);
    $rewardObj = [];
    $xp_change = 0;
    
    if ($rand <= 10) {
        // 10% Jackpot
        $rewardObj = ['type' => 'xp', 'amount' => 100, 'label' => 'JACKPOT! +100 XP', 'color' => '#fbbf24'];
        $xp_change = 100;
    } elseif ($rand <= 40) {
        // 30% Trap
        $rewardObj = ['type' => 'trap', 'amount' => -50, 'label' => 'TRAP! -50 XP', 'color' => '#ef4444'];
        $xp_change = -50;
    } elseif ($rand <= 70) {
        // 30% Small Win
        $rewardObj = ['type' => 'xp', 'amount' => 25, 'label' => 'LUCKY! +25 XP', 'color' => '#34d399'];
        $xp_change = 25;
    } else {
        // 30% Insane Quest
        $rewardObj = ['type' => 'quest', 'amount' => 0, 'label' => 'INSANE QUEST ASSIGNED!', 'color' => '#c084fc'];
        
        // Find an insane quest
        $stmt = $pdo->query("SELECT id FROM quests WHERE difficulty = 'insane' ORDER BY RAND() LIMIT 1");
        $q_id = $stmt->fetchColumn();
        
        if ($q_id) {
            $stmt = $pdo->prepare("
                INSERT INTO user_quests (user_id, quest_id, status, expires_at)
                VALUES (?, ?, 'assigned', DATE_ADD(NOW(), INTERVAL 7 DAY))
                ON DUPLICATE KEY UPDATE 
                status = 'assigned', expires_at = DATE_ADD(NOW(), INTERVAL 7 DAY), assigned_at = NOW()
            ");
            $stmt->execute([$user_id, $q_id]);
        } else {
            $rewardObj = ['type' => 'xp', 'amount' => 50, 'label' => 'NO QUESTS! +50 XP INSTEAD', 'color' => '#fbbf24'];
            $xp_change = 50;
        }
    }
    
    // Process XP changes safely
    if ($xp_change !== 0) {
        $new_xp = max(0, $user['xp'] + $xp_change);
        $stmt = $pdo->prepare("UPDATE users SET xp = ? WHERE id = ?");
        $stmt->execute([$new_xp, $user_id]);
    }
    
    // Update spin date
    $stmt = $pdo->prepare("UPDATE users SET last_spin_date = ? WHERE id = ?");
    $stmt->execute([$today, $user_id]);
    
    echo json_encode(['success' => true, 'result' => $rewardObj]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
