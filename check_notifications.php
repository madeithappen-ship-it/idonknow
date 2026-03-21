<?php
require_once(__DIR__ . '/config.php');

if (!is_logged_in()) {
    http_response_code(401);
    exit;
}

$stmt = $pdo->prepare("SELECT MAX(id) as max_id FROM admin_notifications WHERE target_user_id IS NULL OR target_user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$max_id = $stmt->fetch()['max_id'] ?? 0;

$stmt = $pdo->prepare("SELECT COUNT(id) as cnt FROM admin_notifications WHERE target_user_id IS NULL OR target_user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$cnt = $stmt->fetch()['cnt'] ?? 0;

header('Content-Type: application/json');
echo json_encode([
    'max_id' => (int)$max_id,
    'count' => (int)$cnt
]);
