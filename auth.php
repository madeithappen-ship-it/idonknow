<?php
/**
 * Authentication Handler
 * 
 * Handles user login, registration, logout, and session management
 */

require_once(__DIR__ . '/config.php');

class AuthHandler {
    protected $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Register a new user
     */
    public function register($username, $email, $password, $display_name = null) {
        // Validate input
        if (!$this->validate_username($username)) {
            return ['success' => false, 'error' => 'Invalid username format'];
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Invalid email format'];
        }
        
        if (strlen($password) < 8) {
            return ['success' => false, 'error' => 'Password must be at least 8 characters'];
        }
        
        // Check if username or email already exists
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        
        if ($stmt->rowCount() > 0) {
            return ['success' => false, 'error' => 'Username or email already exists'];
        }
        
        // Hash password
        $password_hash = hash_password($password);
        if (empty($display_name)) {
            // Generate anonymous display name for new registrations
            $display_name = 'Anonymous_' . substr(bin2hex(random_bytes(4)), 0, 8);
        }
        
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO users (username, email, password, display_name, status)
                VALUES (?, ?, ?, ?, 'active')
            ");
            
            $stmt->execute([$username, $email, $password_hash, $display_name]);
            
            return ['success' => true, 'user_id' => $this->pdo->lastInsertId()];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Registration failed'];
        }
    }
    
    /**
     * Login user
     */
    public function login($username, $password) {
        // Find user by username or email
        $stmt = $this->pdo->prepare("
            SELECT id, username, email, password, status FROM users 
            WHERE (username = ? OR email = ?) AND status = 'active'
        ");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if (!$user || !verify_password($password, $user['password'])) {
            return ['success' => false, 'error' => 'Invalid username or password'];
        }

        // Create session for user
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];

        // Determine if this user is also an admin
        $adminStmt = $this->pdo->prepare("SELECT id, username, role, password FROM admin_users WHERE (username = ? OR email = ?) AND is_active = 1");
        $adminStmt->execute([$user['username'], $user['email']]);
        $admin = $adminStmt->fetch();

        if ($admin && verify_password($password, $admin['password'])) {
            // Set admin session state
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_role'] = $admin['role'];

            // Save admin session
            $this->create_session($admin['id'], 'admin');

            // Update last login for admin
            $updateStmt = $this->pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
            $updateStmt->execute([$admin['id']]);

            return ['success' => true, 'user_id' => $user['id'], 'is_admin' => true];
        }

        // Create normal user session
        $this->create_session($user['id'], 'user');

        return ['success' => true, 'user_id' => $user['id'], 'is_admin' => false];
    }
    
    /**
     * Admin login
     */
    public function admin_login($username, $password) {
        $stmt = $this->pdo->prepare("
            SELECT id, username, role, password, is_active 
            FROM admin_users 
            WHERE (username = ? OR email = ?) AND is_active = 1
        ");
        $stmt->execute([$username, $username]);
        $admin = $stmt->fetch();

        if (!$admin || !verify_password($password, $admin['password'])) {
            return ['success' => false, 'error' => 'Invalid admin credentials'];
        }
        
        // Create session
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_role'] = $admin['role'];
        
        // Update last login
        $stmt = $this->pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$admin['id']]);
        
        // Create session record
        $this->create_session($admin['id'], 'admin');
        
        return ['success' => true, 'admin_id' => $admin['id']];
    }
    
    /**
     * Logout user
     */
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            // Clear session from database
            $this->clear_session($_SESSION['user_id'], 'user');
        }
        
        session_destroy();
        return true;
    }
    
    /**
     * Admin logout
     */
    public function admin_logout() {
        if (isset($_SESSION['admin_id'])) {
            $this->clear_session($_SESSION['admin_id'], 'admin');
        }
        
        session_destroy();
        return true;
    }
    
    /**
     * Create session record
     */
    private function create_session($user_id, $type) {
        $session_id = session_id();
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $expires_at = date('Y-m-d H:i:s', time() + 3600 * 24);

        // Admin sessions are tracked by user_type, user_id may be nullable
        $db_user_id = $type === 'admin' ? null : $user_id;

        $stmt = $this->pdo->prepare("
            INSERT INTO sessions (session_id, user_id, user_type, ip_address, user_agent, expires_at)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE last_activity = NOW(), expires_at = ?
        ");

        $stmt->execute([$session_id, $db_user_id, $type, $ip_address, $user_agent, $expires_at, $expires_at]);
    }

    /**
     * Clear session
     */
    private function clear_session($user_id, $type = 'user') {
        if ($type === 'admin') {
            $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE user_type = 'admin' AND session_id = ?");
            $stmt->execute([session_id()]);
        } else {
            $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE user_type = 'user' AND user_id = ? AND session_id = ?");
            $stmt->execute([$user_id, session_id()]);
        }
    }
    
    /**
     * Validate username format
     */
    private function validate_username($username) {
        return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username);
    }
}

// Create instance
$auth = new AuthHandler($pdo);
