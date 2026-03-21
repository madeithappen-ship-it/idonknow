<?php
require_once(__DIR__ . '/config.php');

if (!is_admin() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    die('Unauthorized');
}

if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    die('Invalid CSRF token');
}

$quest_id = (int)$_POST['quest_id'];
if ($quest_id) {
    // Validate quest exists
    $stmt = $pdo->prepare("SELECT id FROM quests WHERE id = ?");
    $stmt->execute([$quest_id]);
    if ($stmt->fetch()) {
        $val = json_encode(['id' => $quest_id, 'date' => date('Y-m-d')]);
        $stmt = $pdo->prepare("INSERT INTO global_settings (setting_key, setting_value) VALUES ('daily_quest', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$val, $val]);
        @log_audit('SET_DAILY_QUEST', 'system', $quest_id, []);
    }
}

header('Location: admin.php?token=' . urlencode(config('admin_url_secret')) . '&section=quests');
exit;
