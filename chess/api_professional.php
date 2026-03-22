<?php
/**
 * Professional Chess API v2
 * Extends base API with ELO, game modes, analysis, and advanced features
 */

session_start();
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/elo_system.php');

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }

    $user_id = (int)$_SESSION['user_id'];
    $action = $_GET['action'] ?? '';

    $pdo = new PDO(
        'mysql:host=' . getenv('DB_HOST') . ';dbname=' . getenv('DB_NAME'),
        getenv('DB_USER'),
        getenv('DB_PASS'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $elo = new ChessEloSystem($pdo);

    switch ($action) {

        // ============= GAME MODE SELECTION =============
        case 'create_game_with_mode':
            $mode = $_POST['mode'] ?? 'blitz';
            $color = $_POST['color'] ?? 'random';
            $is_rated = $_POST['is_rated'] ?? true;
            $custom_time = (int)($_POST['custom_time'] ?? 300);

            // Validate mode
            $mode_check = $pdo->prepare("SELECT * FROM chess_game_modes WHERE mode_name = ? AND enabled = 1");
            $mode_check->execute([$mode]);
            $game_mode = $mode_check->fetch();

            if (!$game_mode) {
                echo json_encode(['error' => 'Invalid game mode']);
                exit;
            }

            // Get time limit
            $time_limit = $game_mode['time_limit_seconds'];
            if ($mode === 'custom') {
                $time_limit = $custom_time;
            }

            // Create room
            $insert = $pdo->prepare("
                INSERT INTO chess_rooms 
                (room_type, white_player, black_player, game_mode, is_rated, white_rating, black_rating, board_theme)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $white_id = ($color === 'black') ? null : $user_id;
            $black_id = ($color === 'white') ? null : $user_id;
            
            if ($color === 'random') {
                $white_id = $user_id;
                $black_id = null;
            }

            $white_rating = $elo->getRatingForMode($user_id, $mode);
            $black_rating = 1200; // Placeholder for opponent

            $insert->execute([
                'public',
                $white_id,
                $black_id,
                $mode,
                $is_rated,
                $white_rating,
                1200
            ]);

            $room_id = $pdo->lastInsertId();

            // Create session token for reconnection
            $token = bin2hex(random_bytes(32));
            $session = $pdo->prepare("
                INSERT INTO chess_game_sessions 
                (user_id, room_id, session_token, expires_at)
                VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))
            ");
            $session->execute([$user_id, $room_id, $token]);

            echo json_encode([
                'success' => true,
                'room_id' => $room_id,
                'session_token' => $token,
                'mode' => $mode,
                'time_limit' => $time_limit,
                'is_rated' => (bool)$is_rated
            ]);
            break;

        // ============= MATCH HISTORY =============
        case 'record_match':
            $room_id = (int)$_POST['room_id'];
            $white_id = (int)$_POST['white_id'];
            $black_id = (int)$_POST['black_id'];
            $result = $_POST['result']; // 'white_win', 'black_win', 'draw', 'abandoned'
            $reason = $_POST['reason'] ?? '';
            $pgn_moves = $_POST['pgn_moves'] ?? '';
            $mode = $_POST['mode'] ?? 'blitz';

            // Check if match already recorded
            $check = $pdo->prepare("SELECT id FROM chess_match_history WHERE room_id = ?");
            $check->execute([$room_id]);
            if ($check->fetch()) {
                echo json_encode(['error' => 'Match already recorded']);
                exit;
            }

            $pdo->beginTransaction();

            try {
                // Record match
                $insert = $pdo->prepare("
                    INSERT INTO chess_match_history 
                    (room_id, white_player_id, black_player_id, game_mode, result, reason, pgn_moves, winner_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $winner_id = ($result === 'white_win') ? $white_id : (($result === 'black_win') ? $black_id : null);
                $game_duration = (int)($_POST['game_duration'] ?? 0);

                $insert->execute([
                    $room_id, $white_id, $black_id, $mode, $result, $reason, $pgn_moves, $winner_id
                ]);

                $match_id = $pdo->lastInsertId();

                // Check if game is rated
                $room = $pdo->query("SELECT is_rated FROM chess_rooms WHERE id = $room_id")->fetch();
                
                if ($room['is_rated']) {
                    // Update ELO ratings
                    $rating_result = $elo->updateRatingAfterGame($match_id, $white_id, $black_id, $result, $mode);
                    
                    if (!$rating_result['success']) {
                        throw new Exception($rating_result['error']);
                    }

                    // Update game stats
                    $elo->updateGameStats($white_id, $black_id, $result);
                }

                // Update leaderboard
                $this->updateLeaderboard($pdo, $mode);

                $pdo->commit();

                echo json_encode([
                    'success' => true,
                    'match_id' => $match_id,
                    'rating_update' => $rating_result ?? null
                ]);

            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;

        // ============= ACCURACY & ANALYSIS =============
        case 'record_move_analysis':
            $match_id = (int)$_POST['match_id'];
            $move_number = (int)$_POST['move_number'];
            $player_id = (int)$_POST['player_id'];
            $move_uci = $_POST['move_uci'] ?? '';
            $centipawn_loss = (int)($_POST['centipawn_loss'] ?? 0);

            // Determine if blunder/mistake/inaccuracy
            $is_blunder = $centipawn_loss > 200;
            $is_mistake = $centipawn_loss > 50 && $centipawn_loss <= 200;
            $is_inaccuracy = $centipawn_loss > 0 && $centipawn_loss <= 50;

            $insert = $pdo->prepare("
                INSERT INTO chess_move_analysis 
                (match_id, move_number, player_id, move_uci, centipawn_loss, is_blunder, is_mistake, is_inaccuracy)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                centipawn_loss = ?, is_blunder = ?, is_mistake = ?, is_inaccuracy = ?
            ");

            $insert->execute([
                $match_id, $move_number, $player_id, $move_uci, $centipawn_loss,
                (int)$is_blunder, (int)$is_mistake, (int)$is_inaccuracy,
                $centipawn_loss, (int)$is_blunder, (int)$is_mistake, (int)$is_inaccuracy
            ]);

            echo json_encode(['success' => true]);
            break;

        // ============= GET MATCH ANALYSIS =============
        case 'get_match_analysis':
            $match_id = (int)($_GET['match_id'] ?? 0);

            $match = $pdo->prepare("SELECT * FROM chess_match_history WHERE id = ?");
            $match->execute([$match_id]);
            $match_data = $match->fetch();

            if (!$match_data) {
                echo json_encode(['error' => 'Match not found']);
                exit;
            }

            // Get move analysis
            $moves = $pdo->prepare("
                SELECT * FROM chess_move_analysis 
                WHERE match_id = ? 
                ORDER BY move_number ASC
            ");
            $moves->execute([$match_id]);
            $moves_data = $moves->fetchAll();

            // Calculate accuracy
            $white_moves = array_filter($moves_data, fn($m) => $m['player_id'] == $match_data['white_player_id']);
            $black_moves = array_filter($moves_data, fn($m) => $m['player_id'] == $match_data['black_player_id']);

            $white_accuracy = $this->calculateAccuracy($white_moves);
            $black_accuracy = $this->calculateAccuracy($black_moves);

            echo json_encode([
                'match' => $match_data,
                'moves' => $moves_data,
                'accuracy' => [
                    'white' => $white_accuracy,
                    'black' => $black_accuracy
                ],
                'stats' => [
                    'white_blunders' => count(array_filter($white_moves, fn($m) => $m['is_blunder'])),
                    'black_blunders' => count(array_filter($black_moves, fn($m) => $m['is_blunder'])),
                    'duration_seconds' => $match_data['game_duration_seconds']
                ]
            ]);
            break;

        // ============= HINTS =============
        case 'use_hint':
            $room_id = (int)$_POST['room_id'];
            $suggested_move = $_POST['suggested_move'] ?? '';
            $move_number = (int)($_POST['move_number'] ?? 0);

            // Check hint limit
            $prefs = $pdo->prepare("SELECT hints_per_game FROM user_chess_preferences WHERE user_id = ?");
            $prefs->execute([$user_id]);
            $pref = $prefs->fetch();
            $hints_limit = $pref['hints_per_game'] ?? 3;

            $hint_count = $pdo->prepare("
                SELECT COUNT(*) as count FROM chess_hints 
                WHERE user_id = ? AND room_id = ?
            ");
            $hint_count->execute([$user_id, $room_id]);
            $count = $hint_count->fetch()['count'];

            if ($count >= $hints_limit) {
                echo json_encode(['error' => 'Hint limit reached for this game']);
                exit;
            }

            $insert = $pdo->prepare("
                INSERT INTO chess_hints (user_id, room_id, move_number, suggested_move)
                VALUES (?, ?, ?, ?)
            ");
            $insert->execute([$user_id, $room_id, $move_number, $suggested_move]);

            echo json_encode([
                'success' => true,
                'suggested_move' => $suggested_move,
                'hints_remaining' => $hints_limit - $count - 1
            ]);
            break;

        // ============= RECONNECTION =============
        case 'reconnect_to_game':
            $session_token = $_POST['session_token'] ?? '';

            $session = $pdo->prepare("
                SELECT * FROM chess_game_sessions 
                WHERE session_token = ? AND user_id = ? AND expires_at > NOW()
            ");
            $session->execute([$session_token, $user_id]);
            $session_data = $session->fetch();

            if (!$session_data) {
                echo json_encode(['error' => 'Invalid or expired session']);
                exit;
            }

            // Update heartbeat
            $update = $pdo->prepare("
                UPDATE chess_game_sessions 
                SET last_heartbeat = NOW(), reconnect_attempts = reconnect_attempts + 1
                WHERE id = ?
            ");
            $update->execute([$session_data['id']]);

            // Get room state
            $room = $pdo->prepare("SELECT * FROM chess_rooms WHERE id = ?");
            $room->execute([$session_data['room_id']]);
            $room_data = $room->fetch();

            echo json_encode([
                'success' => true,
                'room_id' => $session_data['room_id'],
                'room' => $room_data,
                'session_id' => $session_data['id']
            ]);
            break;

        // ============= LEADERBOARDS =============
        case 'get_leaderboard':
            $mode = $_GET['mode'] ?? 'blitz';
            $limit = (int)($_GET['limit'] ?? 100);
            $page = (int)($_GET['page'] ?? 0);

            $leaderboard = $elo->getLeaderboard($mode, $limit, $page * $limit);

            echo json_encode([
                'leaderboard' => $leaderboard,
                'mode' => $mode,
                'page' => $page
            ]);
            break;

        // ============= PREFERENCES =============
        case 'save_preferences':
            $theme_id = (int)($_POST['theme_id'] ?? 1);
            $sound_enabled = (bool)($_POST['sound_enabled'] ?? true);
            $animations_enabled = (bool)($_POST['animations_enabled'] ?? true);
            $hints_per_game = (int)($_POST['hints_per_game'] ?? 3);
            $default_mode = $_POST['default_mode'] ?? 'blitz';

            // Check if preferences exist
            $check = $pdo->prepare("SELECT id FROM user_chess_preferences WHERE user_id = ?");
            $check->execute([$user_id]);

            if ($check->fetch()) {
                $update = $pdo->prepare("
                    UPDATE user_chess_preferences 
                    SET theme_id = ?, sound_enabled = ?, animations_enabled = ?, 
                        hints_per_game = ?, default_game_mode = ?
                    WHERE user_id = ?
                ");
                $update->execute([$theme_id, (int)$sound_enabled, (int)$animations_enabled, $hints_per_game, $default_mode, $user_id]);
            } else {
                $insert = $pdo->prepare("
                    INSERT INTO user_chess_preferences 
                    (user_id, theme_id, sound_enabled, animations_enabled, hints_per_game, default_game_mode)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $insert->execute([$user_id, $theme_id, (int)$sound_enabled, (int)$animations_enabled, $hints_per_game, $default_mode]);
            }

            echo json_encode(['success' => true]);
            break;

        // ============= GET PREFERENCES =============
        case 'get_preferences':
            $prefs = $pdo->prepare("SELECT * FROM user_chess_preferences WHERE user_id = ?");
            $prefs->execute([$user_id]);
            $pref = $prefs->fetch();

            if (!$pref) {
                // Create default preferences
                $insert = $pdo->prepare("INSERT INTO user_chess_preferences (user_id) VALUES (?)");
                $insert->execute([$user_id]);
                $prefs->execute([$user_id]);
                $pref = $prefs->fetch();
            }

            echo json_encode($pref);
            break;

        // ============= MATCH HISTORY =============
        case 'get_match_history':
            $limit = (int)($_GET['limit'] ?? 20);
            $page = (int)($_GET['page'] ?? 0);

            $history = $pdo->prepare("
                SELECT * FROM chess_match_history 
                WHERE white_player_id = ? OR black_player_id = ?
                ORDER BY played_at DESC
                LIMIT ? OFFSET ?
            ");
            $history->bindValue(1, $user_id, PDO::PARAM_INT);
            $history->bindValue(2, $user_id, PDO::PARAM_INT);
            $history->bindValue(3, $limit, PDO::PARAM_INT);
            $history->bindValue(4, $page * $limit, PDO::PARAM_INT);
            $history->execute();

            echo json_encode($history->fetchAll());
            break;

        // ============= ACHIEVEMENTS =============
        case 'get_achievements':
            $target_id = (int)($_GET['user_id'] ?? $user_id);

            $achievements = $pdo->prepare("
                SELECT ca.* FROM chess_achievements ca
                JOIN user_achievements ua ON ca.id = ua.achievement_id
                WHERE ua.user_id = ?
            ");
            $achievements->execute([$target_id]);

            echo json_encode($achievements->fetchAll());
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

// Helper functions
function calculateAccuracy($moves) {
    if (empty($moves)) return 100;
    
    $total_loss = array_sum(array_column($moves, 'centipawn_loss'));
    $move_count = count($moves);
    
    return max(0, min(100, 100 - ($total_loss / ($move_count * 100))));
}

function updateLeaderboard($pdo, $mode) {
    $pdo->query("
        DELETE FROM chess_leaderboards WHERE mode = '$mode'
    ");

    $pdo->query("
        INSERT INTO chess_leaderboards (user_id, mode, rating, games_played)
        SELECT 
            user_id,
            '$mode',
            {$mode}_rating,
            {$mode}_games
        FROM chess_ratings
        WHERE {$mode}_games > 0
    ");
}
?>
