-- ========================================
-- MyLifeIsBoringAndIWantToDoASideQuestButDontKnowWhatToDo
-- Complete Database Schema
-- ========================================

CREATE DATABASE IF NOT EXISTS sidequest_app;
USE sidequest_app;

-- Drop existing tables in correct dependency order
DROP TABLE IF EXISTS submissions;
DROP TABLE IF EXISTS audit_log;
DROP TABLE IF EXISTS user_quests;
DROP TABLE IF EXISTS sessions;
DROP TABLE IF EXISTS quests;
DROP TABLE IF EXISTS admin_users;
DROP TABLE IF EXISTS users;

-- ========================================
-- Users Table
-- ========================================
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
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
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_level (level),
    INDEX idx_xp (xp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Admin Users (created before submissions)
-- ========================================
CREATE TABLE admin_users (
    id INT PRIMARY KEY AUTO_INCREMENT,
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
    INDEX idx_username (username),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Quests Table (Main Quest Database)
-- ========================================
CREATE TABLE quests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    difficulty ENUM('easy', 'medium', 'hard', 'insane') DEFAULT 'medium',
    type ENUM('truth', 'dare', 'social', 'dark_humor', 'challenge', 'physical') DEFAULT 'dare',
    xp_reward INT DEFAULT 10,
    difficulty_multiplier DECIMAL(2, 1) DEFAULT 1.0,
    safety_level ENUM('safe', 'slightly_uncomfortable', 'risky') DEFAULT 'safe',
    requires_proof BOOLEAN DEFAULT TRUE,
    keywords VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    INDEX idx_difficulty (difficulty),
    INDEX idx_type (type),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- User Quests Progress Tracking
-- ========================================
CREATE TABLE user_quests (
    id INT PRIMARY KEY AUTO_INCREMENT,
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
    INDEX idx_user_id (user_id),
    INDEX idx_user_status (user_id, status),
    INDEX idx_quest_id (quest_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Submissions (Proof Upload)
-- ========================================
CREATE TABLE submissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
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
    INDEX idx_user_id (user_id),
    INDEX idx_verification_status (verification_status),
    INDEX idx_submitted_at (submitted_at),
    INDEX idx_pending (verification_status, submitted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
    expires_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Audit Log
-- ========================================
CREATE TABLE audit_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT,
    action VARCHAR(255) NOT NULL,
    target_type VARCHAR(100),
    target_id INT,
    details JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE SET NULL,
    INDEX idx_admin_id (admin_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Sample Super Admin (password: admin123 - CHANGE IN PRODUCTION)
-- ========================================
INSERT INTO admin_users (username, email, password, role, display_name, permissions) VALUES
('admin', 'admin@sidequest.local', '$2y$12$wxjkmH6h/d3YEwEPJVQ6sujtdENW98Qi3Fhz8si19TTmI0ItHYLQa', 'super_admin', 'Super Admin', '{"add_quests": true, "delete_quests": true, "edit_quests": true, "verify_proofs": true, "manage_users": true, "manage_admins": true, "view_logs": true}');