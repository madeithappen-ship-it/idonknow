<?php
require_once(__DIR__ . '/config.php');

if (!is_admin() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    die('Unauthorized');
}

if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    die('Invalid CSRF token');
}

$action = $_POST['action'] ?? '';
$return_url = 'admin.php?token=' . urlencode(config('admin_url_secret')) . '&section=music';

if ($action === 'add') {
    $url = trim($_POST['youtube_url'] ?? '');
    $title = trim($_POST['title'] ?? 'Unknown Track');
    
    preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i', $url, $match);
    $vid = $match[1] ?? '';
    
    if ($vid) {
        $stmt = $pdo->prepare("INSERT INTO site_music (youtube_url, video_id, title) VALUES (?, ?, ?)");
        $stmt->execute([$url, $vid, $title]);
        $_SESSION['message'] = "Music track added successfully.";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Invalid YouTube URL.";
        $_SESSION['message_type'] = "error";
    }
} elseif ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $stmt = $pdo->prepare("DELETE FROM site_music WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['message'] = "Track deleted.";
        $_SESSION['message_type'] = "info";
    }
}

header('Location: ' . $return_url);
exit;
