<?php
/**
 * MyLifeIsBoringAndIWantToDoASideQuestButDontKnowWhatToDo
 * Configuration and Database Connection
 * 
 * This file handles database connection, environment variables,
 * and global configuration settings.
 */

// ========================================
// Load .env file
// ========================================
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            // Remove quotes if present
            if ((strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) ||
                (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1)) {
                $value = substr($value, 1, -1);
            }
            putenv("$key=$value");
        }
    }
}

// ========================================
// Error Handling & Development Mode
// ========================================
error_reporting(E_ALL);
ini_set('display_errors', getenv('DEVELOPMENT_MODE') === 'true' ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/logs/error.log');

// Create logs directory if it doesn't exist
if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

// ========================================
// Environment Configuration
// ========================================
$env = getenv('APP_ENV') ?: 'production';
$isDevelopment = $env === 'development';

// Database Configuration - Support both .env and direct env vars
$db_config = [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'port' => getenv('DB_PORT') ?: '3306',
    'user' => getenv('DB_USER') ?: 'root',
    'pass' => getenv('DB_PASS') ?: '',
    'name' => getenv('DB_NAME') ?: 'sidequest_app',
];

// Application Configuration
$app_config = [
    'app_name' => 'MyLifeIsBoringAndIWantToDoASideQuestButDontKnowWhatToDo',
    'app_url' => getenv('APP_URL') ?: 'http://localhost',
    'upload_dir' => __DIR__ . '/uploads/',
    'max_upload_size' => 50 * 1024 * 1024, // 50MB
    'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm', 'mov'],
    'allowed_mimes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'video/mp4', 'video/webm', 'video/quicktime'],
    'session_timeout' => 3600 * 24, // 24 hours
    'admin_url_secret' => getenv('ADMIN_URL_SECRET') ?: 'x9_admin_portal_hidden',
];

// Create upload directories
if (!is_dir($app_config['upload_dir'])) {
    mkdir($app_config['upload_dir'], 0755, true);
}
if (!is_dir($app_config['upload_dir'] . 'proofs/')) {
    mkdir($app_config['upload_dir'] . 'proofs/', 0755, true);
}

// ========================================
// Database Connection (PDO)
// ========================================
try {
    $dsn = "mysql:host=" . $db_config['host'] . 
           ";port=" . $db_config['port'] . 
           ";dbname=" . $db_config['name'] . 
           ";charset=utf8mb4";
    
    // SSL options for Aiven or other secure connections
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    // Add SSL options if needed (for services like Aiven)
    if (!empty($_ENV['DB_SSL']) || strpos($db_config['host'], 'aiven') !== false) {
        $options[PDO::MYSQL_ATTR_SSL_CA] = '/etc/ssl/certs/ca-certificates.crt';
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    }
    
    $pdo = new PDO($dsn, $db_config['user'], $db_config['pass'], $options);
    
    // Test connection
    $pdo->query("SELECT 1");
    
} catch (PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    die("Database connection failed. Please try again later.");
}

// ========================================
// Session Configuration
// ========================================
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => $app_config['session_timeout'],
        'path' => '/',
        'domain' => '',
        'secure' => !$isDevelopment, // HTTPS only in production
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ========================================
// Security Headers
// ========================================
if (!headers_sent()) {
    // Prevent clickjacking
    header('X-Frame-Options: SAMEORIGIN');
    // Prevent MIME type sniffing
    header('X-Content-Type-Options: nosniff');
    // Enable XSS protection
    header('X-XSS-Protection: 1; mode=block');
    // Content Security Policy
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline';");
}

// ========================================
// Global Helper Functions
// ========================================

/**
 * Get a configuration value
 */
function config($key, $default = null) {
    global $app_config;
    $keys = explode('.', $key);
    $value = $app_config;
    
    foreach ($keys as $k) {
        if (is_array($value) && array_key_exists($k, $value)) {
            $value = $value[$k];
        } else {
            return $default;
        }
    }
    
    return $value;
}

/**
 * Check if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if user is admin
 */
function is_admin() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

/**
 * Redirect with message
 */
function redirect($url, $message = null, $type = 'info') {
    if ($message) {
        $_SESSION['message'] = $message;
        $_SESSION['message_type'] = $type;
    }
    header('Location: ' . $url);
    exit;
}

/**
 * Escape output for HTML
 */
function escape($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * Generate CSRF token
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verify_csrf($token) {
    if (empty($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    return true;
}

/**
 * Hash password
 */
function hash_password($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
}

/**
 * Verify password
 */
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Get current user data
 */
function get_user($id = null) {
    global $pdo;
    
    if ($id === null && !is_logged_in()) {
        return null;
    }
    
    $id = $id ?? $_SESSION['user_id'];
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND status = 'active'");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

/**
 * Log audit action
 */
function log_audit($action, $target_type = null, $target_id = null, $details = []) {
    global $pdo;
    
    if (!is_admin()) {
        return false;
    }
    
    $details_json = json_encode($details);
    $stmt = $pdo->prepare("
        INSERT INTO audit_log (admin_id, action, target_type, target_id, details)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    return $stmt->execute([
        $_SESSION['admin_id'],
        $action,
        $target_type,
        $target_id,
        $details_json
    ]);
}

// Display session messages
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'] ?? 'info';
    unset($_SESSION['message'], $_SESSION['message_type']);
}