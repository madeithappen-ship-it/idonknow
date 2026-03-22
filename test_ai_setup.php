<?php
require_once(__DIR__ . '/config.php');

echo "=== AI Verification System Status ===\n\n";

// Check submissions table structure
$stmt = $pdo->query('DESCRIBE submissions');
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
$column_names = array_map(function($col) { return $col['Field']; }, $columns);

// Check for AI-related columns
$required = ['keywords_found', 'confidence_score', 'verification_notes', 'verified_by'];
$missing = [];

foreach ($required as $col) {
    if (!in_array($col, $column_names)) {
        $missing[] = $col;
    }
}

echo "1. Database Schema:\n";
if (empty($missing)) {
    echo "   ✓ All required AI columns exist\n";
} else {
    echo "   ⚠ Missing columns: " . implode(', ', $missing) . "\n";
}

// Check AI verification setting
$stmt = $pdo->prepare('SELECT setting_value FROM global_settings WHERE setting_key = ?');
$stmt->execute(['ai_verify_proofs']);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo "\n2. AI Verification Setting:\n";
if ($result) {
    $status = $result['setting_value'] === '1' ? 'ENABLED' : 'DISABLED';
    echo "   ✓ Status: $status\n";
} else {
    echo "   ✗ Setting not found\n";
}

// List all files created
echo "\n3. New/Modified Files:\n";
$files = [
    'ai_verify_proof.php' => 'Single submission AI verification',
    'process_ai_batch.php' => 'Batch AI verification processor',
    'toggle_ai_verify.php' => 'Toggle AI feature (existing)',
    'admin.php' => 'Admin panel with AI controls (modified)',
    'view_proof.php' => 'Proof viewer with AI button (modified)'
];

foreach ($files as $file => $desc) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        $size = filesize($path);
        echo "   ✓ $file (" . number_format($size) . " bytes) - $desc\n";
    } else {
        echo "   ✗ $file - NOT FOUND\n";
    }
}

// Count pending submissions
echo "\n4. Pending Submissions:\n";
$stmt = $pdo->query('SELECT COUNT(*) as count FROM submissions WHERE verification_status = "pending"');
$pending = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
echo "   • Waiting for review: $pending\n";

// Get quest keywords stats
$stmt = $pdo->query('SELECT COUNT(*) as count FROM quests WHERE keywords IS NOT NULL AND keywords != ""');
$with_keywords = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
echo "   • Quests with keywords: $with_keywords\n";

echo "\n5. API Endpoints Available:\n";
echo "   • POST /ai_verify_proof.php?action=auto_verify&submission_id=X\n";
echo "   • POST /process_ai_batch.php?action=process_batch\n";
echo "   • GET/POST /toggle_ai_verify.php?action=toggle_ai_verify\n";
echo "   • GET /toggle_ai_verify.php?action=get_ai_status\n";

echo "\n6. Testing Instructions:\n";
echo "   • Go to Admin Panel: Settings tab\n";
echo "   • Toggle: 'AI Proof Verification' to enable\n";
echo "   • Click: 'Process All Pending Submissions' button\n";
echo "   • Or: Approve individual submissions with 'AI Verify' button\n\n";

echo "=== Setup Complete ===\n";
?>
