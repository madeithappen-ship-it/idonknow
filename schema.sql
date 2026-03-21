-- ========================================
-- MyLifeIsBoringAndIWantToDoASideQuestButDontKnowWhatToDo
-- Complete Database Schema (PostgreSQL)
-- ========================================

-- Create enums
CREATE TYPE user_status AS ENUM ('active', 'suspended', 'inactive');
CREATE TYPE quest_difficulty AS ENUM ('easy', 'medium', 'hard', 'insane');
CREATE TYPE quest_type AS ENUM ('truth', 'dare', 'social', 'dark_humor', 'challenge', 'physical');
CREATE TYPE safety_level AS ENUM ('safe', 'slightly_uncomfortable', 'risky');
CREATE TYPE user_quest_status AS ENUM ('assigned', 'in_progress', 'submitted', 'approved', 'rejected', 'expired');
CREATE TYPE submission_status AS ENUM ('pending', 'approved', 'rejected', 'expired');
CREATE TYPE admin_role AS ENUM ('super_admin', 'admin', 'moderator');
CREATE TYPE user_type AS ENUM ('user', 'admin');

-- Drop existing tables in correct dependency order
DROP TABLE IF EXISTS audit_log CASCADE;
DROP TABLE IF EXISTS submissions CASCADE;
DROP TABLE IF EXISTS sessions CASCADE;
DROP TABLE IF EXISTS user_quests CASCADE;
DROP TABLE IF EXISTS admin_users CASCADE;
DROP TABLE IF EXISTS quests CASCADE;
DROP TABLE IF EXISTS users CASCADE;

-- ========================================
-- Users Table
-- ========================================
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
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
    status user_status DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_username ON users (username);
CREATE INDEX idx_email ON users (email);
CREATE INDEX idx_level ON users (level);
CREATE INDEX idx_xp ON users (xp);

-- ========================================
-- Admin Users
-- ========================================
CREATE TABLE admin_users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(255) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role admin_role DEFAULT 'admin',
    display_name VARCHAR(255),
    permissions JSONB,
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_admin_username ON admin_users (username);
CREATE INDEX idx_admin_role ON admin_users (role);

-- ========================================
-- Quests Table (Main Quest Database)
-- ========================================
CREATE TABLE quests (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    difficulty quest_difficulty DEFAULT 'medium',
    type quest_type DEFAULT 'dare',
    xp_reward INT DEFAULT 10,
    difficulty_multiplier DECIMAL(3, 1) DEFAULT 1.0,
    safety_level safety_level DEFAULT 'safe',
    requires_proof BOOLEAN DEFAULT TRUE,
    keywords VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
);

CREATE INDEX idx_difficulty ON quests (difficulty);
CREATE INDEX idx_type ON quests (type);
CREATE INDEX idx_active ON quests (is_active);

-- ========================================
-- User Quests Progress Tracking
-- ========================================
CREATE TABLE user_quests (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    quest_id INT NOT NULL,
    status user_quest_status DEFAULT 'assigned',
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    submission_id INT NULL,
    attempts INT DEFAULT 0,
    last_attempt TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (quest_id) REFERENCES quests(id) ON DELETE CASCADE,
    UNIQUE (user_id, quest_id)
);

CREATE INDEX idx_user_quest_user ON user_quests (user_id);
CREATE INDEX idx_user_quest_status ON user_quests (user_id, status);
CREATE INDEX idx_user_quest_quest ON user_quests (quest_id);
CREATE INDEX idx_user_quest_status_only ON user_quests (status);

-- ========================================
-- Submissions (Proof Upload)
-- ========================================
CREATE TABLE submissions (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    user_quest_id INT NOT NULL,
    quest_id INT NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    mime_type VARCHAR(100),
    verification_status submission_status DEFAULT 'pending',
    verified_by INT NULL,
    verification_notes TEXT,
    keywords_found VARCHAR(500),
    confidence_score DECIMAL(3, 2),
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    verified_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (user_quest_id) REFERENCES user_quests(id) ON DELETE CASCADE,
    FOREIGN KEY (quest_id) REFERENCES quests(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES admin_users(id) ON DELETE SET NULL
);

CREATE INDEX idx_sub_user ON submissions (user_id);
CREATE INDEX idx_sub_status ON submissions (verification_status);
CREATE INDEX idx_sub_submitted ON submissions (submitted_at);
CREATE INDEX idx_sub_pending ON submissions (verification_status, submitted_at);

-- ========================================
-- Session Management
-- ========================================
CREATE TABLE sessions (
    session_id VARCHAR(255) PRIMARY KEY,
    user_id INT,
    user_type user_type DEFAULT 'user',
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_session_user ON sessions (user_id);
CREATE INDEX idx_session_expires ON sessions (expires_at);

-- ========================================
-- Audit Log
-- ========================================
CREATE TABLE audit_log (
    id SERIAL PRIMARY KEY,
    admin_id INT,
    action VARCHAR(255) NOT NULL,
    target_type VARCHAR(100),
    target_id INT,
    details JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
);

CREATE INDEX idx_audit_admin ON audit_log (admin_id);
CREATE INDEX idx_audit_action ON audit_log (action);
CREATE INDEX idx_audit_created ON audit_log (created_at);

-- ========================================
-- Sample Super Admin (password: admin123 - CHANGE IN PRODUCTION)
-- ========================================
INSERT INTO admin_users (username, email, password, role, display_name, permissions) VALUES
('admin', 'admin@sidequest.local', '$2y$12$wxjkmH6h/d3YEwEPJVQ6sujtdENW98Qi3Fhz8si19TTmI0ItHYLQa', 'super_admin', 'Super Admin', '{"add_quests": true, "delete_quests": true, "edit_quests": true, "verify_proofs": true, "manage_users": true, "manage_admins": true, "view_logs": true}');