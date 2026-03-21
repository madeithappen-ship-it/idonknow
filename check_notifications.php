<?php
require_once(__DIR__ . '/config.php');

if (!is_logged_in()) {
    http_response_code(401);
    exit;
}

$stmt = $pdo->query("SELECT MAX(id) as max_id FROM admin_notifications");
$max_id = $stmt->fetch()['max_id'] ?? 0;

$stmt = $pdo->query("SELECT COUNT(id) as cnt FROM admin_notifications");
$cnt = $stmt->fetch()['cnt'] ?? 0;

header('Content-Type: application/json');
echo json_encode([
    'max_id' => (int)$max_id,
    'count' => (int)$cnt
]);
