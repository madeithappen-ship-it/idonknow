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

if (!$submission || empty($submission['file_path'])) {
    http_response_code(404);
    die('Proof not found');
}

$file_path = $submission['file_path'];

if (!file_exists($file_path)) {
    http_response_code(404);
    die('File not found');
}

// Get image dimensions for display
$image_info = getimagesize($file_path);
$width = $image_info[0] ?? 800;
$height = $image_info[1] ?? 600;

// Create a data URL for the image
$image_data = base64_encode(file_get_contents($file_path));
$data_url = 'data:' . $submission['mime_type'] . ';base64,' . $image_data;

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
    </div>
    
    <div class="content">
        <div class="proof-container">
            <img src="<?php echo $data_url; ?>" alt="Proof submission" class="proof-image" style="max-width: <?php echo min($width, 800); ?>px; max-height: <?php echo min($height, 600); ?>px;">
            
            <div class="actions">
                <button class="btn btn-success" onclick="approveSubmission()">Approve</button>
                <button class="btn btn-danger" onclick="rejectSubmission()">Reject</button>
                <button class="btn btn-secondary" onclick="window.close()">Close</button>
            </div>
        </div>
    </div>

    <script>
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