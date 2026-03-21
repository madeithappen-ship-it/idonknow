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

// Get current quest
$stmt = $pdo->prepare("
    SELECT uq.id as user_quest_id, uq.status, q.* FROM user_quests uq
    JOIN quests q ON uq.quest_id = q.id
    WHERE uq.user_id = ? AND uq.status IN ('assigned', 'in_progress', 'submitted')
    ORDER BY uq.assigned_at DESC
    LIMIT 1
");
$stmt->execute([$user_id]);
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
$stmt = $pdo->query("SELECT * FROM admin_notifications ORDER BY created_at DESC LIMIT 5");
$global_notifications = $stmt->fetchAll();

$stmt = $pdo->query("SELECT MAX(id) as max_id, COUNT(id) as cnt FROM admin_notifications");
$notif_stats = $stmt->fetch();
$notif_max_id = $notif_stats['max_id'] ?? 0;
$notif_count = $notif_stats['cnt'] ?? 0;

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
            <span>
                <?php if (is_admin()): ?>
                    <span class="admin-dot">•</span>
                <?php endif; ?>
                Level <?php echo $user['level']; ?> - <?php echo escape($user['username']); ?>
            </span>
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
        
        <!-- Global Notifications -->
        <?php foreach ($global_notifications as $note): ?>
            <div class="message message-info" style="background: rgba(33, 150, 243, 0.15); border: 1px solid rgba(33, 150, 243, 0.4); color: #fff; display: flex; align-items: flex-start; gap: 15px; margin-bottom: 20px; padding: 15px 20px;">
                <span style="font-size: 24px; margin-top: 2px;">📢</span>
                <div>
                    <strong style="color: #64B5F6; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px;">Admin Announcement</strong>
                    <div style="margin-top: 8px; font-size: 16px; line-height: 1.5;">
                        <?php echo nl2br(escape($note['message'])); ?>
                    </div>
                    <div style="font-size: 12px; color: #aaa; margin-top: 8px;">
                        Sent <?php echo date('M d, g:i A', strtotime($note['created_at'])); ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        
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
                        <?php echo nl2br(escape($current_quest['description'])); ?>
                    </div>
                    
                    <?php if ($current_quest['status'] === 'assigned' || $current_quest['status'] === 'in_progress'): ?>
                        <div><strong>Status:</strong> In Progress - Submit your proof below</div>
                        
                        <div class="upload-area" onclick="document.getElementById('proofInput').click();">
                            <input type="file" id="proofInput" name="proof" accept="image/*,video/*">
                            <div>📸 Click or drag to upload proof</div>
                            <small style="color: #aaa;">Max 50MB, images or videos (JPG, PNG, GIF, WebP, MP4, WebM)</small>
                        </div>
                        
                        <div id="uploadPreview" style="margin: 20px 0;"></div>
                        <button class="button" id="submitBtn" onclick="submitProof(<?php echo $current_quest['user_quest_id']; ?>)">Submit Proof</button>
                        
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
        let selectedFile = null;
        
        document.getElementById('proofInput')?.addEventListener('change', function(e) {
            selectedFile = e.target.files[0];
            
            if (selectedFile) {
                const preview = document.getElementById('uploadPreview');
                const objectUrl = URL.createObjectURL(selectedFile);
                if (selectedFile.type.startsWith('video/')) {
                    preview.innerHTML = `<video src="${objectUrl}" style="max-width: 100%; max-height: 300px; border-radius: 6px;" controls autoplay muted loop></video>`;
                } else {
                    preview.innerHTML = `<img src="${objectUrl}" style="max-width: 100%; max-height: 300px; border-radius: 6px;">`;
                }
            }
        });
        
        function submitProof(questId) {
            if (!selectedFile) {
                alert('Please select an image first');
                return;
            }
            
            const formData = new FormData();
            formData.append('proof', selectedFile);
            formData.append('user_quest_id', questId);
            
            const btn = document.getElementById('submitBtn');
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
                    btn.innerHTML = 'Submit Proof';
                }
            })
            .catch(err => {
                console.error('Submission error:', err);
                alert('Error: unable to submit proof right now. Please try again.');
                btn.disabled = false;
                btn.innerHTML = 'Submit Proof';
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
</body>
</html>
