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
        $stmt = $pdo->prepare("
            INSERT INTO quests (title, description, difficulty, type, xp_reward, keywords, is_active)
            VALUES (?, ?, ?, ?, ?, ?, 1)
        ");
        
        $stmt->execute([
            $_POST['title'],
            $_POST['description'],
            $_POST['difficulty'],
            $_POST['type'],
            (int)$_POST['xp_reward'],
            $_POST['keywords'] ?? ''
        ]);
        
        log_audit('ADD_QUEST', 'quest', $pdo->lastInsertId(), [
            'title' => $_POST['title'],
            'difficulty' => $_POST['difficulty']
        ]);
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
$stmt = $pdo->query("SELECT * FROM admin_notifications ORDER BY created_at DESC");
$admin_notes = $stmt->fetchAll();

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
                <a href="?token=<?php echo $url_secret; ?>&section=submissions" class="<?= $section === 'submissions' ? 'active' : '' ?>">Submissions</a>
                <a href="?token=<?php echo $url_secret; ?>&section=quests" class="<?= $section === 'quests' ? 'active' : '' ?>">Manage Quests</a>
                <a href="?token=<?php echo $url_secret; ?>&section=users" class="<?= $section === 'users' ? 'active' : '' ?>">Users</a>
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
                
                <form method="POST" action="manage_notifications.php" style="margin-bottom: 30px; background: #1a1a2e; padding: 20px; border-radius: 10px;">
                    <h3 style="margin-bottom: 15px;">Send New Notification</h3>
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="csrf_token" value="<?php echo escape($token); ?>">
                    <input type="hidden" name="return_url" value="admin.php?token=<?php echo urlencode($url_secret); ?>&section=notifications">
                    
                    <div style="margin-bottom: 15px;">
                        <textarea name="message" required rows="4" placeholder="Type your announcement here... It will appear at the top of the user's dashboard." style="width: 100%; padding: 12px; background: #262641; border: 1px solid #333; color: #fff; border-radius: 6px; font-family: inherit; resize: vertical;"></textarea>
                    </div>
                    
                    <button type="submit">Broadcast Notification</button>
                </form>
                
                <h3>Active Notifications</h3>
                <table class="table" style="margin-top: 15px;">
                    <thead>
                        <tr>
                            <th>Message</th>
                            <th>Posted On</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($admin_notes as $note): ?>
                        <tr>
                            <td style="max-width: 400px; line-height: 1.5;"><?php echo nl2br(escape($note['message'])); ?></td>
                            <td><?php echo date('M d, H:i', strtotime($note['created_at'])); ?></td>
                            <td>
                                <form method="POST" action="manage_notifications.php" onsubmit="return confirm('Delete this notification?');" style="display:inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $note['id']; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo escape($token); ?>">
                                    <input type="hidden" name="return_url" value="admin.php?token=<?php echo urlencode($url_secret); ?>&section=notifications">
                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($admin_notes)): ?>
                        <tr>
                            <td colspan="3" style="text-align: center; color: #aaa; padding: 30px;">No active notifications</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Quests Section -->
            <div id="quests" class="section <?= $section === 'quests' ? 'active' : '' ?>">
                <h2>Quest Management</h2>
                
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
                                <?php if (!empty($sub['file_path']) && file_exists($sub['file_path'])): ?>
                                    <a href="view_proof.php?id=<?php echo $sub['id']; ?>" target="_blank" class="btn btn-info btn-sm">View Proof</a>
                                <?php else: ?>
                                    No proof
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($sub['verification_status'] === 'pending'): ?>
                                    <button class="btn btn-success btn-sm" onclick="approveSubmission(<?php echo $sub['id']; ?>)">Approve</button>
                                    <button class="btn btn-danger btn-sm" onclick="rejectSubmission(<?php echo $sub['id']; ?>)">Reject</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Users Section -->
            <div id="users" class="section <?= $section === 'users' ? 'active' : '' ?>">
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
                            <td><?php echo escape($u['status']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
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
        }, 5000);
    </script>
</body>
</html>