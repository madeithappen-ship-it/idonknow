<?php
/**
 * Chess ELO Rating System
 * Professional chess rating, match tracking, and game mode management
 */

require_once(__DIR__ . '/../config.php');

class ChessEloSystem {
    private $pdo;
    
    // ELO calculation constants
    const K_FACTOR = 32; // Standard K-factor for rating calculation
    const K_FACTOR_NEW = 48; // Higher K for new players (< 30 games)
    const K_FACTOR_HIGH = 24; // Lower K for high-rated players (> 2400)
    const RATING_FLOOR = 800;
    const RATING_CEILING = 3000;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Get or create player rating record
     */
    public function getOrCreateRating($user_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM chess_ratings WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $rating = $stmt->fetch();

        if (!$rating) {
            // Create new rating record
            $insert = $this->pdo->prepare("
                INSERT INTO chess_ratings (user_id) VALUES (?)
            ");
            $insert->execute([$user_id]);
            $stmt->execute([$user_id]);
            $rating = $stmt->fetch();
        }

        return $rating;
    }

    /**
     * Get current rating for a player in a specific mode
     */
    public function getRatingForMode($user_id, $mode = 'blitz') {
        $rating = $this->getOrCreateRating($user_id);
        $column = $mode . '_rating';
        return $rating[$column] ?? 1200;
    }

    /**
     * Calculate expected score (Elo formula)
     */
    public function calculateExpectedScore($player_rating, $opponent_rating) {
        $diff = $opponent_rating - $player_rating;
        return 1 / (1 + pow(10, $diff / 400));
    }

    /**
     * Calculate K-factor based on player history
     */
    public function getKFactor($user_id, $mode) {
        $rating = $this->getOrCreateRating($user_id);
        $games_column = $mode . '_games';
        $rating_column = $mode . '_rating';
        
        $games = $rating[$games_column] ?? 0;
        $rating_val = $rating[$rating_column] ?? 1200;

        // Higher K for new players
        if ($games < 30) {
            return self::K_FACTOR_NEW;
        }
        
        // Lower K for very high rated players
        if ($rating_val > 2400) {
            return self::K_FACTOR_HIGH;
        }

        return self::K_FACTOR;
    }

    /**
     * Update player rating after a game
     */
    public function updateRatingAfterGame($match_id, $white_id, $black_id, $result, $mode = 'blitz') {
        try {
            $this->pdo->beginTransaction();

            $white_rating = $this->getRatingForMode($white_id, $mode);
            $black_rating = $this->getRatingForMode($black_id, $mode);

            $white_expected = $this->calculateExpectedScore($white_rating, $black_rating);
            $black_expected = $this->calculateExpectedScore($black_rating, $white_rating);

            $white_k = $this->getKFactor($white_id, $mode);
            $black_k = $this->getKFactor($black_id, $mode);

            // Determine scores based on result
            $white_score = 0;
            $black_score = 0;
            $white_change = 0;
            $black_change = 0;

            if ($result === 'white_win') {
                $white_score = 1;
                $black_score = 0;
            } elseif ($result === 'black_win') {
                $white_score = 0;
                $black_score = 1;
            } else { // draw
                $white_score = 0.5;
                $black_score = 0.5;
            }

            // Calculate new ratings
            $white_new = max(self::RATING_FLOOR, min(self::RATING_CEILING, 
                $white_rating + $white_k * ($white_score - $white_expected)
            ));
            $black_new = max(self::RATING_FLOOR, min(self::RATING_CEILING,
                $black_rating + $black_k * ($black_score - $black_expected)
            ));

            $white_change = round($white_new - $white_rating);
            $black_change = round($black_new - $black_rating);

            // Update ratings in database
            $this->updatePlayerRating($white_id, $mode, round($white_new));
            $this->updatePlayerRating($black_id, $mode, round($black_new));

            // Record rating changes
            $this->recordRatingHistory($white_id, $mode, $white_rating, round($white_new), $white_change, $match_id);
            $this->recordRatingHistory($black_id, $mode, $black_rating, round($black_new), $black_change, $match_id);

            // Update match history with final ratings
            $update = $this->pdo->prepare("
                UPDATE chess_match_history 
                SET white_final_rating = ?, black_final_rating = ?,
                    white_rating_change = ?, black_rating_change = ?
                WHERE id = ?
            ");
            $update->execute([round($white_new), round($black_new), $white_change, $black_change, $match_id]);

            // Update game count
            $this->incrementGameCount($white_id, $mode);
            $this->incrementGameCount($black_id, $mode);

            // Check for achievements
            $this->checkAchievements($white_id, $result === 'white_win');
            $this->checkAchievements($black_id, $result === 'black_win');

            $this->pdo->commit();

            return [
                'success' => true,
                'white_rating_change' => $white_change,
                'black_rating_change' => $black_change,
                'white_new_rating' => round($white_new),
                'black_new_rating' => round($black_new)
            ];

        } catch (Exception $e) {
            $this->pdo->rollBack();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Update player's rating for a mode
     */
    private function updatePlayerRating($user_id, $mode, $new_rating) {
        $rating = $this->getOrCreateRating($user_id);
        $column = $mode . '_rating';
        $peak_column = 'peak_rating';

        $update = $this->pdo->prepare("
            UPDATE chess_ratings 
            SET {$column} = ?
            WHERE user_id = ?
        ");
        $update->execute([$new_rating, $user_id]);

        // Update peak rating if applicable
        if ($new_rating > ($rating[$peak_column] ?? 1200)) {
            $update_peak = $this->pdo->prepare("
                UPDATE chess_ratings 
                SET {$peak_column} = ?, peak_mode = ?
                WHERE user_id = ?
            ");
            $update_peak->execute([$new_rating, $mode, $user_id]);
        }

        // Update users table with overall rating (average of all modes)
        $this->updateOverallRating($user_id);
    }

    /**
     * Record rating change in history
     */
    private function recordRatingHistory($user_id, $mode, $before, $after, $change, $match_id) {
        $insert = $this->pdo->prepare("
            INSERT INTO chess_rating_history 
            (user_id, mode, rating_before, rating_after, rating_change, match_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $insert->execute([$user_id, $mode, $before, $after, $change, $match_id]);
    }

    /**
     * Increment game count for a player/mode
     */
    private function incrementGameCount($user_id, $mode) {
        $column = $mode . '_games';
        $update = $this->pdo->prepare("
            UPDATE chess_ratings 
            SET {$column} = {$column} + 1
            WHERE user_id = ?
        ");
        $update->execute([$user_id]);
    }

    /**
     * Update overall rating (average of all modes)
     */
    private function updateOverallRating($user_id) {
        $rating = $this->getOrCreateRating($user_id);
        $ratings = [
            $rating['bullet_rating'],
            $rating['blitz_rating'],
            $rating['rapid_rating'],
            $rating['casual_rating']
        ];
        
        $overall = array_sum($ratings) / count($ratings);
        
        $update = $this->pdo->prepare("
            UPDATE users 
            SET chess_rating = ?
            WHERE id = ?
        ");
        $update->execute([round($overall), $user_id]);
    }

    /**
     * Update game stats for users (wins/losses/draws)
     */
    public function updateGameStats($white_id, $black_id, $result) {
        switch ($result) {
            case 'white_win':
                $this->pdo->query("UPDATE users SET chess_wins = chess_wins + 1 WHERE id = {$white_id}");
                $this->pdo->query("UPDATE users SET chess_losses = chess_losses + 1 WHERE id = {$black_id}");
                break;
            case 'black_win':
                $this->pdo->query("UPDATE users SET chess_losses = chess_losses + 1 WHERE id = {$white_id}");
                $this->pdo->query("UPDATE users SET chess_wins = chess_wins + 1 WHERE id = {$black_id}");
                break;
            case 'draw':
                $this->pdo->query("UPDATE users SET chess_draws = chess_draws + 1 WHERE id = {$white_id}");
                $this->pdo->query("UPDATE users SET chess_draws = chess_draws + 1 WHERE id = {$black_id}");
                break;
        }
        
        $this->pdo->query("UPDATE users SET chess_games = chess_games + 1 WHERE id IN ({$white_id}, {$black_id})");
    }

    /**
     * Check and award achievements
     */
    private function checkAchievements($user_id, $won) {
        if (!$won) return; // Only for winners for now

        $rating = $this->getOrCreateRating($user_id);
        $stats = $this->pdo->query("SELECT * FROM users WHERE id = {$user_id}")->fetch();

        // Check various achievement criteria
        $achievements_to_check = [];

        if ($stats['chess_wins'] == 1) {
            $achievements_to_check[] = 'first_win';
        }

        if ($rating['blitz_rating'] >= 1500) {
            $achievements_to_check[] = 'rating_1500';
        }
        if ($rating['blitz_rating'] >= 1800) {
            $achievements_to_check[] = 'rating_1800';
        }
        if ($rating['blitz_rating'] >= 2000) {
            $achievements_to_check[] = 'rating_2000';
        }

        if ($stats['chess_wins'] >= 10) {
            $achievements_to_check[] = '10_wins';
        }
        if ($stats['chess_games'] >= 50) {
            $achievements_to_check[] = '50_games';
        }

        if ($rating['rapid_rating'] >= 1600) {
            $achievements_to_check[] = 'rapid_specialist';
        }

        // Award achievements
        foreach ($achievements_to_check as $code) {
            $ach = $this->pdo->prepare("SELECT id FROM chess_achievements WHERE achievement_code = ?");
            $ach->execute([$code]);
            $ach_record = $ach->fetch();

            if ($ach_record) {
                $check = $this->pdo->prepare("SELECT id FROM user_achievements WHERE user_id = ? AND achievement_id = ?");
                $check->execute([$user_id, $ach_record['id']]);

                if (!$check->fetch()) {
                    $insert = $this->pdo->prepare("INSERT INTO user_achievements (user_id, achievement_id) VALUES (?, ?)");
                    $insert->execute([$user_id, $ach_record['id']]);
                }
            }
        }
    }

    /**
     * Get leaderboard for a mode
     */
    public function getLeaderboard($mode = 'blitz', $limit = 100, $offset = 0) {
        $column = $mode . '_rating';
        
        $stmt = $this->pdo->prepare("
            SELECT 
                cr.user_id,
                u.username,
                u.avatar_url,
                u.level,
                cr.{$column} as rating,
                cr.{$mode}_games as games,
                ROW_NUMBER() OVER (ORDER BY cr.{$column} DESC) as rank
            FROM chess_ratings cr
            JOIN users u ON cr.user_id = u.id
            WHERE u.status = 'active'
            ORDER BY cr.{$column} DESC
            LIMIT ? OFFSET ?
        ");
        
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll();
    }

    /**
     * Get player stats for profile
     */
    public function getPlayerStats($user_id) {
        $rating = $this->getOrCreateRating($user_id);
        $user = $this->pdo->query("SELECT * FROM users WHERE id = {$user_id}")->fetch();

        // Get recent games
        $recent = $this->pdo->prepare("
            SELECT * FROM chess_match_history
            WHERE (white_player_id = ? OR black_player_id = ?)
            ORDER BY played_at DESC
            LIMIT 10
        ");
        $recent->execute([$user_id, $user_id]);
        $recent_games = $recent->fetchAll();

        // Get achievements
        $achievements = $this->pdo->prepare("
            SELECT ca.* FROM chess_achievements ca
            JOIN user_achievements ua ON ca.id = ua.achievement_id
            WHERE ua.user_id = ?
        ");
        $achievements->execute([$user_id]);
        $unlocked_achievements = $achievements->fetchAll();

        return [
            'user' => $user,
            'ratings' => $rating,
            'recent_games' => $recent_games,
            'achievements' => $unlocked_achievements,
            'stats' => [
                'total_games' => $user['chess_games'],
                'wins' => $user['chess_wins'],
                'losses' => $user['chess_losses'],
                'draws' => $user['chess_draws'],
                'win_rate' => $user['chess_games'] > 0 ? round(($user['chess_wins'] / $user['chess_games']) * 100, 1) : 0
            ]
        ];
    }

    /**
     * Get rating history for a player
     */
    public function getRatingHistory($user_id, $mode, $limit = 50) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM chess_rating_history
            WHERE user_id = ? AND mode = ?
            ORDER BY recorded_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $user_id, PDO::PARAM_INT);
        $stmt->bindValue(2, $mode, PDO::PARAM_STR);
        $stmt->bindValue(3, $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return array_reverse($stmt->fetchAll()); // Return in chronological order
    }
}

// API Endpoints
header('Content-Type: application/json');

try {
    $pdo = new PDO(
        'mysql:host=' . getenv('DB_HOST') . ';dbname=' . getenv('DB_NAME'),
        getenv('DB_USER'),
        getenv('DB_PASS'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $elo = new ChessEloSystem($pdo);
    $action = $_GET['action'] ?? '';
    $user_id = (int)($_SESSION['user_id'] ?? 0);

    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }

    switch ($action) {
        case 'get_rating':
            $mode = $_GET['mode'] ?? 'blitz';
            $target_id =  (int)($_GET['user_id'] ?? $user_id);
            $rating = $elo->getRatingForMode($target_id, $mode);
            echo json_encode(['rating' => $rating, 'mode' => $mode]);
            break;

        case 'get_leaderboard':
            $mode = $_GET['mode'] ?? 'blitz';
            $limit = (int)($_GET['limit'] ?? 100);
            echo json_encode($elo->getLeaderboard($mode, $limit));
            break;

        case 'get_stats':
            $target_id = (int)($_GET['user_id'] ?? $user_id);
            echo json_encode($elo->getPlayerStats($target_id));
            break;

        case 'get_rating_history':
            $mode = $_GET['mode'] ?? 'blitz';
            echo json_encode($elo->getRatingHistory($user_id, $mode));
            break;

        case 'get_all_ratings':
            $target_id = (int)($_GET['user_id'] ?? $user_id);
            $rating = $elo->getOrCreateRating($target_id);
            echo json_encode([
                'bullet' => $rating['bullet_rating'],
                'blitz' => $rating['blitz_rating'],
                'rapid' => $rating['rapid_rating'],
                'casual' => $rating['casual_rating'],
                'peak' => $rating['peak_rating']
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
