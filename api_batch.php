<?php
/**
 * Batch API Handler
 * Allows multiple API requests in a single HTTP request
 * Reduces network overhead significantly
 */

require_once(__DIR__ . '/config.php');
header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Accept both JSON and form data
$input = json_decode(file_get_contents('php://input'), true);
if (!$input && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = $_POST;
}

if (!isset($input['requests']) || !is_array($input['requests'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid batch request. Expected "requests" array.'
    ]);
    exit;
}

$requests = $input['requests'];
if (count($requests) > 20) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Too many requests in batch (max 20 per batch)'
    ]);
    exit;
}

$responses = [];
$user_id = $_SESSION['user_id'];

foreach ($requests as $idx => $request) {
    $action = $request['action'] ?? null;
    $params = $request['params'] ?? [];
    
    try {
        switch ($action) {
            case 'get_user':
                $user = cache_remember(
                    'user_' . $user_id,
                    1800,
                    function() {
                        global $pdo, $user_id;
                        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
                        $stmt->execute([$user_id]);
                        return $stmt->fetch();
                    }
                );
                $responses[$idx] = ['success' => true, 'data' => $user];
                break;
                
            case 'get_leaderboard':
                $limit = min((int)($params['limit'] ?? 10), 50);
                $leaderboard = cache_remember(
                    'leaderboard_' . $limit,
                    3600,
                    function() use ($limit) {
                        global $pdo;
                        $stmt = $pdo->prepare("
                            SELECT id, username, level, xp, total_completed, avatar_url
                            FROM users
                            WHERE status = 'active'
                            ORDER BY level DESC, xp DESC
                            LIMIT ?
                        ");
                        $stmt->execute([$limit]);
                        return $stmt->fetchAll();
                    }
                );
                $responses[$idx] = ['success' => true, 'data' => $leaderboard];
                break;
                
            case 'get_user_stats':
                $target_id = (int)($params['user_id'] ?? $user_id);
                $stats = cache_remember(
                    'stats_' . $target_id,
                    600,
                    function() use ($target_id) {
                        global $pdo;
                        $stmt = $pdo->prepare("
                            SELECT 
                                level,
                                xp,
                                total_completed,
                                current_streak,
                                COUNT(DISTINCT CASE WHEN status = 'approved' THEN id END) as approved_quests
                            FROM users
                            LEFT JOIN user_quests ON users.id = user_id
                            WHERE users.id = ?
                            GROUP BY users.id
                        ");
                        $stmt->execute([$target_id]);
                        return $stmt->fetch();
                    }
                );
                $responses[$idx] = ['success' => true, 'data' => $stats];
                break;
                
            case 'get_friends':
                $friends = cache_remember(
                    'friends_' . $user_id,
                    600,
                    function() use ($user_id) {
                        global $pdo;
                        $stmt = $pdo->prepare("
                            SELECT u.id, u.username, u.avatar_url, u.level, u.last_seen
                            FROM friends f
                            JOIN users u ON (u.id = f.user_id OR u.id = f.friend_id)
                            WHERE (f.user_id = ? OR f.friend_id = ?) AND f.status = 'accepted' AND u.id != ?
                            ORDER BY u.last_seen DESC
                            LIMIT 50
                        ");
                        $stmt->execute([$user_id, $user_id, $user_id]);
                        return $stmt->fetchAll();
                    }
                );
                $responses[$idx] = ['success' => true, 'data' => $friends];
                break;
                
            case 'get_active_quests':
                $limit = min((int)($params['limit'] ?? 5), 10);
                $quests = cache_remember(
                    'active_quests_' . $user_id . '_' . $limit,
                    300,
                    function() use ($limit, $user_id) {
                        global $pdo;
                        $stmt = $pdo->prepare("
                            SELECT uq.id as user_quest_id, uq.status, uq.attempts, q.*
                            FROM user_quests uq
                            JOIN quests q ON uq.quest_id = q.id
                            WHERE uq.user_id = ? AND uq.status IN ('assigned', 'in_progress', 'submitted')
                            ORDER BY uq.assigned_at DESC
                            LIMIT ?
                        ");
                        $stmt->execute([$user_id, $limit]);
                        return $stmt->fetchAll();
                    }
                );
                $responses[$idx] = ['success' => true, 'data' => $quests];
                break;
                
            case 'get_notifications':
                $limit = min((int)($params['limit'] ?? 10), 20);
                $notifications = $pdo->prepare("
                    SELECT * FROM admin_notifications 
                    WHERE (target_user_id IS NULL OR target_user_id = ?) 
                    ORDER BY created_at DESC 
                    LIMIT ?
                ");
                $notifications->execute([$user_id, $limit]);
                $responses[$idx] = ['success' => true, 'data' => $notifications->fetchAll()];
                break;
                
            default:
                $responses[$idx] = ['success' => false, 'error' => 'Unknown action: ' . $action];
        }
    } catch (Exception $e) {
        error_log("Batch API error: " . $e->getMessage());
        $responses[$idx] = ['success' => false, 'error' => 'Internal server error'];
    }
}

http_response_code(200);
echo json_encode([
    'success' => true,
    'responses' => $responses,
    'count' => count($responses)
]);
