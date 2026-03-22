<?php
/**
 * User Dashboard
 * Main interface for users to view and submit quests
 */

require_once(__DIR__ . '/config.php');

if (!is_logged_in()) {
    redirect('login.php');
}

$user = get_user();
$user_id = $user['id'];

// Fetch Daily Quest Settings
$stmt = $pdo->query("SELECT setting_value FROM global_settings WHERE setting_key = 'daily_quest'");
$dq_setting = $stmt->fetch();
$dq_raw = $dq_setting ? json_decode($dq_setting['setting_value'], true) : null;
$today = date('Y-m-d');

$daily_quest_data = null;
if ($dq_raw) {
    if (isset($dq_raw['id']) && !isset($dq_raw['global'])) {
        $daily_quest_data = ['global' => $dq_raw, 'users' => []];
    } else {
        $daily_quest_data = $dq_raw;
    }
}

$daily_quest_id = null;
if ($daily_quest_data) {
    if (isset($daily_quest_data['users'][$user_id]) && $daily_quest_data['users'][$user_id]['date'] === $today) {
        $daily_quest_id = $daily_quest_data['users'][$user_id]['id'];
    } elseif (isset($daily_quest_data['global']) && $daily_quest_data['global']['date'] === $today) {
        $daily_quest_id = $daily_quest_data['global']['id'];
    }
}

$active_daily_quest = null;
$available_daily_quest = null;

if ($daily_quest_id) {
    $stmt = $pdo->prepare("
        SELECT uq.id as user_quest_id, uq.status, uq.submission_id, uq.attempts, q.* FROM user_quests uq
        JOIN quests q ON uq.quest_id = q.id
        WHERE uq.user_id = ? AND uq.quest_id = ? AND DATE(uq.assigned_at) = ?
        ORDER BY uq.assigned_at DESC LIMIT 1
    ");
    $stmt->execute([$user_id, $daily_quest_id, $today]);
    $active_daily_quest = $stmt->fetch();
    
    if (!$active_daily_quest) {
        $stmt = $pdo->prepare("SELECT * FROM quests WHERE id = ?");
        $stmt->execute([$daily_quest_id]);
        $available_daily_quest = $stmt->fetch();
    }
}

// Get current regular quest
$query = "
    SELECT uq.id as user_quest_id, uq.status, uq.submission_id, uq.attempts, q.* FROM user_quests uq
    JOIN quests q ON uq.quest_id = q.id
    WHERE uq.user_id = ? AND uq.status IN ('assigned', 'in_progress', 'submitted')
";
$params = [$user_id];
if ($daily_quest_id) {
    $query .= " AND uq.quest_id != ?";
    $params[] = $daily_quest_id;
}
$query .= " ORDER BY uq.assigned_at DESC LIMIT 1";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$current_quest = $stmt->fetch();

// Get top users for leaderboard
$stmt = $pdo->query("
    SELECT username, level, xp, total_completed
    FROM users
    WHERE status = 'active'
    ORDER BY level DESC, xp DESC
    LIMIT 10
");
$leaderboard = $stmt->fetchAll();

// Get user's submission history
$stmt = $pdo->prepare("
    SELECT s.*, q.title, u.username
    FROM submissions s
    JOIN quests q ON s.quest_id = q.id
    JOIN users u ON s.user_id = u.id
    WHERE s.user_id = ?
    ORDER BY s.submitted_at DESC
    LIMIT 5
");
$stmt->execute([$user_id]);
$recent_submissions = $stmt->fetchAll();

// Fetch active admin notifications
$stmt = $pdo->prepare("SELECT * FROM admin_notifications WHERE target_user_id IS NULL OR target_user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$user_id]);
$global_notifications = $stmt->fetchAll();

// Fetch music
$stmt = $pdo->query("SELECT * FROM site_music ORDER BY id DESC");
$site_music = $stmt->fetchAll();

// Fetch music
$stmt = $pdo->query("SELECT * FROM site_music ORDER BY id DESC");
$site_music = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT MAX(id) as max_id, COUNT(id) as cnt FROM admin_notifications WHERE target_user_id IS NULL OR target_user_id = ?");
$stmt->execute([$user_id]);
$notif_stats = $stmt->fetch();
$notif_max_id = $notif_stats['max_id'] ?? 0;
$notif_count = $notif_stats['cnt'] ?? 0;

// Calculate Rare Badges (Completed Insane Quests)
$stmt = $pdo->prepare("
    SELECT COUNT(*) as insane_completed FROM user_quests uq 
    JOIN quests q ON uq.quest_id = q.id 
    WHERE uq.user_id = ? AND q.difficulty = 'insane' AND uq.status = 'approved'
");
$stmt->execute([$user_id]);
$insane_count = $stmt->fetchColumn() ?: 0;
$rare_badges = [];
if ($insane_count >= 1) $rare_badges[] = '☠️ Insanity Initiate';
if ($insane_count >= 5) $rare_badges[] = '👹 Abyss Walker';
if ($insane_count >= 10) $rare_badges[] = '🔥 True Chaos';
if ($insane_count >= 25) $rare_badges[] = '👑 Lord of the Edge';

$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? 'info';
if (isset($_SESSION['message'])) {
    unset($_SESSION['message'], $_SESSION['message_type']);
}

$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Side Quest</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html, body {
            width: 100%;
            height: 100%;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0f0f1e 0%, #1a1a2e 100%);
            color: #fff;
            overflow-x: hidden;
        }
        
        .navbar {
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(10px);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .navbar h1 {
            font-size: 20px;
            color: #4CAF50;
        }
        
        .user-menu {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .user-menu a,
        .user-menu button {
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            color: #fff;
            background: rgba(76, 175, 80, 0.2);
            border: 1px solid #4CAF50;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .user-menu .admin-link {
            background: rgba(255, 193, 7, 0.2);
            border-color: #FFC107;
            color: #FFF;
        }
        .user-menu .admin-link:hover {
            background: #FFC107;
            color: #000;
        }
        .user-menu .admin-dot {
            color: #FFC107;
            font-weight: bold;
            margin-right: 6px;
        }
        
        .user-menu a:hover,
        .user-menu button:hover {
            background: #4CAF50;
            color: #000;
        }
        
        .logout-btn {
            background: rgba(244, 67, 54, 0.2) !important;
            border-color: #f44336 !important;
        }
        
        .logout-btn:hover {
            background: #f44336 !important;
            color: #fff !important;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            animation: slideIn 0.3s ease;
        }
        
        .message-success {
            background: rgba(76, 175, 80, 0.2);
            border: 1px solid rgba(76, 175, 80, 0.5);
            color: #99ff99;
        }
        
        .message-error {
            background: rgba(244, 67, 54, 0.2);
            border: 1px solid rgba(244, 67, 54, 0.5);
            color: #ff9999;
        }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .card {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 20px;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            border-color: rgba(76, 175, 80, 0.5);
            box-shadow: 0 0 20px rgba(76, 175, 80, 0.2);
            transform: translateY(-5px);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-item {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-left: 4px solid #4CAF50;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: bold;
            color: #4CAF50;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 12px;
            color: #aaa;
            text-transform: uppercase;
        }
        
        .quest-card {
            grid-column: 1 / -1;
        }
        
        .quest-title {
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .quest-meta {
            display: flex;
            gap: 15px;
            margin: 15px 0;
            flex-wrap: wrap;
        }
        
        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-easy {
            background: rgba(76, 175, 80, 0.2);
            border: 1px solid rgba(76, 175, 80, 0.5);
            color: #99ff99;
        }
        
        .badge-medium {
            background: rgba(255, 193, 7, 0.2);
            border: 1px solid rgba(255, 193, 7, 0.5);
            color: #ffeb99;
        }
        
        .badge-hard {
            background: rgba(255, 152, 0, 0.2);
            border: 1px solid rgba(255, 152, 0, 0.5);
            color: #ffb399;
        }
        
        .badge-insane {
            background: rgba(244, 67, 54, 0.2);
            border: 1px solid rgba(244, 67, 54, 0.5);
            color: #ff9999;
        }
        
        .quest-description {
            font-size: 16px;
            line-height: 1.6;
            margin: 15px 0;
            color: #ddd;
        }
        
        .xp-reward {
            font-size: 18px;
            color: #4CAF50;
            margin: 15px 0;
            font-weight: 600;
        }
        
        .button {
            padding: 12px 24px;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            border: none;
            border-radius: 6px;
            color: #fff;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-right: 10px;
            margin-bottom: 10px;
        }
        
        .button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.4);
        }
        
        .button.secondary {
            background: rgba(76, 175, 80, 0.2);
            border: 1px solid #4CAF50;
            color: #4CAF50;
        }
        
        .button.secondary:hover {
            background: #4CAF50;
            color: #000;
        }
        
        .upload-area {
            border: 2px dashed rgba(76, 175, 80, 0.5);
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 20px 0;
        }
        
        .upload-area:hover {
            border-color: #4CAF50;
            background: rgba(76, 175, 80, 0.1);
        }
        
        .upload-area input[type="file"] {
            display: none;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .table th {
            background: rgba(76, 175, 80, 0.2);
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .table td {
            padding: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .table tbody tr:hover {
            background: rgba(76, 175, 80, 0.05);
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-pending {
            background: rgba(255, 193, 7, 0.2);
            color: #ffeb99;
        }
        
        .status-approved {
            background: rgba(76, 175, 80, 0.2);
            color: #99ff99;
        }
        
        .status-rejected {
            background: rgba(244, 67, 54, 0.2);
            color: #ff9999;
        }
        
        .section {
            margin-bottom: 40px;
        }
        
        .section h2 {
            font-size: 20px;
            margin-bottom: 20px;
            color: #4CAF50;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .grid {
                grid-template-columns: 1fr;
            }
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(76, 175, 80, 0.3);
            border-radius: 50%;
            border-top-color: #4CAF50;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>⚡ Side Quest</h1>
        <div class="user-menu">
            <div class="user-info" style="display:flex; align-items:center; gap: 10px;">
                <a href="profile.php" style="display:flex; align-items:center; gap: 10px; color:#fff; text-decoration:none;">
                    <?php if ($user['avatar_url']): ?>
                        <img src="<?php echo escape($user['avatar_url']); ?>" style="width:36px; height:36px; border-radius:50%; object-fit:cover; border: 2px solid #4CAF50;">
                    <?php else: ?>
                        <div style="width:36px; height:36px; border-radius:50%; background:#334155; display:flex; align-items:center; justify-content:center; border: 2px solid #4CAF50;">
                            <span style="font-size:16px;">👤</span>
                        </div>
                    <?php endif; ?>
                    <span>Level <?php echo $user['level']; ?> - <?php echo escape($user['display_name'] ?: $user['username']); ?></span>
                </a>
            </div>
            <?php if (is_admin()): ?>
                <a href="admin.php?token=<?php echo urlencode(config('admin_url_secret')); ?>" class="admin-link">Admin Panel</a>
            <?php endif; ?>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="message message-<?php echo $message_type; ?>">
                <?php echo ($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-value"><?php echo $user['level']; ?></div>
                <div class="stat-label">Current Level</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?php echo $user['xp']; ?></div>
                <div class="stat-label">Total XP</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?php echo $user['total_completed']; ?></div>
                <div class="stat-label">Quests Completed</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?php echo $user['current_streak'] ?? 0; ?></div>
                <div class="stat-label">Current Streak</div>
            </div>
        </div>
        
        <!-- Feel The Vibe -->
        <?php if (!empty($site_music)): ?>
        <div class="section" style="margin-bottom: 25px; padding: 20px; background: rgba(0,0,0,0.4); border-radius: 10px; border: 1px solid rgba(255,255,255,0.05); box-shadow: 0 4px 15px rgba(0,0,0,0.5);">
            <h2 style="color: #64B5F6; margin-bottom: 15px; font-size: 16px; display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 20px;">🎧</span> Feel The Vibe
            </h2>
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <?php foreach ($site_music as $idx => $trk): ?>
                    <div onclick="playRadio('<?php echo escape($trk['video_id']); ?>', '<?php echo escape(addslashes($trk['title'])); ?>')" style="padding: 12px 15px; background: rgba(33, 150, 243, 0.1); border: 1px solid rgba(33, 150, 243, 0.3); border-radius: 8px; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 10px;" onmouseover="this.style.background='rgba(33, 150, 243, 0.2)'" onmouseout="this.style.background='rgba(33, 150, 243, 0.1)'">
                        <span style="font-size: 24px;">🎵</span>
                        <div>
                            <div style="color: #fff; font-weight: bold; font-size: 14px;"><?php echo escape($trk['title']); ?></div>
                            <div style="color: #888; font-size: 11px; margin-top: 4px;">Click to play in pop-out player ↗</div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Global Notifications -->
        <?php foreach ($global_notifications as $note): ?>
            <?php $is_private = !empty($note['target_user_id']); ?>
            <div class="message message-info" style="background: <?php echo $is_private ? 'rgba(156, 39, 176, 0.15)' : 'rgba(33, 150, 243, 0.15)'; ?>; border: 1px solid <?php echo $is_private ? 'rgba(156, 39, 176, 0.4)' : 'rgba(33, 150, 243, 0.4)'; ?>; color: #fff; display: flex; align-items: flex-start; gap: 15px; margin-bottom: 20px; padding: 15px 20px;">
                <span style="font-size: 24px; margin-top: 2px;">📢</span>
                <div>
                    <strong style="color: <?php echo $is_private ? '#E1bee7' : '#64B5F6'; ?>; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px;">
                        <?php echo $is_private ? 'Private Message from Admin' : 'Admin Announcement'; ?>
                    </strong>
                    <?php if (!empty(trim($note['message']))): ?>
                    <div style="margin-top: 8px; font-size: 16px; line-height: 1.5;">
                        <?php echo format_text($note['message']); ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($note['image_path'])): ?>
                        <div style="margin-top: 15px;">
                            <?php $is_video_notif = preg_match('/\.(mp4|webm)$/i', $note['image_path']); ?>
                            <?php if ($is_video_notif): ?>
                                <video src="<?php echo escape($note['image_path']); ?>" style="max-width: 100%; max-height: 300px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1); box-shadow: 0 4px 10px rgba(0,0,0,0.5);" autoplay loop muted playsinline></video>
                            <?php else: ?>
                                <img src="<?php echo escape($note['image_path']); ?>" style="max-width: 100%; max-height: 300px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1); box-shadow: 0 4px 10px rgba(0,0,0,0.5);" alt="Notification Attachment">
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div style="font-size: 12px; color: #aaa; margin-top: 8px;">
                        Sent <?php echo date('M d, g:i A', strtotime($note['created_at'])); ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        
        <!-- Friends & Social Section -->
        <div class="section" style="margin-bottom: 25px;">
            <h2 style="color: #64B5F6; margin-bottom: 15px; font-size: 18px; display: flex; align-items: center; gap: 8px;">
                👥 Friends & Social
            </h2>
            
            <div style="background: rgba(0,0,0,0.3); padding: 15px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05); display: flex; gap: 20px; flex-wrap: wrap;">
                
                <!-- Search Users -->
                <div style="flex: 1; min-width: 250px;">
                    <h3 style="color: #cbd5e1; font-size: 14px; margin-bottom: 10px;">Find Players</h3>
                    <div style="margin-bottom: 10px;">
                        <button id="show-all-users-btn" class="btn btn-primary" style="width: 100%; padding: 10px; display: flex; align-items: center; justify-content: center; gap: 8px;">
                            <span style="font-size: 16px;">➕</span> Browse All Players
                        </button>
                    </div>
                    <div id="friend-search-results" style="max-height: 200px; overflow-y: auto; background: #1e293b; border-radius: 4px;"></div>
                </div>

                <!-- Pending Requests -->
                <div style="flex: 1; min-width: 250px; border-left: 1px solid rgba(255,255,255,0.1); padding-left: 20px;" id="pending-friends-col">
                    <h3 style="color: #cbd5e1; font-size: 14px; margin-bottom: 10px;">Pending Requests</h3>
                    <div id="pending-friends-list" style="max-height: 200px; overflow-y: auto;">
                        <div style="color: #888; font-style: italic; font-size: 12px;">No pending requests.</div>
                    </div>
                </div>

                <!-- My Friends -->
                <div style="flex: 1; min-width: 250px; border-left: 1px solid rgba(255,255,255,0.1); padding-left: 20px;">
                    <h3 style="color: #cbd5e1; font-size: 14px; margin-bottom: 10px;">My Friends</h3>
                    <div id="my-friends-list" style="max-height: 200px; overflow-y: auto;">
                        <div style="color: #888; font-style: italic; font-size: 12px;">Loading friends...</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Daily Quest Section -->
        <?php if ($available_daily_quest || $active_daily_quest): ?>
        <div class="section">
            <h2 style="color: #FFC107;">🌟 Today's Daily Quest</h2>
            <?php if ($available_daily_quest): ?>
                <div class="card" style="border-color: rgba(255, 193, 7, 0.5); box-shadow: 0 0 20px rgba(255, 193, 7, 0.1);">
                    <h3 class="quest-title" style="color: #FFC107;"><?php echo escape($available_daily_quest['title']); ?></h3>
                    <div class="quest-meta">
                        <span class="badge badge-<?php echo $available_daily_quest['difficulty']; ?>"><?php echo ucfirst($available_daily_quest['difficulty']); ?></span>
                        <span class="badge"><?php echo ucfirst($available_daily_quest['type']); ?></span>
                        <span class="xp-reward">+<?php echo $available_daily_quest['xp_reward']; ?> XP</span>
                    </div>
                    <div class="quest-description"><?php echo format_text($available_daily_quest['description']); ?></div>
                    <button class="button" style="background: #FFC107; color: #000;" onclick="acceptDailyQuest(<?php echo $available_daily_quest['id']; ?>)">Accept Daily Quest</button>
                </div>
            <?php else: ?>
                <?php $quest = $active_daily_quest; ?>
                <div class="card" style="border-color: rgba(255, 193, 7, 0.5); box-shadow: 0 0 20px rgba(255, 193, 7, 0.1);">
                    <h3 class="quest-title" style="color: #FFC107;"><?php echo escape($quest['title']); ?></h3>
                    <div class="quest-meta">
                        <span class="badge badge-<?php echo $quest['difficulty']; ?>"><?php echo ucfirst($quest['difficulty']); ?></span>
                        <span class="badge"><?php echo ucfirst($quest['type']); ?></span>
                        <span class="xp-reward">+<?php echo $quest['xp_reward']; ?> XP</span>
                    </div>
                    <div class="quest-description"><?php echo format_text($quest['description']); ?></div>
                    
                    <?php
                        $dq_reject_reason = '';
                        $dq_verif_status = '';
                        if (!empty($quest['submission_id'])) {
                            $stmt_sub = $pdo->prepare("SELECT verification_status, verification_notes FROM submissions WHERE id = ?");
                            $stmt_sub->execute([$quest['submission_id']]);
                            if ($sb = $stmt_sub->fetch()) {
                                $dq_verif_status = $sb['verification_status'];
                                $dq_reject_reason = $sb['verification_notes'];
                            }
                        }
                    ?>
                    
                    <?php if ($dq_verif_status === 'rejected' && ($quest['status'] === 'in_progress' || $quest['status'] === 'expired')): ?>
                        <div style="padding: 15px; background: rgba(244, 67, 54, 0.2); border-left: 4px solid #f44336; border-radius: 4px; margin-bottom: 20px;">
                            <h4 style="color: #ff9999; margin-bottom: 5px;">❌ Proof Rejected</h4>
                            <p style="color: #fff; font-size: 14px;"><strong>Admin note:</strong> <?php echo escape($dq_reject_reason ?: 'No specific reason provided.'); ?></p>
                            <?php if ($quest['status'] === 'expired'): ?>
                                <p style="margin-top: 10px; color: #ff9999; font-weight: bold;">Out of attempts!</p>
                            <?php else: ?>
                                <p style="margin-top: 10px; color: #aaa; font-size: 12px;">You can try again and upload a new proof.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($quest['status'] === 'assigned' || $quest['status'] === 'in_progress'): ?>
                        <textarea id="textProof_daily" placeholder="Write out your completion proof here... (Optional if uploading file)" rows="3" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #333; background: #262641; color: #fff; margin-bottom: 15px; font-family: inherit; font-size: 14px; max-width: 100%; box-sizing: border-box;"></textarea>

                        <div class="upload-area" onclick="document.getElementById('proofInput_daily').click();">
                            <div>📸 Click or drag to upload daily proof</div>
                            <small style="color: #aaa;">Max 50MB, images or videos (Optional if writing text)</small>
                        </div>
                        <input type="file" id="proofInput_daily" name="proof" accept="image/*,video/*" onchange="previewProof(event, 'daily')" style="display:none;">
                        <div id="uploadPreview_daily" style="margin: 20px 0;"></div>
                        <button class="button" id="submitBtn_daily" style="background: #FFC107; color: #000;" onclick="submitProof(<?php echo $quest['user_quest_id']; ?>, 'daily')">Submit Daily Proof</button>
                    <?php elseif ($quest['status'] === 'submitted'): ?>
                        <div style="padding: 15px; background: rgba(255, 193, 7, 0.2); border-radius: 6px;">⏳ <strong>Pending Verification</strong></div>
                    <?php elseif ($quest['status'] === 'approved'): ?>
                        <div style="padding: 15px; background: rgba(76, 175, 80, 0.2); border-radius: 6px; color: #99ff99;">✅ <strong>Completed!</strong></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Chess Minigame -->
        <div class="section" style="margin-bottom: 30px;">
            <div style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); padding: 25px; border-radius: 12px; border: 2px solid rgba(59, 130, 246, 0.5); display: flex; align-items: center; justify-content: space-between; box-shadow: 0 10px 25px rgba(0,0,0,0.6); flex-wrap: wrap; gap: 15px;">
                <div style="flex: 1; min-width: 250px;">
                    <h2 style="color: #60a5fa; margin-bottom: 8px; font-size: 22px;">♟️ Grandmaster Arena</h2>
                    <p style="color: #bfdbfe; font-size: 15px;">Play Real-Time Multiplayer Chess or challenge the Computer!</p>
                </div>
                <a href="chess/index.php" class="button" style="background: #3b82f6; color: #fff; text-decoration: none; padding: 12px 25px; font-weight: bold; border-radius: 30px; border: none; box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4); white-space: nowrap;">Play Chess</a>
            </div>
        </div>
        
        <!-- Solitaire Minigame -->
        <?php if ($user['level'] >= 5 || is_admin()): ?>
        <div class="section" style="margin-bottom: 30px;">
            <div style="background: linear-gradient(135deg, #064e3b 0%, #0f172a 100%); padding: 25px; border-radius: 12px; border: 2px solid rgba(16, 185, 129, 0.5); display: flex; align-items: center; justify-content: space-between; box-shadow: 0 10px 25px rgba(0,0,0,0.6); flex-wrap: wrap; gap: 15px;">
                <div style="flex: 1; min-width: 250px;">
                    <h2 style="color: #10b981; margin-bottom: 8px; font-size: 22px;">🃏 Solitaire Quests</h2>
                    <p style="color: #a7f3d0; font-size: 15px;">Play classic Klondike Solitaire. Flip target cards to trigger real-life quests and unlock massive boosts!</p>
                </div>
                <a href="solitaire.php" class="button" style="background: #10b981; color: #fff; text-decoration: none; padding: 12px 25px; font-weight: bold; border-radius: 30px; border: none; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4); white-space: nowrap;">Play Now</a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Cyber Hunt Minigame -->
        <?php if ($user['level'] >= 10 || is_admin()): ?>
        <div class="section" style="margin-bottom: 30px;">
            <div style="background: linear-gradient(135deg, #050510 0%, #0ff 100%); padding: 25px; border-radius: 12px; border: 2px solid #0ff; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 10px 25px rgba(0,255,255,0.4); flex-wrap: wrap; gap: 15px;">
                <div style="flex: 1; min-width: 250px;">
                    <h2 style="color: #000; margin-bottom: 8px; font-size: 22px; text-shadow: 0 0 10px #fff;">🌐 Cyber Hunt (New!)</h2>
                    <p style="color: #000; font-size: 15px; font-weight: bold;">Explore a massive virtual 2D cyberspace. Uncover hidden server anomolies using proximity sonar and extract rare physical XP payloads!</p>
                </div>
                <a href="hunt.php" class="button" style="background: #000; color: #0ff; text-decoration: none; padding: 12px 25px; font-weight: bold; border-radius: 30px; border: 2px solid #0ff; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.8); white-space: nowrap;">Jack In</a>
            </div>
        <?php endif; ?>
        
        <!-- Chaos Wheel Minigame -->
        <?php if ($user['level'] >= 20 || is_admin()): ?>
        <div class="section" style="margin-bottom: 30px;">
            <div style="background: linear-gradient(135deg, #4c1d95 0%, #000 100%); padding: 25px; border-radius: 12px; border: 2px solid #8b5cf6; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 10px 25px rgba(139,92,246,0.4); flex-wrap: wrap; gap: 15px;">
                <div style="flex: 1; min-width: 250px;">
                    <h2 style="color: #c4b5fd; margin-bottom: 8px; font-size: 22px; text-shadow: 0 0 10px #7c3aed;">🎡 Chaos Wheel</h2>
                    <p style="color: #ddd; font-size: 15px; font-weight: bold;">Spin the Wheel of Destiny once a day. Win massive XP, lose progress, or trigger insane quests. Do you feel lucky?</p>
                </div>
                <a href="wheel.php" class="button" style="background: #eab308; color: #000; text-decoration: none; padding: 12px 25px; font-weight: bold; border-radius: 30px; border: 2px solid #fde047; box-shadow: 0 4px 15px rgba(234, 179, 8, 0.6); white-space: nowrap;">Spin Now</a>
            </div>
        </div>
        
        <!-- Dice Game Minigame -->
        <div class="section" style="margin-bottom: 30px;">
            <div style="background: linear-gradient(135deg, #7f1d1d 0%, #000 100%); padding: 25px; border-radius: 12px; border: 2px solid #ef4444; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 10px 25px rgba(239,68,68,0.4); flex-wrap: wrap; gap: 15px;">
                <div style="flex: 1; min-width: 250px;">
                    <h2 style="color: #fca5a5; margin-bottom: 8px; font-size: 22px; text-shadow: 0 0 10px #ef4444;">🎲 Dice Roll (New!)</h2>
                    <p style="color: #ddd; font-size: 15px; font-weight: bold;">Test your fate. Roll 1-6 to randomly assign quest difficulty. Roll a 6 for immediate Insane rewards!</p>
                </div>
                <a href="dice.php" class="button" style="background: #ef4444; color: #fff; text-decoration: none; padding: 12px 25px; font-weight: bold; border-radius: 30px; border: 2px solid #fca5a5; box-shadow: 0 4px 15px rgba(239, 68, 68, 0.6); white-space: nowrap;">Roll Dice</a>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- INSANE MODE UNLOCK -->
        <?php if ($user['level'] >= 10): ?>
        <div class="section" style="margin-bottom: 30px; animation: pulseRed 2s infinite;">
            <style>
                @keyframes pulseRed { 0% {box-shadow: 0 0 15px rgba(239, 68, 68, 0.2);} 50% {box-shadow: 0 0 35px rgba(239, 68, 68, 0.6);} 100% {box-shadow: 0 0 15px rgba(239, 68, 68, 0.2);} }
            </style>
            <div style="background: #450a0a; padding: 25px; border-radius: 12px; border: 2px solid #ef4444; text-align: center;">
                <h2 style="color: #fca5a5; font-size: 24px; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 2px;">🔥 Insane Mode Unlocked 🔥</h2>
                <p style="color: #f87171; font-size: 16px; margin-bottom: 20px;">You've reached Level 10. The abyss stares back. Are you ready for extreme challenges and exclusive Rare Badges?</p>
                <button onclick="summonInsaneQuest()" class="button" style="background: #dc2626; color: #fff; font-weight: bold; font-size: 18px; padding: 15px 40px; border: 1px solid #fecaca; text-transform: uppercase;">Summon Insane Quest</button>
            </div>
        </div>
        <script>
            function summonInsaneQuest() {
                if(!confirm("WARNING: Insane quests are extreme. Are you absolutely sure you want to summon one?")) return;
                fetch('get_quest.php?force_insane=true').then(r => r.json()).then(data => {
                    if (data.success) location.reload(); else alert(data.error || 'Failed to summon quest');
                });
            }
        </script>
        <?php endif; ?>

        <!-- Current Quest Section -->
        <div class="section">
            <h2>🎯 Your Current Quest</h2>
            
            <?php if ($current_quest): ?>
                <div class="card quest-card">
                    <h3 class="quest-title">
                        <?php echo escape($current_quest['title']); ?>
                        <button onclick="skipQuest(<?php echo $current_quest['user_quest_id']; ?>)" title="Skip this quest and get a new one" style="background:none; border:none; cursor:pointer; font-size: 20px; float: right; padding: 5px; transition: transform 0.3s;" onmouseover="this.style.transform='rotate(180deg)'" onmouseout="this.style.transform='rotate(0deg)'">🔄</button>
                    </h3>
                    
                    <div class="quest-meta">
                        <span class="badge badge-<?php echo $current_quest['difficulty']; ?>">
                            <?php echo ucfirst($current_quest['difficulty']); ?>
                        </span>
                        <span class="badge"><?php echo ucfirst($current_quest['type']); ?></span>
                        <span class="xp-reward">+<?php echo $current_quest['xp_reward']; ?> XP</span>
                    </div>
                    
                    <div class="quest-description">
                        <?php echo format_text($current_quest['description']); ?>
                    </div>
                    
                    <?php
                        $rq_reject_reason = '';
                        $rq_verif_status = '';
                        if (!empty($current_quest['submission_id'])) {
                            $stmt_sub = $pdo->prepare("SELECT verification_status, verification_notes FROM submissions WHERE id = ?");
                            $stmt_sub->execute([$current_quest['submission_id']]);
                            if ($sb = $stmt_sub->fetch()) {
                                $rq_verif_status = $sb['verification_status'];
                                $rq_reject_reason = $sb['verification_notes'];
                            }
                        }
                    ?>
                    
                    <?php if ($rq_verif_status === 'rejected' && ($current_quest['status'] === 'in_progress' || $current_quest['status'] === 'expired')): ?>
                        <div style="padding: 15px; background: rgba(244, 67, 54, 0.2); border-left: 4px solid #f44336; border-radius: 4px; margin-bottom: 20px;">
                            <h4 style="color: #ff9999; margin-bottom: 5px;">❌ Proof Rejected</h4>
                            <p style="color: #fff; font-size: 14px;"><strong>Admin note:</strong> <?php echo escape($rq_reject_reason ?: 'No specific reason provided.'); ?></p>
                            <?php if ($current_quest['status'] === 'expired'): ?>
                                <p style="margin-top: 10px; color: #ff9999; font-weight: bold;">Out of attempts for this quest!</p>
                            <?php else: ?>
                                <p style="margin-top: 10px; color: #aaa; font-size: 12px;">You can try again and upload a new proof below.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($current_quest['status'] === 'assigned' || $current_quest['status'] === 'in_progress'): ?>
                        <div><strong>Status:</strong> In Progress - Submit your proof below</div>
                        
                        <textarea id="textProof_regular" placeholder="Write out your completion proof here... (Optional if uploading file)" rows="3" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #333; background: #262641; color: #fff; margin-top: 15px; margin-bottom: 15px; font-family: inherit; font-size: 14px; max-width: 100%; box-sizing: border-box;"></textarea>

                        <div class="upload-area" onclick="document.getElementById('proofInput_regular').click();">
                            <div>📸 Click or drag to upload proof</div>
                            <small style="color: #aaa;">Max 50MB, images or videos (Optional if writing text)</small>
                        </div>
                        <input type="file" id="proofInput_regular" name="proof" accept="image/*,video/*" onchange="previewProof(event, 'regular')" style="display:none;">
                        
                        <div id="uploadPreview_regular" style="margin: 20px 0;"></div>
                        <button class="button" id="submitBtn_regular" onclick="submitProof(<?php echo $current_quest['user_quest_id']; ?>, 'regular')">Submit Proof</button>
                        
                    <?php elseif ($current_quest['status'] === 'submitted'): ?>
                        <div style="padding: 15px; background: rgba(255, 193, 7, 0.2); border-radius: 6px; margin-top: 15px;">
                            ⏳ <strong>Pending Verification</strong> - Your proof is being reviewed by moderators
                        </div>
                        <script>
                            setInterval(() => {
                                fetch('check_status.php')
                                    .then(r => r.json())
                                    .then(data => {
                                        if (data.status && data.status !== 'submitted') {
                                            location.reload();
                                        }
                                    })
                                    .catch(e => console.error(e));
                            }, 5000);
                        </script>
                        
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="card" id="autoAssignCard">
                    <p style="margin-bottom: 15px; text-align: center; font-size: 18px;">
                        <span class="loading" style="vertical-align: middle; margin-right: 10px;"></span> 
                        Assigning your next quest...
                    </p>
                </div>
                <script>
                    window.addEventListener('DOMContentLoaded', () => {
                        fetch('get_quest.php')
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                location.reload();
                            } else {
                                document.getElementById('autoAssignCard').innerHTML = '<p style="text-align:center; color:#ff9999;">' + (data.error || 'Failed to assign quest') + '</p><div style="text-align:center; margin-top:15px;"><button class="button" onclick="location.reload()">Try Again</button></div>';
                            }
                        })
                        .catch(err => {
                            document.getElementById('autoAssignCard').innerHTML = '<p style="text-align:center; color:#ff9999;">Network error while assigning quest.</p><div style="text-align:center; margin-top:15px;"><button class="button" onclick="location.reload()">Try Again</button></div>';
                        });
                    });
                </script>
            <?php endif; ?>
        </div>
        
        <!-- Leaderboard Section -->
        <div class="grid">
            <div class="card">
                <h3 style="margin-bottom: 20px;">🏆 Top Questers</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Player</th>
                            <th>Level</th>
                            <th>XP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_values($leaderboard) as $index => $player): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo escape($player['username']); ?></td>
                            <td><?php echo $player['level']; ?></td>
                            <td><?php echo $player['xp']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Recent History -->
        <div class="section">
            <h2>📋 Recent Submissions</h2>
            <div class="card">
                <?php if ($recent_submissions): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Quest</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_submissions as $sub): ?>
                            <tr>
                                <td><?php echo escape($sub['title']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $sub['verification_status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $sub['verification_status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, H:i', strtotime($sub['submitted_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="color: #aaa; text-align: center; padding: 20px;">No submissions yet. Complete your first quest!</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        function acceptDailyQuest(id) {
            const fd = new FormData();
            fd.append('quest_id', id);
            fd.append('csrf_token', '<?php echo escape($token); ?>');
            fetch('assign_daily_quest.php', {method:'POST', body:fd})
                .then(r=>r.json()).then(d=>{if(d.success)location.reload();else alert('Failed to accept');});
        }

        let selectedFiles = {};
        
        function previewProof(e, type) {
            selectedFiles[type] = e.target.files[0];
            if (selectedFiles[type]) {
                const preview = document.getElementById('uploadPreview_' + type);
                const url = URL.createObjectURL(selectedFiles[type]);
                if (selectedFiles[type].type.startsWith('video/')) {
                    preview.innerHTML = `<video src="${url}" style="max-width: 100%; max-height: 300px; border-radius: 6px;" controls autoplay muted loop></video>`;
                } else {
                    preview.innerHTML = `<img src="${url}" style="max-width: 100%; max-height: 300px; border-radius: 6px;">`;
                }
            }
        }
        
        function submitProof(questId, type) {
            const textProofField = document.getElementById('textProof_' + type);
            const textContent = textProofField ? textProofField.value.trim() : '';
            
            if (!selectedFiles[type] && !textContent) {
                alert('Please upload a file or write a text submission first!');
                return;
            }
            
            const formData = new FormData();
            if (selectedFiles[type]) {
                formData.append('proof', selectedFiles[type]);
            }
            if (textContent) {
                formData.append('text_proof', textContent);
            }
            formData.append('user_quest_id', questId);
            
            const btn = document.getElementById('submitBtn_' + type);
            const ogText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="loading"></span> Uploading...';
            
            fetch('submit_proof.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('✓ Proof submitted! Your submission is being reviewed.');
                    location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Submission failed'));
                    btn.disabled = false;
                    btn.innerHTML = ogText;
                }
            })
            .catch(err => {
                console.error('Submission error:', err);
                alert('Error: unable to submit proof right now. Please try again.');
                btn.disabled = false;
                btn.innerHTML = ogText;
            });
        }
        
        function skipQuest(questId) {
            if (confirm('Are you sure you want to skip this quest? You will get a new one immediately.')) {
                const formData = new FormData();
                formData.append('user_quest_id', questId);
                formData.append('csrf_token', '<?php echo escape($token); ?>');
                
                fetch('skip_quest.php', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + (data.error || 'Failed to skip quest'));
                    }
                })
                .catch(err => alert('Error skipping quest'));
            }
        }
        
        // Handle physical drag and drop targeting
        function setupDragDrop(type) {
            const area = document.querySelector('.upload-area[onclick*="' + type + '"]');
            if (!area) return;
            
            area.addEventListener('dragover', (e) => {
                e.preventDefault();
                area.style.background = 'rgba(76, 175, 80, 0.2)';
                area.style.borderColor = '#4CAF50';
            });
            area.addEventListener('dragleave', (e) => {
                e.preventDefault();
                area.style.background = 'transparent';
                area.style.borderColor = 'rgba(76, 175, 80, 0.5)';
            });
            area.addEventListener('drop', (e) => {
                e.preventDefault();
                area.style.background = 'transparent';
                area.style.borderColor = 'rgba(76, 175, 80, 0.5)';
                
                const files = e.dataTransfer.files;
                if (files.length) {
                    const fakeEvent = { target: { files: files } };
                    previewProof(fakeEvent, type);
                }
            });
        }
        
        setupDragDrop('regular');
        setupDragDrop('daily');
        
        // Auto-refresh notifications
        let currentNotifMaxId = <?php echo (int)$notif_max_id; ?>;
        let currentNotifCount = <?php echo (int)$notif_count; ?>;
        
        setInterval(() => {
            fetch('check_notifications.php')
                .then(r => r.json())
                .then(data => {
                    if (data.max_id > currentNotifMaxId || data.count !== currentNotifCount) {
                        location.reload();
                    }
                })
                .catch(e => console.error(e));
        }, 5000);
    </script>
    
    <script src="assets/js/friends.js"></script>
    <script src="assets/js/notifications.js"></script>
    
    <?php require_once(__DIR__ . '/chat_widget.php'); ?>
</body>
</html>
