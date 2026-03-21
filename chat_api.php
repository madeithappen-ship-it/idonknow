<?php
require_once(__DIR__ . '/config.php');
header('Content-Type: application/json');

if (!is_logged_in() && !is_admin()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$is_admin = is_admin();

if ($action === 'send') {
    $message = trim($_POST['message'] ?? '');
    if (!$message) exit(json_encode(['success' => false]));
    
    if ($is_admin) {
        $user_id = (int)$_POST['user_id'];
        $sender = 'admin';
    } else {
        $user_id = $_SESSION['user_id'];
        $sender = 'user';
    }
    
    if ($user_id) {
        $stmt = $pdo->prepare("INSERT INTO chat_messages (sender_type, user_id, message) VALUES (?, ?, ?)");
        $stmt->execute([$sender, $user_id, $message]);
        echo json_encode(['success' => true]);
    }
    exit;
}

if ($action === 'typing') {
    if ($is_admin) {
        $user_id = (int)$_POST['user_id'];
        if ($user_id) {
            $stmt = $pdo->prepare("INSERT INTO chat_typing (user_id, admin_typing_until) VALUES (?, DATE_ADD(NOW(), INTERVAL 4 SECOND)) ON DUPLICATE KEY UPDATE admin_typing_until = DATE_ADD(NOW(), INTERVAL 4 SECOND)");
            $stmt->execute([$user_id]);
        }
    } else {
        $user_id = $_SESSION['user_id'];
        $stmt = $pdo->prepare("INSERT INTO chat_typing (user_id, user_typing_until) VALUES (?, DATE_ADD(NOW(), INTERVAL 4 SECOND)) ON DUPLICATE KEY UPDATE user_typing_until = DATE_ADD(NOW(), INTERVAL 4 SECOND)");
        $stmt->execute([$user_id]);
    }
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'fetch') {
    $last_id = (int)($_GET['last_id'] ?? 0);
    
    if ($is_admin) {
        $user_id = (int)($_GET['user_id'] ?? 0);
        if (!$user_id) {
            // Admin fetching conversation list
            $stmt = $pdo->query("
                SELECT u.id, u.username, MAX(c.created_at) as last_msg,
                (SELECT message FROM chat_messages WHERE user_id = u.id ORDER BY id DESC LIMIT 1) as last_text,
                (SELECT COUNT(*) FROM chat_messages WHERE user_id = u.id AND sender_type = 'user' AND is_read = 0) as unread
                FROM users u
                JOIN chat_messages c ON u.id = c.user_id
                GROUP BY u.id, u.username
                ORDER BY last_msg DESC
            ");
            echo json_encode(['success' => true, 'conversations' => $stmt->fetchAll()]);
            exit;
        }
        // Mark read for admin
        $pdo->prepare("UPDATE chat_messages SET is_read = 1 WHERE user_id = ? AND sender_type = 'user'")->execute([$user_id]);
    } else {
        $user_id = $_SESSION['user_id'];
        // Mark read for user
        $pdo->prepare("UPDATE chat_messages SET is_read = 1 WHERE user_id = ? AND sender_type = 'admin'")->execute([$user_id]);
    }
    
    // Fetch typing status
    $is_typing = false;
    if ($user_id) {
        $stmt = $pdo->prepare("SELECT * FROM chat_typing WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $typing_row = $stmt->fetch();
        if ($typing_row) {
            if ($is_admin) {
                $is_typing = strtotime($typing_row['user_typing_until'] ?? '1970-01-01') > time();
            } else {
                $is_typing = strtotime($typing_row['admin_typing_until'] ?? '1970-01-01') > time();
            }
        }
    }
    
    $stmt = $pdo->prepare("SELECT * FROM chat_messages WHERE user_id = ? AND id > ? ORDER BY id ASC");
    $stmt->execute([$user_id, $last_id]);
    echo json_encode(['success' => true, 'messages' => $stmt->fetchAll(), 'is_typing' => $is_typing]);
    exit;
}

if ($action === 'unread_count') {
    if ($is_admin) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM chat_messages WHERE sender_type = 'user' AND is_read = 0");
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM chat_messages WHERE user_id = ? AND sender_type = 'admin' AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
    }
    echo json_encode(['success' => true, 'count' => (int)$stmt->fetchColumn()]);
    exit;
}
