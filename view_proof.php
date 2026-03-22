<?php
/**
 * Proof Image Viewer
 * 
 * Displays proof images in a web page for admins
 */

require_once(__DIR__ . '/config.php');

if (!is_admin()) {
    http_response_code(403);
    die('Access denied');
}

$submission_id = $_GET['id'] ?? null;

if (!$submission_id) {
    http_response_code(400);
    die('Submission ID required');
}

// Get submission details
$stmt = $pdo->prepare("
    SELECT s.*, q.title as quest_title, u.username 
    FROM submissions s
    JOIN quests q ON s.quest_id = q.id
    JOIN users u ON s.user_id = u.id
    WHERE s.id = ?
");
$stmt->execute([$submission_id]);
$submission = $stmt->fetch();

if (!$submission || (empty($submission['file_path']) && empty($submission['text_proof']))) {
    http_response_code(404);
    die('Proof not found');
}

$has_file = !empty($submission['file_path']) && file_exists($submission['file_path']);
$file_url = null;
$is_video = false;
$width = 800;
$height = 600;

if ($has_file) {
    $file_path = $submission['file_path'];
    $is_video = strpos($submission['mime_type'] ?? '', 'video/') === 0;
    
    if (!$is_video) {
        $image_info = @getimagesize($file_path);
        $width = $image_info[0] ?? 800;
        $height = $image_info[1] ?? 600;
    }
    
    $file_url = 'uploads/proofs/' . rawurlencode($submission['file_name']);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proof Review - <?php echo htmlspecialchars($submission['username']); ?> - <?php echo htmlspecialchars($submission['quest_title']); ?></title>
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
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .header {
            background: rgba(0, 0, 0, 0.5);
            padding: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .header h1 {
            color: #4CAF50;
            font-size: 24px;
        }
        
        .header p {
            color: #aaa;
            margin-top: 5px;
        }
        
        .content {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .proof-container {
            max-width: 90%;
            max-height: 80vh;
            background: #1a1a2e;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 0 20px rgba(0,0,0,0.5);
        }
        
        .proof-image {
            max-width: 100%;
            max-height: 100%;
            border-radius: 8px;
            display: block;
            margin: 0 auto;
        }
        
        .actions {
            text-align: center;
            margin-top: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            margin: 0 10px;
            border: none;
            border-radius: 6px;
            color: #fff;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
            transition: all 0.3s ease;
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
        
        .btn-secondary {
            background: #666;
        }
        
        .btn-secondary:hover {
            background: #555;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Proof Review</h1>
        <p><strong>User:</strong> <?php echo htmlspecialchars($submission['username']); ?> | <strong>Quest:</strong> <?php echo htmlspecialchars($submission['quest_title']); ?> | <strong>Submitted:</strong> <?php echo date('M d, Y H:i', strtotime($submission['submitted_at'])); ?></p>
        
        <?php 
        // Check AI verification status
        $stmt = $pdo->prepare("SELECT setting_value FROM global_settings WHERE setting_key = 'ai_verify_proofs'");
        $stmt->execute();
        $ai_enabled = $stmt->fetch(PDO::FETCH_ASSOC)['setting_value'] === '1';
        ?>
        
        <div style="margin-top: 10px; font-size: 13px; color: #aaa;">
            <?php if ($ai_enabled): ?>
                <span style="background: rgba(76, 175, 80, 0.2); color: #4CAF50; padding: 4px 8px; border-radius: 4px;">
                    ✓ AI Verification: ENABLED
                </span>
            <?php else: ?>
                <span style="background: rgba(100, 100, 100, 0.2); color: #999; padding: 4px 8px; border-radius: 4px;">
                    ✗ AI Verification: DISABLED
                </span>
            <?php endif; ?>
            
            <?php if (!empty($submission['confidence_score'])): ?>
                | <strong>AI Confidence:</strong> 
                <?php 
                $confidence = $submission['confidence_score'] * 100;
                $confidence_color = $confidence >= 85 ? '#4CAF50' : ($confidence >= 50 ? '#FFC107' : '#f44336');
                ?>
                <span style="color: <?php echo $confidence_color; ?>;"><?php echo round($confidence, 1); ?>%</span>
                <?php if ($submission['keywords_found']): ?>
                    | <strong>Keywords:</strong> <span style="color: #4CAF50;"><?php echo htmlspecialchars($submission['keywords_found']); ?></span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="content">
        <div class="proof-container">
            <?php if ($has_file && $is_video): ?>
                <video src="<?php echo htmlspecialchars($file_url); ?>" controls autoplay loop style="max-width: 100%; max-height: 800px; border-radius: 8px;"></video>
            <?php elseif ($has_file && !$is_video): ?>
                <img src="<?php echo htmlspecialchars($file_url); ?>" alt="Proof submission" class="proof-image" style="max-width: <?php echo min($width, 800); ?>px; max-height: <?php echo min($height, 600); ?>px;">
            <?php endif; ?>
            
            <?php if (!empty($submission['text_proof'])): ?>
                <div style="margin-top: 20px; padding: 20px; background: rgba(0, 0, 0, 0.4); border-radius: 8px; border-left: 4px solid #f0f;">
                    <h3 style="color: #f0f; margin-top: 0; margin-bottom: 10px; font-size: 14px; text-transform: uppercase;">TEXT PROOF SUBMISSION:</h3>
                    <div style="font-size: 16px; line-height: 1.6; color: #fff; white-space: pre-wrap;"><?php echo htmlspecialchars($submission['text_proof']); ?></div>
                </div>
            <?php endif; ?>
            
            <div class="actions">
                <?php if ($ai_enabled && $submission['verification_status'] === 'pending'): ?>
                    <button class="btn" style="background: #2196F3; color: white;" onclick="aiVerifySubmission()" id="ai-verify-btn">
                        🤖 AI Verify
                    </button>
                <?php endif; ?>
                <button class="btn btn-success" onclick="approveSubmission()">Approve</button>
                <button class="btn btn-danger" onclick="rejectSubmission()">Reject</button>
                <button class="btn btn-secondary" onclick="window.close()">Close</button>
            </div>
        </div>
    </div>

    <script>
        function aiVerifySubmission() {
            const btn = document.getElementById('ai-verify-btn');
            btn.disabled = true;
            btn.textContent = '⏳ Verifying...';
            
            fetch('ai_verify_proof.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=auto_verify&submission_id=<?php echo $submission_id; ?>'
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const msg = data.verified_status === 'approved' 
                        ? `✓ AI Auto-Approved!\nConfidence: ${data.confidence.toFixed(1)}%\n\n${data.reasoning}`
                        : `✗ AI Auto-Rejected\nConfidence: ${data.confidence.toFixed(1)}%\n\n${data.reasoning}`;
                    
                    alert(msg);
                    window.opener.location.reload();
                    window.close();
                } else {
                    alert('AI Verification Error: ' + (data.error || 'Failed to verify'));
                    btn.disabled = false;
                    btn.textContent = '🤖 AI Verify';
                }
            })
            .catch(err => {
                console.error('AI verification error:', err);
                alert('Error: unable to run AI verification');
                btn.disabled = false;
                btn.textContent = '🤖 AI Verify';
            });
        }

        function approveSubmission() {
            if (confirm('Are you sure you want to approve this submission?')) {
                fetch('approve.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'submission_id=<?php echo $submission_id; ?>'
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert('Submission approved!');
                        window.opener.location.reload(); // Refresh parent window
                        window.close();
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

        function rejectSubmission() {
            const notes = prompt('Enter rejection notes (optional):');
            if (notes !== null) { // null if cancelled
                fetch('reject.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'submission_id=<?php echo $submission_id; ?>&notes=' + encodeURIComponent(notes)
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert('Submission rejected!');
                        window.opener.location.reload(); // Refresh parent window
                        window.close();
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
    </script>
</body>
</html>