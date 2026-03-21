<?php
require_once(__DIR__ . '/config.php');

if (!is_admin()) {
    http_response_code(403);
    die('Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }

    $action = $_POST['action'] ?? '';
    $return_url = $_POST['return_url'] ?? 'admin.php';

    if ($action === 'add') {
        $message = trim($_POST['message'] ?? '');
        $target_user = trim($_POST['target_user'] ?? '');
        $target_user_id = null;
        
        if (!empty($target_user) && strtolower($target_user) !== 'all') {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR id = ?");
            $stmt->execute([$target_user, $target_user]);
            $target_user_id = $stmt->fetchColumn() ?: null;
            if (!$target_user_id) {
                $_SESSION['message'] = "Target user not found!";
                $_SESSION['message_type'] = "error";
                header('Location: ' . $return_url);
                exit;
            }
        }

        if ($message) {
            $stmt = $pdo->prepare("INSERT INTO admin_notifications (target_user_id, message) VALUES (?, ?)");
            $stmt->execute([$target_user_id, $message]);
            @log_audit('ADD_NOTIFICATION', 'system', $pdo->lastInsertId(), ['message' => substr($message, 0, 50)]);
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $pdo->prepare("DELETE FROM admin_notifications WHERE id = ?");
            $stmt->execute([$id]);
            @log_audit('DELETE_NOTIFICATION', 'system', $id, []);
        }
    }

    header('Location: ' . $return_url);
    exit;
}
