-- ========================================
-- MyLifeIsBoringAndIWantToDoASideQuestButDontKnowWhatToDo
-- Complete Database Schema (MySQL)
-- ========================================

-- Drop existing tables in correct dependency order
DROP TABLE IF EXISTS admin_notifications;
DROP TABLE IF EXISTS audit_log;
DROP TABLE IF EXISTS submissions;
DROP TABLE IF EXISTS sessions;
DROP TABLE IF EXISTS user_quests;
DROP TABLE IF EXISTS admin_users;
DROP TABLE IF EXISTS quests;
DROP TABLE IF EXISTS users;

-- ========================================
-- Users Table
-- ========================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    display_name VARCHAR(255),
    avatar_url VARCHAR(500),
    level INT DEFAULT 1,
    xp INT DEFAULT 0,
    total_completed INT DEFAULT 0,
    current_streak INT DEFAULT 0,
    last_quest_date TIMESTAMP NULL,
    status ENUM('active', 'suspended', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_username (username),
    KEY idx_email (email),
    KEY idx_level (level),
    KEY idx_xp (xp)
);

-- ========================================
-- Admin Users
-- ========================================
CREATE TABLE admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'admin', 'moderator') DEFAULT 'admin',
    display_name VARCHAR(255),
    permissions JSON,
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_admin_username (username),
    KEY idx_admin_role (role)
);

-- ========================================
-- Quests Table (Main Quest Database)
-- ========================================
CREATE TABLE quests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    difficulty ENUM('easy', 'medium', 'hard', 'insane') DEFAULT 'medium',
    type ENUM('truth', 'dare', 'social', 'dark_humor', 'challenge', 'physical') DEFAULT 'dare',
    xp_reward INT DEFAULT 10,
    difficulty_multiplier DECIMAL(3, 1) DEFAULT 1.0,
    safety_level ENUM('safe', 'slightly_uncomfortable', 'risky') DEFAULT 'safe',
    requires_proof BOOLEAN DEFAULT TRUE,
    keywords VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    KEY idx_difficulty (difficulty),
    KEY idx_type (type),
    KEY idx_active (is_active)
);

-- ========================================
-- User Quests Progress Tracking
-- ========================================
CREATE TABLE user_quests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    quest_id INT NOT NULL,
    status ENUM('assigned', 'in_progress', 'submitted', 'approved', 'rejected', 'expired') DEFAULT 'assigned',
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    submission_id INT NULL,
    attempts INT DEFAULT 0,
    last_attempt TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (quest_id) REFERENCES quests(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_quest (user_id, quest_id),
    KEY idx_user_quest_user (user_id),
    KEY idx_user_quest_status (user_id, status),
    KEY idx_user_quest_quest (quest_id),
    KEY idx_user_quest_status_only (status)
);

-- ========================================
-- Submissions (Proof Upload)
-- ========================================
CREATE TABLE submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    user_quest_id INT NOT NULL,
    quest_id INT NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    mime_type VARCHAR(100),
    verification_status ENUM('pending', 'approved', 'rejected', 'expired') DEFAULT 'pending',
    verified_by INT NULL,
    verification_notes TEXT,
    keywords_found VARCHAR(500),
    confidence_score DECIMAL(3, 2),
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    verified_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (user_quest_id) REFERENCES user_quests(id) ON DELETE CASCADE,
    FOREIGN KEY (quest_id) REFERENCES quests(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES admin_users(id) ON DELETE SET NULL,
    KEY idx_sub_user (user_id),
    KEY idx_sub_status (verification_status),
    KEY idx_sub_submitted (submitted_at),
    KEY idx_sub_pending (verification_status, submitted_at)
);

-- ========================================
-- Session Management
-- ========================================
CREATE TABLE sessions (
    session_id VARCHAR(255) PRIMARY KEY,
    user_id INT,
    user_type ENUM('user', 'admin') DEFAULT 'user',
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    KEY idx_session_user (user_id),
    KEY idx_session_expires (expires_at)
);

-- ========================================
-- Audit Log
-- ========================================
CREATE TABLE audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT,
    action VARCHAR(255) NOT NULL,
    target_type VARCHAR(100),
    target_id INT,
    details JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE SET NULL,
    KEY idx_audit_admin (admin_id),
    KEY idx_audit_action (action),
    KEY idx_audit_created (created_at)
);

-- ========================================
-- Sample Super Admin (password: admin123 - CHANGE IN PRODUCTION)
-- ========================================
INSERT INTO admin_users (username, email, password, role, display_name, permissions) VALUES
('admin', 'admin@sidequest.local', '$2y$12$wxjkmH6h/d3YEwEPJVQ6sujtdENW98Qi3Fhz8si19TTmI0ItHYLQa', 'super_admin', 'Super Admin', '{"add_quests": true, "delete_quests": true, "edit_quests": true, "verify_proofs": true, "manage_users": true, "manage_admins": true, "view_logs": true}');