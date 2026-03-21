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
        if ($message) {
            $stmt = $pdo->prepare("INSERT INTO admin_notifications (message) VALUES (?)");
            $stmt->execute([$message]);
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
