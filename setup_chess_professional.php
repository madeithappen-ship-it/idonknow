<?php
/**
 * Comprehensive Chess Professional Features Database Setup
 * Adds ELO ratings, game modes, analysis, leaderboards, and advanced features
 */

require_once(__DIR__ . '/config.php');

try {
    echo "🚀 Setting up Professional Chess Features...\n\n";

    // 1. PLAYER ELO RATINGS TABLE
    $pdo->exec("
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
        )
    ");
    echo "✅ Created chess_ratings table\n";

    // 2. GAME MODES & VARIANTS
    $pdo->exec("
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
        )
    ");
    echo "✅ Created chess_game_modes table\n";

    // Insert default game modes
    $modes = [
        ['bullet', 60, 0, true, 'Ultra fast chess (1 min)', '⚡', true],
        ['blitz', 300, 0, true, 'Fast chess (5 min)', '🔥', true],
        ['rapid', 900, 0, true, 'Standard speed (15 min)', '⚙️', true],
        ['casual', 300, 0, false, 'Unrated blitz', '🎮', true],
        ['custom', 0, 0, false, 'Custom time', '⏱️', true],
    ];
    
    foreach ($modes as $mode) {
        $check = $pdo->prepare("SELECT id FROM chess_game_modes WHERE mode_name = ?");
        $check->execute([$mode[0]]);
        if (!$check->fetch()) {
            $insert = $pdo->prepare("
                INSERT INTO chess_game_modes 
                (mode_name, time_limit_seconds, increment_seconds, is_rated, description, icon_emoji, enabled)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $insert->execute($mode);
        }
    }
    echo "✅ Inserted default game modes\n";

    // 3. MATCH HISTORY & ANALYSIS
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS chess_match_history (
            id INT PRIMARY KEY AUTO_INCREMENT,
            room_id INT NOT NULL,
            white_player_id INT NOT NULL,
            black_player_id INT NOT NULL,
            game_mode VARCHAR(50) NOT NULL DEFAULT 'blitz',
            result VARCHAR(20) NOT NULL, -- 'white_win', 'black_win', 'draw', 'abandoned'
            winner_id INT,
            reason VARCHAR(100), -- checkmate, resignation, timeout, stalemate, draw
            white_final_rating INT,
            black_final_rating INT,
            white_rating_change INT,
            black_rating_change INT,
            pgn_moves TEXT, -- PGN notation
            move_times TEXT, -- JSON: time per move
            accuracy_white DECIMAL(5,2), -- percentage
            accuracy_black DECIMAL(5,2),
            blunders_white INT DEFAULT 0,
            blunders_black INT DEFAULT 0,
            best_move_white DECIMAL(5,2), -- centipawn loss
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
        )
    ");
    echo "✅ Created chess_match_history table\n";

    // 4. GAME ANALYSIS (Move quality)
    $pdo->exec("
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
        )
    ");
    echo "✅ Created chess_move_analysis table\n";

    // 5. HINTS & SUGGESTIONS
    $pdo->exec("
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
        )
    ");
    echo "✅ Created chess_hints table\n";

    // 6. ACHIEVEMENTS & BADGES
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS chess_achievements (
            id INT PRIMARY KEY AUTO_INCREMENT,
            achievement_code VARCHAR(50) UNIQUE NOT NULL,
            title VARCHAR(100),
            description TEXT,
            icon_emoji VARCHAR(10),
            difficulty VARCHAR(20), -- bronze, silver, gold, platinum
            requirement_json TEXT, -- JSON: {type: 'rating', value: 1600}
            reward_xp INT DEFAULT 0,
            enabled BOOLEAN DEFAULT TRUE,
            UNIQUE KEY (achievement_code)
        )
    ");
    echo "✅ Created chess_achievements table\n";

    // Insert default achievements
    $achievements = [
        ['first_win', 'First Victory', 'Win your first chess game', '🏆', 'bronze', json_encode(['type' => 'wins', 'value' => 1]), 10],
        ['rating_1500', 'Intermediate Player', 'Reach 1500 rating', '⭐', 'silver', json_encode(['type' => 'rating', 'value' => 1500]), 50],
        ['rating_1800', 'Skilled Player', 'Reach 1800 rating', '✨', 'gold', json_encode(['type' => 'rating', 'value' => 1800]), 100],
        ['rating_2000', 'Master', 'Reach 2000 rating', '👑', 'platinum', json_encode(['type' => 'rating', 'value' => 2000]), 250],
        ['10_wins', 'Winning Streak', 'Win 10 games', '🔥', 'silver', json_encode(['type' => 'streak', 'value' => 10]), 75],
        ['50_games', 'Dedicated Player', 'Play 50 games', '💪', 'silver', json_encode(['type' => 'games', 'value' => 50]), 60],
        ['perfect_accuracy', 'Perfect Game', 'Win with 100% accuracy', '💎', 'platinum', json_encode(['type' => 'accuracy', 'value' => 100]), 200],
        ['rapid_specialist', 'Rapid Master', 'Reach 1600 in Rapid', '🎯', 'gold', json_encode(['type' => 'mode_rating', 'mode' => 'rapid', 'value' => 1600]), 120],
    ];
    
    foreach ($achievements as $ach) {
        $check = $pdo->prepare("SELECT id FROM chess_achievements WHERE achievement_code = ?");
        $check->execute([$ach[0]]);
        if (!$check->fetch()) {
            $insert = $pdo->prepare("
                INSERT INTO chess_achievements 
                (achievement_code, title, description, icon_emoji, difficulty, requirement_json, reward_xp)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $insert->execute($ach);
        }
    }
    echo "✅ Inserted default achievements\n";

    // 7. USER ACHIEVEMENTS (earned)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_achievements (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            achievement_id INT NOT NULL,
            earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (achievement_id) REFERENCES chess_achievements(id),
            UNIQUE KEY (user_id, achievement_id),
            INDEX (user_id)
        )
    ");
    echo "✅ Created user_achievements table\n";

    // 8. LEADERBOARDS (cached for performance)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS chess_leaderboards (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            mode VARCHAR(50), -- 'bullet', 'blitz', 'rapid', 'overall'
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
        )
    ");
    echo "✅ Created chess_leaderboards table\n";

    // 9. DAILY PUZZLES
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS chess_puzzles (
            id INT PRIMARY KEY AUTO_INCREMENT,
            fen_position VARCHAR(100) NOT NULL,
            puzzle_date DATE UNIQUE,
            difficulty INT, -- 1-3 (easy, medium, hard)
            solution_moves TEXT, -- JSON: best move sequence
            theme VARCHAR(50), -- 'checkmate', 'pin', 'fork', etc
            source_game_id INT,
            rating INT DEFAULT 1200,
            attempt_count INT DEFAULT 0,
            successful_count INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY (puzzle_date),
            INDEX (difficulty)
        )
    ");
    echo "✅ Created chess_puzzles table\n";

    // 10. USER PUZZLE ATTEMPTS
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_puzzle_attempts (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            puzzle_id INT NOT NULL,
            attempt_number INT,
            solved BOOLEAN,
            time_taken_seconds INT,
            moves_played TEXT, -- actual moves player made
            attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (puzzle_id) REFERENCES chess_puzzles(id),
            INDEX (user_id, puzzle_id),
            INDEX (puzzle_id)
        )
    ");
    echo "✅ Created user_puzzle_attempts table\n";

    // 11. BOARD THEMES
    $pdo->exec("
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
        )
    ");
    echo "✅ Created chess_board_themes table\n";

    // Insert default themes
    $themes = [
        ['classic', 'Classic', '#F0D9B5', '#B58863', '#BCE654', true, false, 'The classic wood board theme'],
        ['dark', 'Dark', '#1A1A1A', '#333333', '#4CAF50', false, false, 'Sleek dark theme'],
        ['ocean', 'Ocean', '#87CEEB', '#4682B4', '#FFD700', false, true, 'Ocean blue board theme (premium)'],
        ['forest', 'Forest', '#90EE90', '#228B22', '#FFD700', false, true, 'Forest green theme (premium)'],
        ['neon', 'Neon', '#0F0F1E', '#1A1A2E', '#00FF00', false, true, 'Cyberpunk neon style (premium)'],
        ['marble', 'Marble', '#E8E8E8', '#808080', '#FF6B6B', false, true, 'Elegant marble theme (premium)'],
    ];
    
    foreach ($themes as $theme) {
        $check = $pdo->prepare("SELECT id FROM chess_board_themes WHERE theme_code = ?");
        $check->execute([$theme[0]]);
        if (!$check->fetch()) {
            $insert = $pdo->prepare("
                INSERT INTO chess_board_themes 
                (theme_code, theme_name, board_light_hex, board_dark_hex, highlight_color_hex, is_default, is_premium, description)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insert->execute($theme);
        }
    }
    echo "✅ Inserted default board themes\n";

    // 12. USER PREFERENCES
    $pdo->exec("
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
            default_color VARCHAR(10) DEFAULT 'random', -- 'white', 'black', 'random'
            notifications_enabled BOOLEAN DEFAULT TRUE,
            receive_challenge_notifications BOOLEAN DEFAULT TRUE,
            receive_friend_invites BOOLEAN DEFAULT TRUE,
            privacy_mode BOOLEAN DEFAULT FALSE, -- hide profile stats
            hide_rating BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (theme_id) REFERENCES chess_board_themes(id),
            INDEX (user_id)
        )
    ");
    echo "✅ Created user_chess_preferences table\n";

    // 13. RECONNECTION TOKENS (session tracking)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS chess_game_sessions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            room_id INT NOT NULL,
            session_token VARCHAR(255) UNIQUE NOT NULL,
            last_heartbeat TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            is_connected BOOLEAN DEFAULT TRUE,
            reconnect_attempts INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (room_id) REFERENCES chess_rooms(id) ON DELETE CASCADE,
            INDEX (user_id, room_id),
            INDEX (session_token),
            INDEX (expires_at)
        )
    ");
    echo "✅ Created chess_game_sessions table\n";

    // 14. RATINGS HISTORY (for tracking trends)
    $pdo->exec("
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
        )
    ");
    echo "✅ Created chess_rating_history table\n";

    // 15. ADD COLUMNS TO EXISTING chess_rooms TABLE
    $checkColumns = $pdo->query("DESCRIBE chess_rooms");
    $columns = array_column($checkColumns->fetchAll(), 'Field');
    
    $newColumns = [
        'game_mode' => "ALTER TABLE chess_rooms ADD COLUMN game_mode VARCHAR(50) DEFAULT 'blitz' AFTER room_type",
        'is_rated' => "ALTER TABLE chess_rooms ADD COLUMN is_rated BOOLEAN DEFAULT TRUE AFTER game_mode",
        'white_rating' => "ALTER TABLE chess_rooms ADD COLUMN white_rating INT AFTER is_rated",
        'black_rating' => "ALTER TABLE chess_rooms ADD COLUMN black_rating INT AFTER white_rating",
        'board_theme' => "ALTER TABLE chess_rooms ADD COLUMN board_theme VARCHAR(50) DEFAULT 'classic' AFTER black_rating",
    ];
    
    foreach ($newColumns as $col => $sql) {
        if (!in_array($col, $columns)) {
            $pdo->exec($sql);
            echo "✅ Added $col to chess_rooms\n";
        }
    }

    // 16. ADD COLUMNS TO EXISTING users TABLE
    $checkColumns = $pdo->query("DESCRIBE users");
    $columns = array_column($checkColumns->fetchAll(), 'Field');
    
    $newColumns = [
        'chess_rating' => "ALTER TABLE users ADD COLUMN chess_rating INT DEFAULT 1200",
        'chess_wins' => "ALTER TABLE users ADD COLUMN chess_wins INT DEFAULT 0",
        'chess_losses' => "ALTER TABLE users ADD COLUMN chess_losses INT DEFAULT 0",
        'chess_draws' => "ALTER TABLE users ADD COLUMN chess_draws INT DEFAULT 0",
        'chess_games' => "ALTER TABLE users ADD COLUMN chess_games INT DEFAULT 0",
    ];
    
    foreach ($newColumns as $col => $sql) {
        if (!in_array($col, $columns)) {
            $pdo->exec($sql);
            echo "✅ Added $col to users\n";
        }
    }

    echo "\n✅ ============================================\n";
    echo "✅ Database schema setup COMPLETE!\n";
    echo "✅ All professional features ready to use\n";
    echo "✅ ============================================\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
