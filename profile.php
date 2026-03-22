<?php
require_once(__DIR__ . '/config.php');

if (!is_logged_in()) {
    redirect('login.php');
}

$user = get_user();
$user_id = $user['id'];

// Check if viewing someone else
$target_id = isset($_GET['id']) ? (int)$_GET['id'] : $user_id;
$is_owner = ($target_id === $user_id);

if (!$is_owner) {
    $stmt = $pdo->prepare("SELECT id, username, display_name, avatar_url, level, xp, total_completed, last_seen FROM users WHERE id = ? AND status = 'active'");
    $stmt->execute([$target_id]);
    $target_user = $stmt->fetch();
    if (!$target_user) {
        die("User not found or suspended.");
    }
} else {
    $target_user = $user;
}

if ($is_owner && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['message'] = "Invalid CSRF token";
        $_SESSION['message_type'] = "error";
    } else {
        $display_name = trim($_POST['display_name'] ?? '');
        $avatar_url = $user['avatar_url'];
        
        // Handle avatar upload
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array(strtolower($ext), $allowed)) {
                if (!is_dir(__DIR__ . '/uploads/avatars')) {
                    mkdir(__DIR__ . '/uploads/avatars', 0755, true);
                }
                $filename = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
                $target = 'uploads/avatars/' . $filename;
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], __DIR__ . '/' . $target)) {
                    $avatar_url = $target;
                }
            } else {
                $_SESSION['message'] = "Invalid file format for avatar. Use JPG, PNG, GIF, WEBP.";
                $_SESSION['message_type'] = "error";
            }
        }
        
        $stmt = $pdo->prepare("UPDATE users SET display_name = ?, avatar_url = ? WHERE id = ?");
        $stmt->execute([$display_name, $avatar_url, $user_id]);
        
        $_SESSION['message'] = "Profile updated successfully!";
        $_SESSION['message_type'] = "success";
        
        redirect('profile.php');
    }
}
$token = csrf_token();

$is_online = false;
if ($target_user['last_seen'] && strtotime($target_user['last_seen']) > time() - 300) {
    $is_online = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - Side Quest</title>
    <link rel="icon" type="image/x-icon" href="./assets/images/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="./assets/images/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="./assets/images/favicon-16.png">
    <link rel="apple-touch-icon" href="./assets/images/icon-192.png">
    <style>
        body, html { margin: 0; padding: 0; background: #0f172a; color: #fff; font-family: sans-serif; }
        .container { max-width: 600px; margin: 50px auto; padding: 20px; background: #1e293b; border-radius: 8px; border: 1px solid #334155; }
        h2 { margin-top: 0; border-bottom: 1px solid #334155; padding-bottom: 15px; color: #3b82f6; display: flex; align-items: center; justify-content: space-between; }
        .form-group { margin-bottom: 20px; display: flex; flex-direction: column; }
        label { margin-bottom: 8px; font-weight: bold; color: #cbd5e1; }
        input[type="text"], input[type="file"] { padding: 10px; border-radius: 4px; border: 1px solid #475569; background: #0b0f19; color: #fff; }
        .btn { background: #3b82f6; color: #fff; border: none; padding: 12px 20px; border-radius: 6px; cursor: pointer; font-weight: bold; text-decoration: none; display: inline-block;}
        .avatar-preview { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 2px solid #3b82f6; margin-bottom: 10px; }
        .msg { padding: 15px; border-radius: 6px; margin-bottom: 20px; border: 1px solid; }
        .msg.success { background: rgba(16, 185, 129, 0.2); border-color: #10b981; color: #34d399; }
        .msg.error { background: rgba(239, 68, 68, 0.2); border-color: #ef4444; color: #f87171; }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" style="color: #93c5fd; text-decoration: none; margin-bottom: 20px; display: inline-block;">◄ Back to Dashboard</a>
        <h2>
            <?php echo $is_owner ? 'Edit Player Profile' : escape($target_user['display_name'] ?? $target_user['username']) . '’s Profile'; ?>
            <?php if (!$is_owner): ?>
                <span style="font-size: 14px; padding: 4px 8px; border-radius: 12px; background: <?php echo $is_online ? 'rgba(74, 222, 128, 0.2)' : 'rgba(100, 116, 139, 0.2)'; ?>; color: <?php echo $is_online ? '#4ade80' : '#94a3b8'; ?>;">
                    <?php echo $is_online ? '🟢 Online' : '⚪ Offline'; ?>
                </span>
            <?php endif; ?>
        </h2>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="msg <?php echo $_SESSION['message_type']; ?>">
                <?php echo escape($_SESSION['message']); ?>
            </div>
            <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
        <?php endif; ?>

        <?php if ($is_owner): ?>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo escape($token); ?>">
            
            <div class="form-group">
                <label>Current Avatar</label>
                <?php if ($user['avatar_url']): ?>
                    <img src="<?php echo escape($user['avatar_url']); ?>" class="avatar-preview" alt="Avatar">
                <?php else: ?>
                    <div style="width:100px;height:100px;border-radius:50%;background:#334155;display:flex;align-items:center;justify-content:center;margin-bottom:10px;border:2px solid #3b82f6;">
                        <span style="font-size:30px;">👤</span>
                    </div>
                <?php endif; ?>
                <input type="file" name="avatar" accept="image/*">
            </div>
            
            <div class="form-group">
                <label>Display Name (optional)</label>
                <input type="text" name="display_name" value="<?php echo escape($user['display_name'] ?? ''); ?>" placeholder="Enter display name...">
            </div>
            
            <button type="submit" class="btn">Update Profile</button>
        </form>
        <?php else: ?>
        <!-- Other Player Profile -->
        <div style="display: flex; gap: 20px; align-items: center; margin-bottom: 30px;">
            <?php if ($target_user['avatar_url']): ?>
                <img src="<?php echo escape($target_user['avatar_url']); ?>" class="avatar-preview" alt="Avatar" style="margin: 0;">
            <?php else: ?>
                <div style="width:100px;height:100px;border-radius:50%;background:#334155;display:flex;align-items:center;justify-content:center;border:2px solid #3b82f6;">
                    <span style="font-size:30px;">👤</span>
                </div>
            <?php endif; ?>
            <div>
                <div style="font-size: 24px; font-weight: bold; color: #fff; margin-bottom: 5px;"><?php echo escape($target_user['username']); ?></div>
                <div style="color: #94a3b8; font-size: 14px;">Level <?php echo $target_user['level']; ?> • <?php echo $target_user['xp']; ?> XP</div>
                <div style="color: #64B5F6; font-size: 13px; margin-top: 5px;">Completed <?php echo $target_user['total_completed']; ?> Quests</div>
            </div>
        </div>

        <!-- Chess Statistics Section (Professional Features) -->
        <div style="background: rgba(76, 175, 80, 0.1); border: 1px solid #4CAF50; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
            <h3 style="margin-top: 0; color: #4CAF50; display: flex; align-items: center; gap: 10px;">
                ♔ Chess Statistics
                <a href="chess/professional-index.php" style="font-size: 12px; color: #64B5F6; text-decoration: none; background: rgba(100, 181, 246, 0.2); padding: 4px 8px; border-radius: 4px;">Play Chess</a>
            </h3>
            
            <script>
                // Load chess stats for this player
                fetch('/boringlife/chess/elo_system.php?action=get_stats&user_id=<?php echo $target_id; ?>')
                    .then(r => r.json())
                    .then(stats => {
                        if (stats.ratings) {
                            const ratings = stats.ratings;
                            const statsHtml = `
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 12px;">
                                    <div style="background: rgba(255,255,255,0.05); padding: 12px; border-radius: 6px; text-align: center;">
                                        <div style="font-size: 12px; color: #aaa; margin-bottom: 5px;">⚡ Bullet</div>
                                        <div style="font-size: 18px; font-weight: bold; color: #4CAF50;">${ratings.bullet_rating || 1200}</div>
                                    </div>
                                    <div style="background: rgba(255,255,255,0.05); padding: 12px; border-radius: 6px; text-align: center;">
                                        <div style="font-size: 12px; color: #aaa; margin-bottom: 5px;">🔥 Blitz</div>
                                        <div style="font-size: 18px; font-weight: bold; color: #4CAF50;">${ratings.blitz_rating || 1200}</div>
                                    </div>
                                    <div style="background: rgba(255,255,255,0.05); padding: 12px; border-radius: 6px; text-align: center;">
                                        <div style="font-size: 12px; color: #aaa; margin-bottom: 5px;">⚙️ Rapid</div>
                                        <div style="font-size: 18px; font-weight: bold; color: #4CAF50;">${ratings.rapid_rating || 1200}</div>
                                    </div>
                                    <div style="background: rgba(255,255,255,0.05); padding: 12px; border-radius: 6px; text-align: center;">
                                        <div style="font-size: 12px; color: #aaa; margin-bottom: 5px;">👑 Peak</div>
                                        <div style="font-size: 18px; font-weight: bold; color: #FFD700;">${ratings.peak || 1200}</div>
                                    </div>
                                </div>
                            `;
                            
                            if (stats.stats) {
                                const sumHtml = `
                                    <div style="margin-top: 15px; display: grid; grid-template-columns: repeat(auto-fit, minmax(100px, 1fr)); gap: 12px;">
                                        <div style="background: rgba(255,255,255,0.05); padding: 12px; border-radius: 6px; text-align: center;">
                                            <div style="font-size: 12px; color: #aaa;">Games</div>
                                            <div style="font-size: 18px; font-weight: bold;">${stats.stats.total_games}</div>
                                        </div>
                                        <div style="background: rgba(255,255,255,0.05); padding: 12px; border-radius: 6px; text-align: center;">
                                            <div style="font-size: 12px; color: #aaa;">Wins</div>
                                            <div style="font-size: 18px; font-weight: bold; color: #4CAF50;">${stats.stats.wins}</div>
                                        </div>
                                        <div style="background: rgba(255,255,255,0.05); padding: 12px; border-radius: 6px; text-align: center;">
                                            <div style="font-size: 12px; color: #aaa;">Win Rate</div>
                                            <div style="font-size: 18px; font-weight: bold;">${stats.stats.win_rate}%</div>
                                        </div>
                                    </div>
                                `;
                                document.getElementById('chess-stats').innerHTML = statsHtml + sumHtml;
                            } else {
                                document.getElementById('chess-stats').innerHTML = statsHtml;
                            }
                        }
                    })
                    .catch(e => console.error('Error loading chess stats:', e));
            </script>
            
            <div id="chess-stats" style="color: #94a3b8; font-size: 13px;">
                <p>Loading chess statistics...</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <script src="assets/js/notifications.js"></script>
    <script src="assets/js/live-chat.js"></script>
    
    <!-- Professional Chess Features -->
    <script src="chess/public/professional-chess-ui.js"></script>
    
    <!-- Cookie Consent Banner -->
    <script src="assets/js/cookies.js"></script>

    <!-- Progressive Web App Helper -->
    <script src="assets/js/pwa-helper.js"></script>
</body>
</html>
