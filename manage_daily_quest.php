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
$target = trim($_POST['target_user'] ?? '');
$date = date('Y-m-d');

if ($quest_id) {
    // Validate quest exists
    $stmt = $pdo->prepare("SELECT id FROM quests WHERE id = ?");
    $stmt->execute([$quest_id]);
    if ($stmt->fetch()) {
        
        // Fetch current setting
        $stmt_set = $pdo->query("SELECT setting_value FROM global_settings WHERE setting_key = 'daily_quest'");
        $current = $stmt_set->fetchColumn();
        
        $data = [];
        if ($current) {
            $parsed = json_decode($current, true);
            if (is_array($parsed)) {
                // If the old format was just an object, migrate it
                if (isset($parsed['id']) && !isset($parsed['global'])) {
                    $data = ['global' => $parsed, 'users' => []];
                } else {
                    $data = $parsed;
                }
            }
        }
        
        if (!isset($data['global'])) $data['global'] = null;
        if (!isset($data['users'])) $data['users'] = [];
        
        if (empty($target) || strtolower($target) === 'all') {
            $data['global'] = ['id' => $quest_id, 'date' => $date];
        } else {
            // Find user
            $stmt_u = $pdo->prepare("SELECT id FROM users WHERE username = ? OR id = ?");
            $stmt_u->execute([$target, $target]);
            if ($uid = $stmt_u->fetchColumn()) {
                $data['users'][$uid] = ['id' => $quest_id, 'date' => $date];
            } else {
                $_SESSION['message'] = "Target user not found!";
                $_SESSION['message_type'] = "error";
                header('Location: admin.php?token=' . urlencode(config('admin_url_secret')) . '&section=quests');
                exit;
            }
        }

        $val = json_encode($data);
        $stmt_update = $pdo->prepare("INSERT INTO global_settings (setting_key, setting_value) VALUES ('daily_quest', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt_update->execute([$val, $val]);
        
        @log_audit('SET_DAILY_QUEST', 'system', $quest_id, ['target' => $target]);
    }
}

header('Location: admin.php?token=' . urlencode(config('admin_url_secret')) . '&section=quests');
exit;
