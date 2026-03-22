<?php
/**
 * Toggle AI Proof Verification
 * API endpoint to toggle AI automatic verification feature
 */

require_once(__DIR__ . '/config.php');
header('Content-Type: application/json');

if (!is_admin()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'toggle_ai_verify') {
    try {
        // Get current value
        $stmt = $pdo->prepare("SELECT setting_value FROM global_settings WHERE setting_key = 'ai_verify_proofs'");
        $stmt->execute();
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $new_value = ($current && $current['setting_value'] === '1') ? '0' : '1';
        
        // Update or insert
        $stmt = $pdo->prepare("
            INSERT INTO global_settings (setting_key, setting_value) 
            VALUES ('ai_verify_proofs', ?) 
            ON DUPLICATE KEY UPDATE setting_value = ?
        ");
        $stmt->execute([$new_value, $new_value]);
        
        // Log the action
        log_audit('TOGGLE_AI_VERIFY', 'setting', 0, [
            'old_value' => $current['setting_value'] ?? '0',
            'new_value' => $new_value
        ]);
        
        echo json_encode([
            'success' => true,
            'ai_enabled' => $new_value === '1',
            'message' => 'AI verification ' . ($new_value === '1' ? 'enabled' : 'disabled')
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'get_ai_status') {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM global_settings WHERE setting_key = 'ai_verify_proofs'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $enabled = ($result && $result['setting_value'] === '1');
        
        echo json_encode([
            'success' => true,
            'ai_enabled' => $enabled
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
