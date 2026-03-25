<?php
require_once(__DIR__ . '/config.php');
header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user = get_user();
$user_id = $user['id'];

$input = json_decode(file_get_contents('php://input'), true);
if (!$input && isset($_POST['action'])) {
    $input = $_POST;
}
$action = $input['action'] ?? '';

if ($action === 'search_user') {
    // Cache search results for 30 minutes per user
    $search_query = $input['query'] ?? '';
    $results = cache_remember(
        'friend_search_' . $user_id . '_' . md5($search_query),
        1800,
        function() use ($user_id, $pdo) {
            $stmt = $pdo->prepare("
                SELECT id, COALESCE(display_name, username) as name, avatar_url, level
                FROM users
                WHERE id != ?
                AND status = 'active'
                AND id NOT IN (
                    SELECT friend_id FROM friends WHERE user_id = ?
                    UNION 
                    SELECT user_id FROM friends WHERE friend_id = ?
                )
                ORDER BY last_seen DESC
                LIMIT 100
            ");
            $stmt->execute([$user_id, $user_id, $user_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    );
    echo json_encode(['success' => true, 'results' => $results]);
    exit;
}

if ($action === 'add_friend') {
    $target_id = (int)($input['target_id'] ?? 0);
    if ($target_id === $user_id) {
        echo json_encode(['success' => false, 'error' => 'Cannot add yourself']);
        exit;
    }
    
    // Check existing
    $stmt = $pdo->prepare("SELECT id FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)");
    $stmt->execute([$user_id, $target_id, $target_id, $user_id]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Friend request already exists']);
        exit;
    }
    
    $stmt = $pdo->prepare("INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, 'pending')");
    $stmt->execute([$user_id, $target_id]);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'respond_friend') {
    $request_id = (int)($input['request_id'] ?? 0);
    $response = $input['response'] ?? 'decline';
    
    // Validate request is targeting me
    $stmt = $pdo->prepare("SELECT * FROM friends WHERE id = ? AND friend_id = ? AND status = 'pending'");
    $stmt->execute([$request_id, $user_id]);
    $req = $stmt->fetch();
    
    if (!$req) {
        echo json_encode(['success' => false, 'error' => 'Invalid request']);
        exit;
    }
    
    if ($response === 'accept') {
        $pdo->prepare("UPDATE friends SET status = 'accepted' WHERE id = ?")->execute([$request_id]);
    } else {
        $pdo->prepare("DELETE FROM friends WHERE id = ?")->execute([$request_id]);
    }
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'list_friends') {
    // Cache friends list for 10 minutes
    // 1. Pending Requests (where I am the friend_id)
    $pending = cache_remember(
        'pending_friends_' . $user_id,
        600,
        function() use ($user_id, $pdo) {
            $stmt = $pdo->prepare("
                SELECT f.id as request_id, COALESCE(u.display_name, u.username) as name, u.avatar_url, u.level
                FROM friends f
                JOIN users u ON f.user_id = u.id
                WHERE f.friend_id = ? AND f.status = 'pending'
                ORDER BY f.created_at DESC
            ");
            $stmt->execute([$user_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    );
    
    // 2. Accepted Friends (I could be user_id or friend_id)
    $friends = cache_remember(
        'accepted_friends_' . $user_id,
        600,
        function() use ($user_id, $pdo) {
            $stmt = $pdo->prepare("
                SELECT u.id, COALESCE(u.display_name, u.username) as name, u.avatar_url, u.level, u.last_seen
                FROM friends f
                JOIN users u ON (u.id = f.user_id OR u.id = f.friend_id)
                WHERE (f.user_id = ? OR f.friend_id = ?) AND f.status = 'accepted' AND u.id != ?
                ORDER BY u.last_seen DESC
                LIMIT 50
            ");
            $stmt->execute([$user_id, $user_id, $user_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    );
    
    echo json_encode([
        'success' => true,
        'pending' => $pending,
        'friends' => $friends
    ]);
    exit;
}
    ");
    $stmt->execute([$user_id, $user_id, $user_id]);
    $friends = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Map online property
    foreach($friends as &$u) {
        $u['online'] = false;
        if ($u['last_seen'] && strtotime($u['last_seen']) > time() - 300) {
            $u['online'] = true;
        }
    }
    
    echo json_encode(['success' => true, 'pending' => $pending, 'friends' => $friends]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
