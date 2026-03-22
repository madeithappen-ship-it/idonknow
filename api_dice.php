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
    echo json_encode(['success' => false, 'error' => 'Dice Game unlocks at Level 20']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

if ($action === 'roll') {
    // Check rerolls
    $today = date('Y-m-d');
    if (!isset($_SESSION['dice_date']) || $_SESSION['dice_date'] !== $today) {
        $_SESSION['dice_date'] = $today;
        $_SESSION['dice_rerolls'] = 3;
    }
    
    if ($_SESSION['dice_rerolls'] <= 0) {
        echo json_encode(['success' => false, 'error' => 'You have 0 rerolls left today!']);
        exit;
    }
    
    // Decrement reroll
    $_SESSION['dice_rerolls']--;
    $rerolls_left = $_SESSION['dice_rerolls'];
    
    $roll = mt_rand(1, 6);
    $difficulty = 'easy';
    
    if ($roll === 1 || $roll === 2) $difficulty = 'easy';
    elseif ($roll === 3 || $roll === 4) $difficulty = 'medium';
    elseif ($roll === 5) $difficulty = 'hard';
    elseif ($roll === 6) $difficulty = 'insane';
    
    // Get random quest of this difficulty
    $stmt = $pdo->prepare("SELECT id, title, description, xp_reward FROM quests WHERE difficulty = ? ORDER BY RAND() LIMIT 1");
    $stmt->execute([$difficulty]);
    $new_quest = $stmt->fetch();
    
    if (!$new_quest) {
        // Fallback if no quest of that difficulty exists
        $stmt = $pdo->query("SELECT id, title, description, xp_reward FROM quests ORDER BY RAND() LIMIT 1");
        $new_quest = $stmt->fetch();
    }
    
    // Assign quest
    $stmt = $pdo->prepare("
        INSERT INTO user_quests (user_id, quest_id, status, expires_at)
        VALUES (?, ?, 'assigned', DATE_ADD(NOW(), INTERVAL 7 DAY))
        ON DUPLICATE KEY UPDATE 
        status = 'assigned', expires_at = DATE_ADD(NOW(), INTERVAL 7 DAY), assigned_at = NOW()
    ");
    $stmt->execute([$user_id, $new_quest['id']]);
    
    echo json_encode([
        'success' => true, 
        'roll' => $roll,
        'difficulty' => $difficulty,
        'rerolls_left' => $rerolls_left,
        'quest' => $new_quest
    ]);
    exit;
}

if ($action === 'status') {
    $today = date('Y-m-d');
    if (!isset($_SESSION['dice_date']) || $_SESSION['dice_date'] !== $today) {
        $_SESSION['dice_date'] = $today;
        $_SESSION['dice_rerolls'] = 3;
    }
    echo json_encode(['success' => true, 'rerolls_left' => $_SESSION['dice_rerolls']]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
