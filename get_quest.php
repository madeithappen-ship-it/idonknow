<?php
/**
 * Quest Assignment and Delivery API
 * 
 * Assigns random quests to users, preventing repeats
 * and ensuring variety in difficulty levels
 */

require_once(__DIR__ . '/config.php');

// Must be logged in
if (!is_logged_in()) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get current quest if exists
$stmt = $pdo->prepare("
    SELECT uq.id, uq.quest_id, q.* FROM user_quests uq
    JOIN quests q ON uq.quest_id = q.id
    WHERE uq.user_id = ? AND uq.status IN ('assigned', 'in_progress', 'submitted')
    ORDER BY uq.assigned_at DESC
    LIMIT 1
");
$stmt->execute([$user_id]);
$current_quest = $stmt->fetch();

// If user has a current quest, return it
if ($current_quest) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'current' => true,
        'quest' => [
            'id' => $current_quest['id'],
            'quest_id' => $current_quest['quest_id'],
            'title' => $current_quest['title'],
            'description' => $current_quest['description'],
            'difficulty' => $current_quest['difficulty'],
            'type' => $current_quest['type'],
            'xp_reward' => $current_quest['xp_reward'],
            'status' => $current_quest['status'],
        ]
    ]);
    exit;
}

// Get quests completed by this user
$stmt = $pdo->prepare("
    SELECT DISTINCT quest_id FROM user_quests
    WHERE user_id = ? AND status IN ('approved', 'completed')
");
$stmt->execute([$user_id]);
$completed_quests = array_column($stmt->fetchAll(), 'quest_id');

// Get user level for difficulty progression
$user = get_user($user_id);
$user_level = $user['level'] ?? 1;

// Difficulty weights based on level
$difficulty_weights = [];
if ($user_level < 5) {
    $difficulty_weights = ['easy' => 50, 'medium' => 30, 'hard' => 15, 'insane' => 5];
} elseif ($user_level < 10) {
    $difficulty_weights = ['easy' => 20, 'medium' => 50, 'hard' => 25, 'insane' => 5];
} elseif ($user_level < 20) {
    $difficulty_weights = ['easy' => 10, 'medium' => 30, 'hard' => 50, 'insane' => 10];
} else {
    $difficulty_weights = ['easy' => 5, 'medium' => 15, 'hard' => 30, 'insane' => 50];
}

// Pick difficulty based on weights
$rand = rand(0, 100);
$difficulty = 'medium';
$cumulative = 0;
foreach ($difficulty_weights as $diff => $weight) {
    $cumulative += $weight;
    if ($rand <= $cumulative) {
        $difficulty = $diff;
        break;
    }
}

// Build query to find available quest
$excluded_str = !empty($completed_quests) ? implode(',', $completed_quests) : '0';
$sql = "
    SELECT * FROM quests
    WHERE is_active = 1 
    AND difficulty = ?
    AND id NOT IN ($excluded_str)
    ORDER BY RAND()
    LIMIT 1
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$difficulty]);
$new_quest = $stmt->fetch();

if (!$new_quest) {
    // Fallback: get any unompleted quest
    $sql = "
        SELECT * FROM quests
        WHERE is_active = 1
        AND id NOT IN ($excluded_str)
        ORDER BY RAND()
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $new_quest = $stmt->fetch();
}

if ($new_quest) {
    // Assign quest to user
    try {
        $stmt = $pdo->prepare("
            INSERT INTO user_quests (user_id, quest_id, status, expires_at)
            VALUES (?, ?, 'assigned', DATE_ADD(NOW(), INTERVAL 7 DAY))
            ON DUPLICATE KEY UPDATE 
            status = 'assigned',
            expires_at = DATE_ADD(NOW(), INTERVAL 7 DAY),
            assigned_at = NOW()
        ");
        $stmt->execute([$user_id, $new_quest['id']]);
        
        // Get the user_quest record
        $stmt = $pdo->prepare("
            SELECT id FROM user_quests WHERE user_id = ? AND quest_id = ?
        ");
        $stmt->execute([$user_id, $new_quest['id']]);
        $uq = $stmt->fetch();
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'current' => false,
            'quest' => [
                'id' => $uq['id'],
                'quest_id' => $new_quest['id'],
                'title' => $new_quest['title'],
                'description' => $new_quest['description'],
                'difficulty' => $new_quest['difficulty'],
                'type' => $new_quest['type'],
                'xp_reward' => $new_quest['xp_reward'],
                'status' => 'assigned',
            ]
        ]);
    } catch (Exception $e) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to assign quest']);
    }
} else {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'No quests available']);
}
include "config.php";

$user_id = $_SESSION['user_id'];

$query = "
SELECT * FROM quests 
WHERE id NOT IN (
    SELECT quest_id FROM user_quests WHERE user_id=?
)
ORDER BY RAND() LIMIT 1
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$quest = $result->fetch_assoc();

$stmt2 = $conn->prepare("INSERT INTO user_quests (user_id, quest_id) VALUES (?,?)");
$stmt2->bind_param("ii", $user_id, $quest['id']);
$stmt2->execute();

echo json_encode($quest);