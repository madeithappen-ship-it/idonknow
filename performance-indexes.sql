-- ========================================
-- Performance Optimization Indexes
-- These indexes significantly improve query performance
-- ========================================

-- ========================================
-- Users Table Indexes
-- ========================================
CREATE INDEX IF NOT EXISTS idx_users_status ON users(status);
CREATE INDEX IF NOT EXISTS idx_users_level_xp ON users(level DESC, xp DESC);
CREATE INDEX IF NOT EXISTS idx_users_created_at ON users(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_users_last_seen ON users(last_seen DESC);

-- ========================================
-- User Quests Indexes (Critical for dashboard)
-- ========================================
CREATE INDEX IF NOT EXISTS idx_user_quests_user_date ON user_quests(user_id, assigned_at DESC);
CREATE INDEX IF NOT EXISTS idx_user_quests_user_status ON user_quests(user_id, status);
CREATE INDEX IF NOT EXISTS idx_user_quests_quest_id ON user_quests(quest_id);
CREATE INDEX IF NOT EXISTS idx_user_quests_status ON user_quests(status);
CREATE INDEX IF NOT EXISTS idx_user_quests_user_quest_status ON user_quests(user_id, quest_id, status);

-- ========================================
-- Friends Table Indexes
-- ========================================
CREATE INDEX IF NOT EXISTS idx_friends_user_status ON friends(user_id, status);
CREATE INDEX IF NOT EXISTS idx_friends_friend_status ON friends(friend_id, status);
CREATE INDEX IF NOT EXISTS idx_friends_both_status ON friends(status, user_id, friend_id);

-- ========================================
-- Submissions Table Indexes (Critical for approval workflow)
-- ========================================
CREATE INDEX IF NOT EXISTS idx_submissions_user_date ON submissions(user_id, submitted_at DESC);
CREATE INDEX IF NOT EXISTS idx_submissions_status ON submissions(status);
CREATE INDEX IF NOT EXISTS idx_submissions_quest_id ON submissions(quest_id);
CREATE INDEX IF NOT EXISTS idx_submissions_user_quest ON submissions(user_id, quest_id);

-- ========================================
-- Quests Table Indexes
-- ========================================
CREATE INDEX IF NOT EXISTS idx_quests_difficulty ON quests(difficulty);
CREATE INDEX IF NOT EXISTS idx_quests_category ON quests(category);
CREATE INDEX IF NOT EXISTS idx_quests_created_at ON quests(created_at DESC);

-- ========================================
-- Chat Messages Indexes (Common query pattern)
-- ========================================
CREATE INDEX IF NOT EXISTS idx_chat_messages_user_date ON chat_messages(user_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_chat_messages_created_at ON chat_messages(created_at DESC);

-- ========================================
-- Sessions Table Indexes (if stored in DB)
-- ========================================
CREATE INDEX IF NOT EXISTS idx_sessions_user_id ON sessions(user_id);
CREATE INDEX IF NOT EXISTS idx_sessions_created_at ON sessions(created_at DESC);

-- ========================================
-- Global Settings Index
-- ========================================
CREATE INDEX IF NOT EXISTS idx_global_settings_key ON global_settings(setting_key);

-- ========================================
-- Audit Log Indexes
-- ========================================
CREATE INDEX IF NOT EXISTS idx_audit_log_user ON audit_log(user_id);
CREATE INDEX IF NOT EXISTS idx_audit_log_action ON audit_log(action);
CREATE INDEX IF NOT EXISTS idx_audit_log_created_at ON audit_log(created_at DESC);

-- ========================================
-- Admin Notifications Indexes
-- ========================================
CREATE INDEX IF NOT EXISTS idx_admin_notifications_user ON admin_notifications(user_id);
CREATE INDEX IF NOT EXISTS idx_admin_notifications_status ON admin_notifications(status);
