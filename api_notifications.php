<?php
/**
 * Real-time Notifications API
 * Returns: Admin notifications, Daily quest updates, and Chess challenge requests
 * Optimized for performance with efficient queries
 */
require_once(__DIR__ . '/config.php');

if (!is_logged_in()) {
    http_response_code(401);
    exit;
}

// Set cache headers for notification polling
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');

$user_id = $_SESSION['user_id'];
$since_id = (int)($_GET['since_id'] ?? 0);
$notifications = [];

// 1. FETCH NEW ADMIN NOTIFICATIONS (optimized query)
$stmt = $pdo->prepare("
    SELECT 
        id,
        message,
        image_path,
        created_at
    FROM admin_notifications 
    WHERE id > ? 
    AND (target_user_id IS NULL OR target_user_id = ?)
    AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
    ORDER BY id DESC
    LIMIT 5
");
$stmt->execute([$since_id, $user_id]);
$admin_notifs = $stmt->fetchAll();

foreach ($admin_notifs as $notif) {
    $notifications[] = [
        'type' => 'admin',
        'id' => $notif['id'],
        'title' => '📢 Announcement',
        'message' => $notif['message'],
        'image' => $notif['image_path'],
        'sound' => 'notification',
        'timestamp' => $notif['created_at']
    ];
}

// 2. FETCH DAILY QUEST UPDATES
$stmt = $pdo->query("SELECT setting_value FROM global_settings WHERE setting_key = 'daily_quest'");
$dq_setting = $stmt->fetch();
if ($dq_setting) {
    $dq_data = json_decode($dq_setting['setting_value'], true);
    if ($dq_data) {
        // Check if there's a global daily quest for today
        if (isset($dq_data['global']) && $dq_data['global']['date'] === date('Y-m-d')) {
            // Check if user has already been notified (we check via a session var)
            if (!isset($_SESSION['daily_quest_notified_id']) || $_SESSION['daily_quest_notified_id'] !== $dq_data['global']['id']) {
                $quest_id = $dq_data['global']['id'];
                $stmt = $pdo->prepare("SELECT title, description FROM quests WHERE id = ?");
                $stmt->execute([$quest_id]);
                $quest = $stmt->fetch();
                
                if ($quest) {
                    $notifications[] = [
                        'type' => 'daily_quest',
                        'id' => 'dq_' . $quest_id . '_' . date('Y-m-d'),
                        'title' => '⭐ Daily Quest',
                        'message' => $quest['title'],
                        'description' => $quest['description'],
                        'sound' => 'quest',
                        'timestamp' => date('Y-m-d H:i:s')
                    ];
                }
            }
        }
        
        // Check if there's a user-specific daily quest for today
        if (isset($dq_data['users'][$user_id]) && $dq_data['users'][$user_id]['date'] === date('Y-m-d')) {
            if (!isset($_SESSION['user_daily_quest_notified_id']) || $_SESSION['user_daily_quest_notified_id'] !== $dq_data['users'][$user_id]['id']) {
                $quest_id = $dq_data['users'][$user_id]['id'];
                $stmt = $pdo->prepare("SELECT title, description FROM quests WHERE id = ?");
                $stmt->execute([$quest_id]);
                $quest = $stmt->fetch();
                
                if ($quest) {
                    $notifications[] = [
                        'type' => 'daily_quest',
                        'id' => 'dq_user_' . $quest_id . '_' . date('Y-m-d'),
                        'title' => '⭐ Your Daily Quest',
                        'message' => $quest['title'],
                        'description' => $quest['description'],
                        'sound' => 'quest',
                        'timestamp' => date('Y-m-d H:i:s')
                    ];
                }
            }
        }
    }
}

// 3. FETCH CHESS CHALLENGE REQUESTS (if table exists)
$challenge_check = $pdo->prepare("SHOW TABLES LIKE 'chess_challenges'");
$challenge_check->execute();
if ($challenge_check->rowCount() > 0) {
    $stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.challenger_username,
            c.created_at
        FROM chess_challenges c
        WHERE c.opponent_user_id = ? 
        AND c.status = 'pending'
        AND c.created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        AND c.id > ?
        ORDER BY c.id DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id, $since_id]);
    $challenges = $stmt->fetchAll();
    
    foreach ($challenges as $challenge) {
        $notifications[] = [
            'type' => 'challenge',
            'id' => 'chess_' . $challenge['id'],
            'title' => '♟️ Chess Challenge',
            'message' => $challenge['challenger_username'] . ' challenged you to chess!',
            'sound' => 'challenge',
            'timestamp' => $challenge['created_at'],
            'action_url' => 'chess/index.php?challenge=' . $challenge['id']
        ];
    }
}

// Sort by timestamp (newest first)
usort($notifications, function($a, $b) {
    return strtotime($b['timestamp']) - strtotime($a['timestamp']);
});

// Get next ID to poll from
$next_id = 0;
if (!empty($notifications)) {
    foreach ($notifications as $notif) {
        if (isset($notif['id']) && is_numeric(substr($notif['id'], -5))) {
            $id = (int)substr($notif['id'], -10);
            $next_id = max($next_id, $id);
        }
    }
}

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'notifications' => $notifications,
    'next_poll_id' => $next_id ?: $since_id,
    'count' => count($notifications)
]);
