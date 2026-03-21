<?php
require_once(__DIR__ . '/config.php');

if (!is_admin()) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$stmt = $pdo->query("SELECT COUNT(*) as count FROM submissions WHERE verification_status = 'pending'");
$count = $stmt->fetch()['count'];

header('Content-Type: application/json');
echo json_encode(['count' => (int)$count]);
