<?php
/**
 * Admin Panel Dashboard
 * Hidden URL: /x9_admin_portal_hidden/admin.php
 */

require_once(__DIR__ . '/config.php');

// Check admin secret URL
$url_secret = config('admin_url_secret');
if (isset($_GET['token']) && $_GET['token'] !== $url_secret) {
    http_response_code(404);
    die('Not found');
}

// Must be logged in as admin
if (!is_admin()) {
    // If no token, redirect to secret login path; if invalid token, we have 404 above
    header('Location: admin-login.php?token=' . urlencode($url_secret));
    exit;
}

$admin = $_SESSION;
$section = $_GET['section'] ?? 'dashboard';
$action = $_GET['action'] ?? null;

// Handle quest management
if ($section === 'quests' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $quest_action = $_POST['quest_action'] ?? '';
    
    if ($quest_action === 'add' && verify_csrf($_POST['csrf_token'] ?? '')) {
        $target_input = trim($_POST['target_user'] ?? '');
        $target_user_id = null;
        $is_active = 1;

        if (!empty($target_input)) {
            if (is_numeric($target_input)) {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
                $stmt->execute([$target_input]);
            } else {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$target_input]);
            }
            $target_user_id = $stmt->fetchColumn();
            if ($target_user_id) $is_active = 0; // Hide targeted quests from public pool randomly assigned by get_quest
        }

        $stmt = $pdo->prepare("
            INSERT INTO quests (title, description, difficulty, type, xp_reward, keywords, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $_POST['title'],
            $_POST['description'],
            $_POST['difficulty'],
            $_POST['type'],
            (int)$_POST['xp_reward'],
            $_POST['keywords'] ?? '',
            $is_active
        ]);
        
        $new_quest_id = $pdo->lastInsertId();

        if ($target_user_id) {
            // Assign instantly to the dashboard of the specific targeted player natively
            $stmt = $pdo->prepare("INSERT INTO user_quests (user_id, quest_id, status) VALUES (?, ?, 'assigned')");
            $stmt->execute([$target_user_id, $new_quest_id]);
        }
        
        log_audit('ADD_QUEST', 'quest', $new_quest_id, [
            'title' => $_POST['title'],
            'difficulty' => $_POST['difficulty']
        ]);
        
        $_SESSION['message'] = "Targeted quest deployed successfully!";
        $_SESSION['message_type'] = "success";
        header("Location: admin.php?section=quests");
        exit;
    }
}

// Handle User Management
if ($section === 'users' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_action = $_POST['user_action'] ?? '';
    
    if ($user_action === 'add' && verify_csrf($_POST['csrf_token'] ?? '')) {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $level = (int)($_POST['level'] ?? 1);
        $xp = (int)($_POST['xp'] ?? 0);
        
        if ($username && $email && $password) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, level, xp) VALUES (?, ?, ?, ?, ?)");
            try {
                $stmt->execute([$username, $email, $hash, $level, $xp]);
                $_SESSION['message'] = "User $username created successfully!";
                $_SESSION['message_type'] = "success";
            } catch (PDOException $e) {
                $_SESSION['message'] = "Error creating user: " . $e->getMessage();
                $_SESSION['message_type'] = "error";
            }
        } else {
            $_SESSION['message'] = "Please fill completely.";
            $_SESSION['message_type'] = "error";
        }
        header("Location: admin.php?section=users&token=" . urlencode($url_secret));
        exit;
    } else if ($user_action === 'suspend' && verify_csrf($_POST['csrf_token'] ?? '')) {
        $user_id = (int)($_POST['user_id'] ?? 0);
        if ($user_id > 0) {
            $stmt = $pdo->prepare("UPDATE users SET status = 'suspended' WHERE id = ?");
            $stmt->execute([$user_id]);
            log_audit('SUSPEND_USER', 'user', $user_id, ['new_status' => 'suspended']);
            $_SESSION['message'] = "User has been suspended.";
            $_SESSION['message_type'] = "success";
        }
        header("Location: admin.php?section=users&token=" . urlencode($url_secret));
        exit;
    } else if ($user_action === 'ban' && verify_csrf($_POST['csrf_token'] ?? '')) {
        $user_id = (int)($_POST['user_id'] ?? 0);
        if ($user_id > 0) {
            $stmt = $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
            $stmt->execute([$user_id]);
            log_audit('BAN_USER', 'user', $user_id, ['new_status' => 'inactive']);
            $_SESSION['message'] = "User has been banned.";
            $_SESSION['message_type'] = "success";
        }
        header("Location: admin.php?section=users&token=" . urlencode($url_secret));
        exit;
    } else if ($user_action === 'activate' && verify_csrf($_POST['csrf_token'] ?? '')) {
        $user_id = (int)($_POST['user_id'] ?? 0);
        if ($user_id > 0) {
            $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
            $stmt->execute([$user_id]);
            log_audit('ACTIVATE_USER', 'user', $user_id, ['new_status' => 'active']);
            $_SESSION['message'] = "User has been reactivated.";
            $_SESSION['message_type'] = "success";
        }
        header("Location: admin.php?section=users&token=" . urlencode($url_secret));
        exit;
    } else if ($user_action === 'delete' && verify_csrf($_POST['csrf_token'] ?? '')) {
        $user_id = (int)($_POST['user_id'] ?? 0);
        if ($user_id > 0) {
            // Delete related data first (cascading)
            $pdo->prepare("DELETE FROM user_quests WHERE user_id = ?")->execute([$user_id]);
            $pdo->prepare("DELETE FROM submissions WHERE user_id = ?")->execute([$user_id]);
            $pdo->prepare("DELETE FROM friends WHERE user_id = ? OR friend_id = ?")->execute([$user_id, $user_id]);
            $pdo->prepare("DELETE FROM admin_notifications WHERE target_user_id = ?")->execute([$user_id]);
            
            // Delete user
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            log_audit('DELETE_USER', 'user', $user_id, []);
            $_SESSION['message'] = "User has been permanently deleted.";
            $_SESSION['message_type'] = "success";
        }
        header("Location: admin.php?section=users&token=" . urlencode($url_secret));
        exit;
    }
}

// Fetch stats
$stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
$user_count = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM quests WHERE is_active = 1");
$quest_count = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM submissions WHERE verification_status = 'pending'");
$pending_submissions = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM submissions WHERE verification_status = 'approved'");
$approved_count = $stmt->fetch()['count'];

// Fetch pending submissions
$stmt = $pdo->prepare("
    SELECT s.*, u.username, q.title
    FROM submissions s
    JOIN users u ON s.user_id = u.id
    JOIN quests q ON s.quest_id = q.id
    WHERE s.verification_status = 'pending'
    ORDER BY s.submitted_at DESC
    LIMIT 10
");
$stmt->execute();
$pending = $stmt->fetchAll();

// Fetch all submissions too
$stmt = $pdo->prepare("
    SELECT s.*, u.username, q.title
    FROM submissions s
    JOIN users u ON s.user_id = u.id
    JOIN quests q ON s.quest_id = q.id
    ORDER BY s.submitted_at DESC
    LIMIT 100
");
$stmt->execute();
$all_submissions = $stmt->fetchAll();

// Fetch quests
$stmt = $pdo->prepare("
    SELECT * FROM quests
    WHERE is_active = 1
    ORDER BY created_at DESC
    LIMIT 20
");
$stmt->execute();
$quests = $stmt->fetchAll();

// Fetch users
$stmt = $pdo->prepare("SELECT id, username, email, level, xp, total_completed, status, created_at FROM users ORDER BY created_at DESC LIMIT 100");
$stmt->execute();
$users = $stmt->fetchAll();

// Fetch admin notifications
$stmt = $pdo->query("SELECT n.*, u.username as target_name FROM admin_notifications n LEFT JOIN users u ON n.target_user_id = u.id ORDER BY n.created_at DESC LIMIT 20");
$admin_notifications = $stmt->fetchAll();

// Fetch music
$stmt = $pdo->query("SELECT * FROM site_music ORDER BY id DESC");
$site_music = $stmt->fetchAll();

// Fetch music
$stmt = $pdo->query("SELECT * FROM site_music ORDER BY id DESC");
$site_music = $stmt->fetchAll();

// Fetch daily quest
$stmt = $pdo->query("SELECT setting_value FROM global_settings WHERE setting_key = 'daily_quest'");
$dq_setting = $stmt->fetch();
$dq_raw = $dq_setting ? json_decode($dq_setting['setting_value'], true) : null;

$current_dq = null;
if ($dq_raw) {
    if (isset($dq_raw['id']) && !isset($dq_raw['global'])) {
        $current_dq = ['global' => $dq_raw, 'users' => []];
    } else {
        $current_dq = $dq_raw;
    }
}

$dq_global_title = 'None';
$user_dq_list = [];

if ($current_dq) {
    if (isset($current_dq['global']) && $current_dq['global']['date'] === date('Y-m-d')) {
        $stmt = $pdo->prepare("SELECT title FROM quests WHERE id = ?");
        $stmt->execute([$current_dq['global']['id']]);
        $dq_global_title = $stmt->fetchColumn() ?: 'Unknown Quest';
    }
    
    if (!empty($current_dq['users'])) {
        foreach ($current_dq['users'] as $uid => $qdata) {
            if ($qdata['date'] === date('Y-m-d')) {
                $stmt = $pdo->prepare("SELECT title FROM quests WHERE id = ?");
                $stmt->execute([$qdata['id']]);
                $qtitle = $stmt->fetchColumn() ?: 'Unknown';
                
                $stmt_u = $pdo->prepare("SELECT username FROM users WHERE id = ?");
                $stmt_u->execute([$uid]);
                $uname = $stmt_u->fetchColumn() ?: "ID: $uid";
                
                $user_dq_list[] = "$uname -> $qtitle";
            }
        }
    }
}

$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0f0f1e;
            color: #fff;
        }
        
        .admin-container {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 250px;
            background: #1a1a2e;
            padding: 20px;
            border-right: 1px solid #333;
        }
        
        .sidebar h2 {
            font-size: 18px;
            margin-bottom: 20px;
            color: #4CAF50;
        }
        
        .sidebar nav a {
            display: block;
            padding: 12px 15px;
            color: #aaa;
            text-decoration: none;
            border-radius: 6px;
            margin-bottom: 8px;
            transition: all 0.3s ease;
        }
        
        .sidebar nav a:hover,
        .sidebar nav a.active {
            background: #264f36;
            color: #4CAF50;
        }
        
        .main-content {
            flex: 1;
            padding: 30px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #333;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: #1a1a2e;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid #4CAF50;
        }
        
        .stat-card h3 {
            font-size: 12px;
            color: #aaa;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        
        .stat-card .value {
            font-size: 32px;
            font-weight: bold;
            color: #4CAF50;
        }
        
        .section {
            display: none;
        }
        
        .section.active {
            display: block;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            background: #1a1a2e;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .table th {
            background: #264f36;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }
        
        .table td {
            padding: 15px;
            border-bottom: 1px solid #333;
        }
        
        .table tbody tr:hover {
            background: #262641;
        }
        
        button {
            padding: 8px 16px;
            background: #4CAF50;
            border: none;
            border-radius: 6px;
            color: #fff;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
        }
        
        button:hover {
            background: #45a049;
        }
        
        button.danger {
            background: #f44336;
        }
        
        button.danger:hover {
            background: #da190b;
        }
        
        .logout-btn {
            background: #f44336;
            margin-left: auto;
        }
        
        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            color: #fff;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-sm {
            padding: 4px 8px;
            font-size: 11px;
        }
        
        .btn-success {
            background: #4CAF50;
        }
        
        .btn-success:hover {
            background: #45a049;
        }
        
        .btn-danger {
            background: #f44336;
        }
        
        .btn-danger:hover {
            background: #da190b;
        }
        
        .btn-info {
            background: #2196F3;
        }
        
        .btn-info:hover {
            background: #0b7dda;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="sidebar">
            <h2>Admin Panel</h2>
            <nav>
                <a href="?token=<?php echo $url_secret; ?>&section=dashboard" class="<?= $section === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
                <a href="?token=<?php echo $url_secret; ?>&section=notifications" class="<?= $section === 'notifications' ? 'active' : '' ?>">Notifications</a>
                <a href="?token=<?php echo $url_secret; ?>&section=music" class="<?= $section === 'music' ? 'active' : '' ?>">Feel The Vibe</a>
                <a href="?token=<?php echo $url_secret; ?>&section=submissions" class="<?= $section === 'submissions' ? 'active' : '' ?>">Submissions</a>
                <a href="?token=<?php echo $url_secret; ?>&section=quests" class="<?= $section === 'quests' ? 'active' : '' ?>">Manage Quests</a>
                <a href="?token=<?php echo $url_secret; ?>&section=users" class="<?= $section === 'users' ? 'active' : '' ?>">Users</a>
                <a href="?token=<?php echo $url_secret; ?>&section=settings" class="<?= $section === 'settings' ? 'active' : '' ?>">⚙️ Settings</a>
                <a href="logout.php" class="logout-btn">Logout</a>
            </nav>
        </div>
        
        <div class="main-content">
            <div class="header">
                <h1>Admin Panel</h1>
            </div>
            
            <!-- Dashboard Section -->
            <div id="dashboard" class="section <?= $section === 'dashboard' ? 'active' : '' ?>">
                <div class="stats">
                    <div class="stat-card">
                        <h3>Active Users</h3>
                        <div class="value"><?php echo $user_count; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Total Quests</h3>
                        <div class="value"><?php echo $quest_count; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Pending Verifications</h3>
                        <div class="value"><?php echo $pending_submissions; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Approved Submissions</h3>
                        <div class="value"><?php echo $approved_count; ?></div>
                    </div>
                </div>
                
                <h2>Recent Submissions</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Quest</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending as $sub): ?>
                        <tr>
                            <td><?php echo escape($sub['username']); ?></td>
                            <td><?php echo escape($sub['title']); ?></td>
                            <td><span style="color: #FFC107;"><?php echo escape($sub['verification_status']); ?></span></td>
                            <td><?php echo date('M d, H:i', strtotime($sub['submitted_at'])); ?></td>
                            <td>
                                <a href="?token=<?php echo $url_secret; ?>&section=submissions&view=<?php echo $sub['id']; ?>">
                                    <button>Review</button>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Notifications Section -->
            <div id="notifications" class="section <?= $section === 'notifications' ? 'active' : '' ?>">
                <h2>Global Notifications</h2>
                
                <div style="background: rgba(33, 150, 243, 0.1); border: 1px solid rgba(33, 150, 243, 0.3); padding: 20px; border-radius: 10px; margin-bottom: 30px;">
                    <h3 style="color: #64B5F6; margin-bottom: 15px;">📢 Send Notification / Announcement</h3>
                    <form method="POST" action="manage_notifications.php" enctype="multipart/form-data" style="display: flex; gap: 10px; flex-direction: column;">
                        <input type="hidden" name="csrf_token" value="<?php echo escape($token); ?>">
                        <input type="hidden" name="action" value="add">
                        <textarea name="message" placeholder="Type the announcement or direct message here..." style="padding: 10px; background: #262641; border: 1px solid #333; color: #fff; border-radius: 6px; resize: vertical; min-height: 80px;"></textarea>
                        
                        <div style="background: rgba(0,0,0,0.2); padding: 10px; border-radius: 6px; border: 1px dashed #4CAF50;">
                            <label style="color: #ccc; font-size: 13px; display: block; margin-bottom: 5px;">🖼️/🎥 Attach an Image or Video (Optional):</label>
                            <input type="file" name="image" accept="image/*,video/*" style="color: #fff; font-size: 13px;">
                        </div>

                        <div style="display: flex; gap: 10px;">
                            <input type="text" name="target_user" placeholder="All Users (or enter Username/ID for private message)" style="padding: 8px; background: #262641; border: 1px solid #333; color: #fff; border-radius: 6px; flex: 1;">
                            <button type="submit" style="background: #2196F3; color: #fff; border: none; padding: 0 20px; border-radius: 6px; font-weight: bold; cursor: pointer;">Send Now</button>
                        </div>
                    </form>
                </div>
                
                <h3 style="margin-bottom: 15px;">Active Notifications</h3>
                <?php if (empty($admin_notifications)): ?>
                    <p style="color: #ccc;">No active notifications.</p>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <?php foreach ($admin_notifications as $note): ?>
                            <div style="background: rgba(255,255,255,0.05); padding: 15px; border-radius: 6px; position: relative;">
                                <div style="margin-bottom: 5px;">
                                    <?php if ($note['target_user_id']): ?>
                                        <span class="badge" style="background: #9c27b0; color: #fff; margin-right: 10px;">Private to: <?php echo escape($note['target_name'] ?? 'User '.$note['target_user_id']); ?></span>
                                    <?php else: ?>
                                        <span class="badge" style="background: #2196F3; color: #fff; margin-right: 10px;">Global</span>
                                    <?php endif; ?>
                                    <small style="color: #aaa;"><?php echo date('M d, g:i A', strtotime($note['created_at'])); ?></small>
                                </div>
                                <p><?php echo format_text($note['message']); ?></p>
                                <?php if (!empty($note['image_path'])): ?>
                                    <div style="margin-top: 10px;">
                                        <?php $is_vid = preg_match('/\.(mp4|webm)$/i', $note['image_path']); ?>
                                        <a href="<?php echo escape($note['image_path']); ?>" target="_blank" style="color: <?php echo $is_vid ? '#f0f' : '#64B5F6'; ?>; text-decoration: underline; font-size: 12px;">
                                            <?php echo $is_vid ? '🎥 View Attached Video' : '🖼️ View Attached Image'; ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <form method="POST" action="manage_notifications.php" style="position: absolute; top: 15px; right: 15px;" onsubmit="return confirm('Delete this notification?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $note['id']; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo escape($token); ?>">
                                    <input type="hidden" name="return_url" value="admin.php?token=<?php echo urlencode($url_secret); ?>&section=notifications">
                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
        <!-- Radio Hub -->
        <div id="music" class="section <?= $section === 'music' ? 'active' : '' ?>">
            <h2>🎧 Feel The Vibe</h2>
            
            <div class="card" style="margin-bottom: 30px;">
                <h3 style="margin-bottom: 15px;">Add YouTube Track</h3>
                <form method="POST" action="manage_music.php" style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <input type="hidden" name="csrf_token" value="<?php echo escape($token); ?>">
                    <input type="hidden" name="action" value="add">
                    <input type="text" name="title" required placeholder="Track Name / Artist" style="padding: 10px; flex: 1; min-width: 200px; background: #262641; border: 1px solid #333; color: #fff; border-radius: 6px;">
                    <input type="url" name="youtube_url" required placeholder="https://www.youtube.com/watch?v=..." style="padding: 10px; flex: 2; min-width: 300px; background: #262641; border: 1px solid #333; color: #fff; border-radius: 6px;">
                    <button type="submit" class="btn btn-primary">Add Track</button>
                </form>
            </div>
            
            <h3>Active Stations / Tracks</h3>
            <table class="table" style="margin-top: 15px;">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>YouTube URL</th>
                        <th>Added On</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($site_music)): ?>
                    <tr><td colspan="4" style="text-align: center; color: #aaa; padding: 20px;">No tracks currently streaming on Feel The Vibe.</td></tr>
                    <?php else: foreach ($site_music as $audio): ?>
                    <tr>
                        <td><strong><?php echo escape($audio['title']); ?></strong></td>
                        <td><a href="<?php echo escape($audio['youtube_url']); ?>" target="_blank" style="color: #64B5F6; text-decoration: underline;"><?php echo escape($audio['youtube_url']); ?></a></td>
                        <td><?php echo date('M d, Y', strtotime($audio['created_at'])); ?></td>
                        <td>
                            <form method="POST" action="manage_music.php" onsubmit="return confirm('Permanently remove this track from the station?');" style="margin: 0;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $audio['id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo escape($token); ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Quests Section -->
        <div id="quests" class="section <?= $section === 'quests' ? 'active' : '' ?>">
                <h2>Quest Management</h2>
                
                <div style="background: rgba(255, 193, 7, 0.1); border: 1px solid rgba(255, 193, 7, 0.3); padding: 20px; border-radius: 10px; margin-bottom: 30px;">
                    <h3 style="color: #FFC107; margin-bottom: 10px;">🌟 Special Daily Quests</h3>
                    <p style="margin-bottom: 5px;">Global Daily Quest: <strong><?php echo escape($dq_global_title); ?></strong></p>
                    <?php if ($user_dq_list): ?>
                        <p style="margin-bottom: 15px; font-size: 14px; color: #ffeb99;">Targeted users: <?php echo escape(implode(', ', $user_dq_list)); ?></p>
                    <?php else: ?>
                        <p style="margin-bottom: 15px; font-size: 14px; color: #aaa;">No specific users targeted today.</p>
                    <?php endif; ?>
                    <form method="POST" action="manage_daily_quest.php" style="display: flex; gap: 10px;">
                        <input type="hidden" name="csrf_token" value="<?php echo escape($token); ?>">
                        <input type="number" name="quest_id" required placeholder="Quest ID" style="padding: 8px; background: #262641; border: 1px solid #333; color: #fff; border-radius: 6px; width: 100px;">
                        <input type="text" name="target_user" placeholder="All Users (or username/ID)" style="padding: 8px; background: #262641; border: 1px solid #333; color: #fff; border-radius: 6px; width: 220px;">
                        <button type="submit" style="background: #FFC107; color: #000;">Set Quest</button>
                    </form>
                </div>
                
                <form method="POST" style="margin-bottom: 30px; background: #1a1a2e; padding: 20px; border-radius: 10px;">
                    <h3 style="margin-bottom: 15px;">Add New Quest</h3>
                    
                    <input type="hidden" name="quest_action" value="add">
                    <input type="hidden" name="csrf_token" value="<?php echo escape($token); ?>">
                    
                    <div style="margin-bottom: 15px;">
                        <label>Title</label>
                        <input type="text" name="title" required style="width: 100%; padding: 8px; background: #262641; border: 1px solid #333; color: #fff; border-radius: 6px;">
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label>Description</label>
                        <textarea name="description" required rows="4" style="width: 100%; padding: 8px; background: #262641; border: 1px solid #333; color: #fff; border-radius: 6px;"></textarea>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div>
                            <label>Difficulty</label>
                            <select name="difficulty" style="width: 100%; padding: 8px; background: #262641; border: 1px solid #333; color: #fff; border-radius: 6px;">
                                <option value="easy">Easy</option>
                                <option value="medium" selected>Medium</option>
                                <option value="hard">Hard</option>
                                <option value="insane">Insane</option>
                            </select>
                        </div>
                        <div>
                            <label>Type</label>
                            <select name="type" style="width: 100%; padding: 8px; background: #262641; border: 1px solid #333; color: #fff; border-radius: 6px;">
                                <option value="dare" selected>Dare</option>
                                <option value="truth">Truth</option>
                                <option value="social">Social</option>
                                <option value="dark_humor">Dark Humor</option>
                                <option value="challenge">Challenge</option>
                                <option value="physical">Physical</option>
                            </select>
                        </div>
                        <div>
                            <label>XP Reward</label>
                            <input type="number" name="xp_reward" value="10" min="1" style="width: 100%; padding: 8px; background: #262641; border: 1px solid #333; color: #fff; border-radius: 6px;">
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label>Keywords (comma separated)</label>
                        <input type="text" name="keywords" placeholder="proof, screenshot, selfie, etc." style="width: 100%; padding: 8px; background: #262641; border: 1px solid #333; color: #fff; border-radius: 6px;">
                    </div>

                    <div style="margin-bottom: 25px; padding: 15px; border: 1px dashed #f0f; border-radius: 8px; background: rgba(255, 0, 255, 0.05);">
                        <label style="color: #f0f; font-weight: bold;">SPECIAL TARGET ASSIGNMENT (Optional)</label>
                        <p style="font-size: 13px; color: #aaa; margin: 5px 0 10px 0;">Enter a specific Username or User ID. If filled, this quest will bypass the random pool and instantly appear ONLY on that user's dashboard!</p>
                        <input type="text" name="target_user" placeholder="Target Username or ID" style="width: 100%; padding: 8px; background: #262641; border: 1px solid #333; color: #fff; border-radius: 6px;">
                    </div>
                    
                    <button type="submit">Add Quest</button>
                </form>
                
                <h3>Recent Quests</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Difficulty</th>
                            <th>XP</th>
                            <th>Added</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($quests as $quest): ?>
                        <tr>
                            <td><?php echo escape($quest['title']); ?></td>
                            <td><?php echo escape($quest['type']); ?></td>
                            <td><?php echo escape($quest['difficulty']); ?></td>
                            <td><?php echo $quest['xp_reward']; ?></td>
                            <td><?php echo date('M d', strtotime($quest['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Submissions Section -->
            <div id="submissions" class="section <?= $section === 'submissions' ? 'active' : '' ?>">
                <h2>All Submissions</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Quest</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Proof</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_submissions as $sub): ?>
                        <tr>
                            <td><?php echo $sub['id']; ?></td>
                            <td><?php echo escape($sub['username']); ?></td>
                            <td><?php echo escape($sub['title']); ?></td>
                            <td><?php echo escape($sub['verification_status']); ?></td>
                            <td><?php echo date('M d, H:i', strtotime($sub['submitted_at'])); ?></td>
                            <td>
                                <?php if ((!empty($sub['file_path']) && file_exists($sub['file_path'])) || !empty($sub['text_proof'])): ?>
                                    <a href="view_proof.php?id=<?php echo $sub['id']; ?>" target="_blank" class="btn btn-info btn-sm">View Proof</a>
                                <?php else: ?>
                                    No proof
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($sub['verification_status'] === 'pending'): ?>
                                    <button class="btn btn-success btn-sm" onclick="approveSubmission(<?php echo $sub['id']; ?>)">Approve</button>
                                    <button class="btn btn-warning btn-sm" style="background:#ff9800; border-color:#ff9800; color:#fff;" onclick="rejectSubmission(<?php echo $sub['id']; ?>)">Reject</button>
                                <?php endif; ?>
                                <button class="btn btn-danger btn-sm" style="background:#cc0000; border-color:#cc0000;" onclick="deleteSubmission(<?php echo $sub['id']; ?>)">Delete</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Users Section -->
            <div id="users" class="section <?= $section === 'users' ? 'active' : '' ?>">
                
                <div class="card" style="margin-bottom: 30px;">
                    <h3 style="margin-bottom: 15px;">➕ Create New User</h3>
                    <form method="POST" action="admin.php?section=users&token=<?php echo urlencode($url_secret); ?>" style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <input type="hidden" name="csrf_token" value="<?php echo escape($token); ?>">
                        <input type="hidden" name="user_action" value="add">
                        
                        <input type="text" name="username" required placeholder="Username" style="padding: 10px; flex: 1; min-width: 150px; background: #262641; border: 1px solid #333; color: #fff; border-radius: 6px;">
                        <input type="email" name="email" required placeholder="Email Address" style="padding: 10px; flex: 1; min-width: 200px; background: #262641; border: 1px solid #333; color: #fff; border-radius: 6px;">
                        <input type="text" name="password" required placeholder="Raw Password" style="padding: 10px; flex: 1; min-width: 150px; background: #262641; border: 1px solid #333; color: #fff; border-radius: 6px;">
                        
                        <div style="display: flex; gap: 10px;">
                            <input type="number" name="level" value="1" min="1" placeholder="Lvl" style="padding: 10px; width: 70px; background: #262641; border: 1px solid #333; color: #fff; border-radius: 6px;">
                            <input type="number" name="xp" value="0" min="0" placeholder="XP" style="padding: 10px; width: 80px; background: #262641; border: 1px solid #333; color: #fff; border-radius: 6px;">
                            <button type="submit" class="btn btn-primary">Create Account</button>
                        </div>
                    </form>
                </div>

                <h2>Registered Users</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Level</th>
                            <th>XP</th>
                            <th>Total Completed</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?php echo $u['id']; ?></td>
                            <td><?php echo escape($u['username']); ?></td>
                            <td><?php echo escape($u['email']); ?></td>
                            <td><?php echo $u['level']; ?></td>
                            <td><?php echo $u['xp']; ?></td>
                            <td><?php echo $u['total_completed']; ?></td>
                            <td>
                                <span class="badge <?php echo $u['status'] === 'active' ? 'badge-active' : 'badge-inactive'; ?>">
                                    <?php echo ucfirst($u['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                            <td>
                                <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                    <button class="btn btn-primary btn-sm" onclick="startAdminChat(<?php echo $u['id']; ?>, '<?php echo escape(addslashes($u['username'])); ?>')">Chat</button>
                                    <button class="btn btn-sm" style="background: #FF9800; color: white; border: none; cursor: pointer; padding: 6px 12px; border-radius: 4px; font-size: 12px;" onclick="showUserActions(<?php echo $u['id']; ?>, '<?php echo escape($u['username']); ?>', '<?php echo $u['status']; ?>')">
                                        <i class="fas fa-cog"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Settings Section -->
            <div id="settings" class="section <?= $section === 'settings' ? 'active' : '' ?>">
                <h2>⚙️ System Settings</h2>
                
                <div class="card">
                    <h3 style="margin-bottom: 15px; color: #4CAF50;">🤖 AI Proof Verification</h3>
                    <p style="margin-bottom: 15px; color: #aaa;">Enable AI to automatically verify, approve, or reject quest proof submissions based on keywords and confidence scores.</p>
                    
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <label for="ai-verify-toggle" style="cursor: pointer; display: flex; align-items: center; gap: 8px;">
                                <input type="checkbox" id="ai-verify-toggle" style="width: 20px; height: 20px; cursor: pointer;">
                                <span id="ai-status-text">Load...</span>
                            </label>
                        </div>
                        <button id="ai-toggle-btn" class="btn btn-info" style="display: none;" onclick="toggleAIVerify()">Update Preference</button>
                    </div>
                    <div id="ai-message" style="margin-top: 10px; font-size: 12px; color: #aaa;"></div>
                </div>

                <div class="card" style="margin-bottom: 20px;">
                    <h3 style="margin-bottom: 15px; color: #2196F3;">🔄 Batch Processing</h3>
                    <p style="margin-bottom: 15px; color: #aaa;">Automatically process all pending submissions using AI verification when enabled.</p>
                    
                    <button class="btn" style="background: #2196F3; color: white; padding: 10px 20px; border-radius: 6px; border: none; cursor: pointer; font-weight: 600;" onclick="processBatchAI()">
                        Process All Pending Submissions
                    </button>
                    
                    <div id="batch-progress" style="margin-top: 15px; display: none;">
                        <div style="font-size: 14px; margin-bottom: 10px;">
                            ⏳ Processing submissions...
                        </div>
                        <div style="background: rgba(255, 255, 255, 0.1); height: 8px; border-radius: 4px; overflow: hidden;">
                            <div id="batch-progress-bar" style="background: #2196F3; height: 100%; width: 0%; transition: width 0.3s ease;"></div>
                        </div>
                    </div>
                    
                    <div id="batch-result" style="margin-top: 15px; padding: 15px; border-radius: 8px; display: none; background: rgba(76, 175, 80, 0.1); border-left: 4px solid #4CAF50;">
                        <div id="batch-result-content"></div>
                    </div>
                </div>

                <div class="card">
                    <h3 style="margin-bottom: 15px;">📊 Stats</h3>
                    <p><strong>AI Auto-Verify:</strong> <span id="ai-stats">Loading...</span></p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function deleteSubmission(id) {
            if (confirm('Are you absolutely sure you want to permanently delete this submission?')) {
                const fd = new FormData();
                fd.append('id', id);
                fetch('delete_submission.php', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) location.reload();
                        else alert('Error deleting submission');
                    });
            }
        }
        
        function approveSubmission(id) {
            if (confirm('Are you sure you want to approve this submission?')) {
                fetch('approve.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'submission_id=' + id
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert('Submission approved!');
                        location.reload();
                    } else {
                        alert('Error: ' + (data.error || 'Failed to approve'));
                    }
                })
                .catch(err => {
                    console.error('Approval error:', err);
                    alert('Error: unable to approve submission');
                });
            }
        }

        function rejectSubmission(id) {
            const notes = prompt('Enter rejection notes (optional):');
            if (notes !== null) { // null if cancelled
                fetch('reject.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'submission_id=' + id + '&notes=' + encodeURIComponent(notes)
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert('Submission rejected!');
                        location.reload();
                    } else {
                        alert('Error: ' + (data.error || 'Failed to reject'));
                    }
                })
                .catch(err => {
                    console.error('Rejection error:', err);
                    alert('Error: unable to reject submission');
                });
            }
        }

        // Auto-refresh when new submissions arrive
        let currentPendingCount = <?php echo isset($pending_submissions) ? (int)$pending_submissions : 0; ?>;
        setInterval(() => {
            fetch('check_pending.php')
                .then(r => r.json())
                .then(data => {
                    if (data.count > currentPendingCount) {
                        location.reload();
                    } else if (data.count !== undefined) {
                        currentPendingCount = data.count; // Sync if it decreases
                    }
                })
                .catch(e => console.error('Polling error:', e));
        }, 10000);

        // ============================================
        // AI Proof Verification Toggle
        // ============================================
        
        // Load AI status on page load
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('settings')) {
                loadAIStatus();
                document.getElementById('ai-verify-toggle').addEventListener('change', function() {
                    document.getElementById('ai-toggle-btn').style.display = 'block';
                });
            }
        });

        function loadAIStatus() {
            fetch('toggle_ai_verify.php?action=get_ai_status')
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const enabled = data.ai_enabled;
                        document.getElementById('ai-verify-toggle').checked = enabled;
                        document.getElementById('ai-status-text').innerText = enabled ? '✓ Enabled' : '✗ Disabled';
                        document.getElementById('ai-stats').innerText = enabled ? 'Active - Auto-verifying submissions' : 'Disabled - Manual review only';
                        
                        if (enabled) {
                            document.getElementById('ai-stats').style.color = '#4CAF50';
                        } else {
                            document.getElementById('ai-stats').style.color = '#f44336';
                        }
                    }
                })
                .catch(e => {
                    console.error('Error loading AI status:', e);
                    document.getElementById('ai-status-text').innerText = 'Error loading...';
                });
        }

        function toggleAIVerify() {
            const isCurrentlyEnabled = document.getElementById('ai-verify-toggle').checked;
            const btn = document.getElementById('ai-toggle-btn');
            
            btn.disabled = true;
            btn.innerText = 'Updating...';
            
            fetch('toggle_ai_verify.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=toggle_ai_verify'
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const message = document.getElementById('ai-message');
                    message.style.color = '#4CAF50';
                    message.innerText = '✓ ' + data.message;
                    
                    // Hide button and reload status
                    btn.style.display = 'none';
                    btn.innerText = 'Update Preference';
                    btn.disabled = false;
                    
                    // Wait a moment then reload
                    setTimeout(() => {
                        loadAIStatus();
                    }, 500);
                    
                    // Log audit trail
                    console.log('[AI VERIFY] Toggled:', data.ai_enabled);
                } else {
                    const message = document.getElementById('ai-message');
                    message.style.color = '#f44336';
                    message.innerText = '✗ Error: ' + (data.error || 'Failed to toggle');
                    btn.disabled = false;
                    btn.innerText = 'Update Preference';
                }
            })
            .catch(e => {
                console.error('Toggle error:', e);
                const message = document.getElementById('ai-message');
                message.style.color = '#f44336';
                message.innerText = '✗ Network error: ' + e.message;
                btn.disabled = false;
                btn.innerText = 'Update Preference';
            });
        }

        // ============================================
        // Batch AI Processing
        // ============================================
        
        function processBatchAI() {
            const progressDiv = document.getElementById('batch-progress');
            const resultDiv = document.getElementById('batch-result');
            const btn = event.target;
            
            progressDiv.style.display = 'block';
            resultDiv.style.display = 'none';
            btn.disabled = true;
            btn.innerText = '⏳ Processing...';
            
            fetch('process_ai_batch.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=process_batch'
            })
            .then(r => r.json())
            .then(data => {
                progressDiv.style.display = 'none';
                
                if (data.success) {
                    const html = `
                        <div style="font-weight: 600; margin-bottom: 10px; color: #4CAF50;">✓ Batch Processing Complete</div>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; color: #ddd; font-size: 13px;">
                            <div>📊 Processed: <strong>${data.processed}</strong></div>
                            <div>✓ Approved: <strong style="color: #4CAF50;">${data.approved}</strong></div>
                            <div>✗ Rejected: <strong style="color: #f44336;">${data.rejected}</strong></div>
                            <div>👀 Manual Review: <strong style="color: #FFC107;">${data.manual_review}</strong></div>
                        </div>
                    `;
                    document.getElementById('batch-result-content').innerHTML = html;
                    resultDiv.style.display = 'block';
                } else {
                    const errorHtml = `
                        <div style="font-weight: 600; color: #f44336;">✗ Error: ${data.error || 'Failed to process'}</div>
                    `;
                    document.getElementById('batch-result-content').innerHTML = errorHtml;
                    resultDiv.style.display = 'block';
                    resultDiv.style.borderLeftColor = '#f44336';
                    resultDiv.style.background = 'rgba(244, 67, 54, 0.1)';
                }
                
                btn.disabled = false;
                btn.innerText = 'Process All Pending Submissions';
            })
            .catch(e => {
                console.error('Batch processing error:', e);
                progressDiv.style.display = 'none';
                
                const errorHtml = `
                    <div style="font-weight: 600; color: #f44336;">✗ Network Error: ${e.message}</div>
                `;
                document.getElementById('batch-result-content').innerHTML = errorHtml;
                resultDiv.style.display = 'block';
                resultDiv.style.borderLeftColor = '#f44336';
                resultDiv.style.background = 'rgba(244, 67, 54, 0.1)';
                
                btn.disabled = false;
                btn.innerText = 'Process All Pending Submissions';
            });
        }
    </script>

    <!-- User Actions Modal -->
    <div id="user-actions-modal" style="
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.7);
        z-index: 9999;
        justify-content: center;
        align-items: center;
    ">
        <div style="
            background: #1a1a2e;
            padding: 30px;
            border-radius: 10px;
            max-width: 400px;
            width: 90%;
            color: #fff;
            border: 1px solid #333;
        ">
            <h3 style="margin-top: 0; margin-bottom: 20px; font-size: 18px;">
                User: <span id="modal-username" style="color: #4CAF50;"></span>
            </h3>
            
            <div style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px;">
                <p style="margin: 0; color: #aaa; font-size: 13px;">Current Status: <strong id="modal-status"></strong></p>
            </div>
            
            <div id="modal-actions" style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px;">
                <!-- Actions will be inserted here -->
            </div>
            
            <button onclick="closeUserActionsModal()" style="
                width: 100%;
                padding: 10px;
                background: #333;
                color: #fff;
                border: 1px solid #444;
                border-radius: 6px;
                cursor: pointer;
                font-weight: 600;
            ">Cancel</button>
        </div>
    </div>

    <script>
        // User Management Functions
        function showUserActions(userId, username, currentStatus) {
            const modal = document.getElementById('user-actions-modal');
            const actionsDiv = document.getElementById('modal-actions');
            
            document.getElementById('modal-username').innerText = username;
            document.getElementById('modal-status').innerText = currentStatus.charAt(0).toUpperCase() + currentStatus.slice(1);
            
            // Build actions based on current status
            let html = '';
            
            if (currentStatus === 'active') {
                html += `
                    <button onclick="userAction(${userId}, 'suspend', '<?php echo $url_secret; ?>')" style="
                        padding: 12px;
                        background: #FFC107;
                        color: #000;
                        border: none;
                        border-radius: 6px;
                        cursor: pointer;
                        font-weight: 600;
                        transition: background 0.2s;
                    " onmouseover="this.style.background='#FFB300'" onmouseout="this.style.background='#FFC107'">
                        ⏸️ Suspend User
                    </button>
                `;
                html += `
                    <button onclick="userAction(${userId}, 'ban', '<?php echo $url_secret; ?>')" style="
                        padding: 12px;
                        background: #f44336;
                        color: #fff;
                        border: none;
                        border-radius: 6px;
                        cursor: pointer;
                        font-weight: 600;
                        transition: background 0.2s;
                    " onmouseover="this.style.background='#d32f2f'" onmouseout="this.style.background='#f44336'">
                        🚫 Ban User
                    </button>
                `;
            } else if (currentStatus === 'suspended' || currentStatus === 'inactive') {
                html += `
                    <button onclick="userAction(${userId}, 'activate', '<?php echo $url_secret; ?>')" style="
                        padding: 12px;
                        background: #4CAF50;
                        color: #fff;
                        border: none;
                        border-radius: 6px;
                        cursor: pointer;
                        font-weight: 600;
                        transition: background 0.2s;
                    " onmouseover="this.style.background='#45a049'" onmouseout="this.style.background='#4CAF50'">
                        ✓ Reactivate User
                    </button>
                `;
            }
            
            html += `
                <button onclick="userAction(${userId}, 'delete', '<?php echo $url_secret; ?>')" style="
                    padding: 12px;
                    background: #8B0000;
                    color: #fff;
                    border: none;
                    border-radius: 6px;
                    cursor: pointer;
                    font-weight: 600;
                    transition: background 0.2s;
                " onmouseover="this.style.background='#600000'" onmouseout="this.style.background='#8B0000'">
                    🗑️ Permanently Delete
                </button>
            `;
            
            actionsDiv.innerHTML = html;
            modal.style.display = 'flex';
        }
        
        function closeUserActionsModal() {
            document.getElementById('user-actions-modal').style.display = 'none';
        }
        
        function userAction(userId, action, token) {
            const actionMessage = {
                'suspend': 'Are you sure you want to suspend this user?',
                'ban': 'Are you sure you want to ban this user? They will no longer be able to log in.',
                'activate': 'Are you sure you want to reactivate this user?',
                'delete': 'WARNING: This will permanently delete this user and all their data (submissions, quests, etc). This cannot be undone. Continue?'
            };
            
            if (!confirm(actionMessage[action])) {
                return;
            }
            
            const formData = new FormData();
            formData.append('user_action', action);
            formData.append('user_id', userId);
            formData.append('csrf_token', '<?php echo $token; ?>');
            
            fetch('admin.php?section=users&token=' + encodeURIComponent(token), {
                method: 'POST',
                body: formData
            })
            .then(r => r.text())
            .then(data => {
                closeUserActionsModal();
                location.reload();
            })
            .catch(err => {
                console.error('Error:', err);
                alert('Error performing action: ' + err.message);
            });
        }
        
        // Close modal when clicking outside
        document.getElementById('user-actions-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeUserActionsModal();
            }
        });
    </script>
    
    <!-- Cookie Consent Banner -->
    <script src="assets/js/cookies.js"></script>
    
    <?php require_once(__DIR__ . '/chat_widget.php'); ?>
</body>
</html>