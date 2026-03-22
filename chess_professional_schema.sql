-- Professional Chess Features Database Schema
-- Run this file to add all the professional chess features

-- 1. PLAYER ELO RATINGS
CREATE TABLE IF NOT EXISTS chess_ratings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    bullet_rating INT DEFAULT 1200,
    blitz_rating INT DEFAULT 1200,
    rapid_rating INT DEFAULT 1200,
    casual_rating INT DEFAULT 1200,
    bullet_games INT DEFAULT 0,
    blitz_games INT DEFAULT 0,
    rapid_games INT DEFAULT 0,
    casual_games INT DEFAULT 0,
    peak_rating INT DEFAULT 1200,
    peak_mode VARCHAR(20) DEFAULT 'blitz',
    longest_streak INT DEFAULT 0,
    win_streak INT DEFAULT 0,
    loss_streak INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX (bullet_rating),
    INDEX (blitz_rating),
    INDEX (rapid_rating),
    INDEX (peak_rating)
);

-- 2. GAME MODES
CREATE TABLE IF NOT EXISTS chess_game_modes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    mode_name VARCHAR(50) UNIQUE NOT NULL,
    time_limit_seconds INT NOT NULL,
    increment_seconds INT DEFAULT 0,
    is_rated BOOLEAN DEFAULT TRUE,
    description VARCHAR(255),
    icon_emoji VARCHAR(10),
    enabled BOOLEAN DEFAULT TRUE,
    UNIQUE KEY (mode_name)
);

-- Insert default game modes
INSERT IGNORE INTO chess_game_modes VALUES
(1, 'bullet', 60, 0, 1, 'Ultra fast chess (1 min)', '⚡', 1),
(2, 'blitz', 300, 0, 1, 'Fast chess (5 min)', '🔥', 1),
(3, 'rapid', 900, 0, 1, 'Standard speed (15 min)', '⚙️', 1),
(4, 'casual', 300, 0, 0, 'Unrated blitz', '🎮', 1),
(5, 'custom', 0, 0, 0, 'Custom time', '⏱️', 1);

-- 3. MATCH HISTORY
CREATE TABLE IF NOT EXISTS chess_match_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    room_id INT NOT NULL,
    white_player_id INT NOT NULL,
    black_player_id INT NOT NULL,
    game_mode VARCHAR(50) NOT NULL DEFAULT 'blitz',
    result VARCHAR(20) NOT NULL,
    winner_id INT,
    reason VARCHAR(100),
    white_final_rating INT,
    black_final_rating INT,
    white_rating_change INT,
    black_rating_change INT,
    pgn_moves TEXT,
    move_times TEXT,
    accuracy_white DECIMAL(5,2),
    accuracy_black DECIMAL(5,2),
    blunders_white INT DEFAULT 0,
    blunders_black INT DEFAULT 0,
    best_move_white DECIMAL(5,2),
    best_move_black DECIMAL(5,2),
    game_duration_seconds INT,
    played_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    analyzed_at TIMESTAMP NULL,
    FOREIGN KEY (room_id) REFERENCES chess_rooms(id),
    FOREIGN KEY (white_player_id) REFERENCES users(id),
    FOREIGN KEY (black_player_id) REFERENCES users(id),
    FOREIGN KEY (winner_id) REFERENCES users(id),
    INDEX (white_player_id, played_at),
    INDEX (black_player_id, played_at),
    INDEX (result),
    INDEX (game_mode),
    UNIQUE KEY (room_id)
);

-- 4. MOVE ANALYSIS
CREATE TABLE IF NOT EXISTS chess_move_analysis (
    id INT PRIMARY KEY AUTO_INCREMENT,
    match_id INT NOT NULL,
    move_number INT NOT NULL,
    player_id INT NOT NULL,
    move_uci VARCHAR(10),
    move_notation VARCHAR(20),
    centipawn_loss INT,
    is_blunder BOOLEAN,
    is_mistake BOOLEAN,
    is_inaccuracy BOOLEAN,
    suggested_move VARCHAR(10),
    position_fen VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (match_id) REFERENCES chess_match_history(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES users(id),
    INDEX (match_id, move_number),
    UNIQUE KEY (match_id, move_number)
);

-- 5. HINTS
CREATE TABLE IF NOT EXISTS chess_hints (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    room_id INT NOT NULL,
    move_number INT NOT NULL,
    suggested_move VARCHAR(10),
    used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (room_id) REFERENCES chess_rooms(id),
    INDEX (user_id, room_id)
);

-- 6. ACHIEVEMENTS
CREATE TABLE IF NOT EXISTS chess_achievements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    achievement_code VARCHAR(50) UNIQUE NOT NULL,
    title VARCHAR(100),
    description TEXT,
    icon_emoji VARCHAR(10),
    difficulty VARCHAR(20),
    requirement_json TEXT,
    reward_xp INT DEFAULT 0,
    enabled BOOLEAN DEFAULT TRUE,
    UNIQUE KEY (achievement_code)
);

-- Insert achievements
INSERT IGNORE INTO chess_achievements VALUES
(1, 'first_win', 'First Victory', 'Win your first chess game', '🏆', 'bronze', '{"type":"wins","value":1}', 10, 1),
(2, 'rating_1500', 'Intermediate Player', 'Reach 1500 rating', '⭐', 'silver', '{"type":"rating","value":1500}', 50, 1),
(3, 'rating_1800', 'Skilled Player', 'Reach 1800 rating', '✨', 'gold', '{"type":"rating","value":1800}', 100, 1),
(4, 'rating_2000', 'Master', 'Reach 2000 rating', '👑', 'platinum', '{"type":"rating","value":2000}', 250, 1),
(5, '10_wins', 'Winning Streak', 'Win 10 games', '🔥', 'silver', '{"type":"streak","value":10}', 75, 1),
(6, '50_games', 'Dedicated Player', 'Play 50 games', '💪', 'silver', '{"type":"games","value":50}', 60, 1),
(7, 'perfect_accuracy', 'Perfect Game', 'Win with 100% accuracy', '💎', 'platinum', '{"type":"accuracy","value":100}', 200, 1),
(8, 'rapid_specialist', 'Rapid Master', 'Reach 1600 in Rapid', '🎯', 'gold', '{"type":"mode_rating","mode":"rapid","value":1600}', 120, 1);

-- 7. USER ACHIEVEMENTS
CREATE TABLE IF NOT EXISTS user_achievements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    achievement_id INT NOT NULL,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (achievement_id) REFERENCES chess_achievements(id),
    UNIQUE KEY (user_id, achievement_id),
    INDEX (user_id)
);

-- 8. LEADERBOARDS
CREATE TABLE IF NOT EXISTS chess_leaderboards (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    mode VARCHAR(50),
    rank_global INT,
    rank_weekly INT,
    rank_monthly INT,
    rating INT,
    games_played INT,
    win_percentage DECIMAL(5,2),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY (user_id, mode),
    INDEX (mode, rating DESC),
    INDEX (updated_at)
);

-- 9. PUZZLES
CREATE TABLE IF NOT EXISTS chess_puzzles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    fen_position VARCHAR(100) NOT NULL,
    puzzle_date DATE UNIQUE,
    difficulty INT,
    solution_moves TEXT,
    theme VARCHAR(50),
    source_game_id INT,
    rating INT DEFAULT 1200,
    attempt_count INT DEFAULT 0,
    successful_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (puzzle_date),
    INDEX (difficulty)
);

-- 10. PUZZLE ATTEMPTS
CREATE TABLE IF NOT EXISTS user_puzzle_attempts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    puzzle_id INT NOT NULL,
    attempt_number INT,
    solved BOOLEAN,
    time_taken_seconds INT,
    moves_played TEXT,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (puzzle_id) REFERENCES chess_puzzles(id),
    INDEX (user_id, puzzle_id),
    INDEX (puzzle_id)
);

-- 11. BOARD THEMES
CREATE TABLE IF NOT EXISTS chess_board_themes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    theme_code VARCHAR(50) UNIQUE NOT NULL,
    theme_name VARCHAR(100),
    board_light_hex VARCHAR(7),
    board_dark_hex VARCHAR(7),
    highlight_color_hex VARCHAR(7),
    is_default BOOLEAN DEFAULT FALSE,
    is_premium BOOLEAN DEFAULT FALSE,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert themes
INSERT IGNORE INTO chess_board_themes VALUES
(1, 'classic', 'Classic', '#F0D9B5', '#B58863', '#BCE654', 1, 0, 'The classic wood board theme'),
(2, 'dark', 'Dark', '#1A1A1A', '#333333', '#4CAF50', 0, 0, 'Sleek dark theme'),
(3, 'ocean', 'Ocean', '#87CEEB', '#4682B4', '#FFD700', 0, 1, 'Ocean blue board theme (premium)'),
(4, 'forest', 'Forest', '#90EE90', '#228B22', '#FFD700', 0, 1, 'Forest green theme (premium)'),
(5, 'neon', 'Neon', '#0F0F1E', '#1A1A2E', '#00FF00', 0, 1, 'Cyberpunk neon style (premium)'),
(6, 'marble', 'Marble', '#E8E8E8', '#808080', '#FF6B6B', 0, 1, 'Elegant marble theme (premium)');

-- 12. USER PREFERENCES
CREATE TABLE IF NOT EXISTS user_chess_preferences (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    theme_id INT,
    sound_enabled BOOLEAN DEFAULT TRUE,
    animations_enabled BOOLEAN DEFAULT TRUE,
    show_legal_moves BOOLEAN DEFAULT TRUE,
    show_last_move BOOLEAN DEFAULT TRUE,
    enable_hints BOOLEAN DEFAULT TRUE,
    hints_per_game INT DEFAULT 3,
    default_game_mode VARCHAR(50) DEFAULT 'blitz',
    default_color VARCHAR(10) DEFAULT 'random',
    notifications_enabled BOOLEAN DEFAULT TRUE,
    receive_challenge_notifications BOOLEAN DEFAULT TRUE,
    receive_friend_invites BOOLEAN DEFAULT TRUE,
    privacy_mode BOOLEAN DEFAULT FALSE,
    hide_rating BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (theme_id) REFERENCES chess_board_themes(id),
    INDEX (user_id)
);

-- 13. GAME SESSIONS (reconnection)
CREATE TABLE IF NOT EXISTS chess_game_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    room_id INT NOT NULL,
    session_token VARCHAR(255) UNIQUE NOT NULL,
    last_heartbeat TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_connected BOOLEAN DEFAULT TRUE,
    reconnect_attempts INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES chess_rooms(id) ON DELETE CASCADE,
    INDEX (user_id, room_id),
    INDEX (session_token),
    INDEX (expires_at)
);

-- 14. RATING HISTORY
CREATE TABLE IF NOT EXISTS chess_rating_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    mode VARCHAR(50),
    rating_before INT,
    rating_after INT,
    rating_change INT,
    match_id INT,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (match_id) REFERENCES chess_match_history(id),
    INDEX (user_id, mode, recorded_at),
    INDEX (recorded_at)
);

-- 15. ALTER EXISTING TABLES
ALTER TABLE chess_rooms ADD COLUMN IF NOT EXISTS game_mode VARCHAR(50) DEFAULT 'blitz' AFTER room_type;
ALTER TABLE chess_rooms ADD COLUMN IF NOT EXISTS is_rated BOOLEAN DEFAULT TRUE AFTER game_mode;
ALTER TABLE chess_rooms ADD COLUMN IF NOT EXISTS white_rating INT AFTER is_rated;
ALTER TABLE chess_rooms ADD COLUMN IF NOT EXISTS black_rating INT AFTER white_rating;
ALTER TABLE chess_rooms ADD COLUMN IF NOT EXISTS board_theme VARCHAR(50) DEFAULT 'classic' AFTER black_rating;

-- 16. ALTER USERS TABLE
ALTER TABLE users ADD COLUMN IF NOT EXISTS chess_rating INT DEFAULT 1200;
ALTER TABLE users ADD COLUMN IF NOT EXISTS chess_wins INT DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS chess_losses INT DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS chess_draws INT DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS chess_games INT DEFAULT 0;

-- All done!
