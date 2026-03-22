<?php
session_start();
require_once(__DIR__ . '/../config.php');

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true);

try {
    // Auto-cleanup old rooms occasionally (runs every ~5000 requests to reduce overhead)
    if (rand(1, 5000) === 1) {
        $pdo->exec("DELETE FROM chess_rooms WHERE created_at < NOW() - INTERVAL 6 HOUR AND status IN ('abandoned', 'finished')");
        $pdo->exec("DELETE FROM chess_moves WHERE room_code NOT IN (SELECT room_code FROM chess_rooms)");
    }

    if ($action === 'create_room') {
        // Get username from payload or from session
        $username = $input['username'] ?? null;
        
        if (!$username) {
            $user = get_user();
            if (!$user || !isset($user['username'])) {
                echo json_encode(['success' => false, 'error' => 'Could not identify user']);
                exit;
            }
            $username = $user['username'];
        }
        
        $target = $input['target_opponent'] ?? null;
        
        if (!$username) {
            echo json_encode(['success' => false, 'error' => 'Username is empty']);
            exit;
        }
        
        $roomCode = strtoupper(substr(md5(uniqid()), 0, 6)); // 6 letter code
        
        // Check if is_live column exists, if not add it
        try {
            $tableInfo = $pdo->query("DESCRIBE chess_rooms")->fetchAll(PDO::FETCH_ASSOC);
            $hasIsLive = false;
            foreach ($tableInfo as $col) {
                if ($col['Field'] === 'is_live') {
                    $hasIsLive = true;
                    break;
                }
            }
            
            if (!$hasIsLive) {
                $pdo->exec("ALTER TABLE chess_rooms ADD COLUMN is_live TINYINT DEFAULT 0");
            }
        } catch (Exception $e) {
            // Column might already exist, continue
        }
        
        // Try to insert into chess_rooms
        try {
            $stmt = $pdo->prepare("INSERT INTO chess_rooms (room_code, player_w_name, player_b_name, status, is_live, created_at) VALUES (?, ?, ?, 'waiting', 0, NOW())");
            $result = $stmt->execute([$roomCode, $username, $target]);
            
            if (!$result) {
                echo json_encode(['success' => false, 'error' => 'Failed to create room']);
                exit;
            }
        } catch (PDOException $e) {
            // Try without is_live column
            try {
                $stmt = $pdo->prepare("INSERT INTO chess_rooms (room_code, player_w_name, player_b_name, status, created_at) VALUES (?, ?, ?, 'waiting', NOW())");
                $result = $stmt->execute([$roomCode, $username, $target]);
                
                if (!$result) {
                    echo json_encode(['success' => false, 'error' => 'Failed to create room']);
                    exit;
                }
            } catch (PDOException $e2) {
                echo json_encode(['success' => false, 'error' => 'Database error: ' . $e2->getMessage()]);
                exit;
            }
        }
        
        header('Cache-Control: no-cache');
        echo json_encode(['success' => true, 'room_code' => $roomCode, 'color' => 'w']);
    }
    
    elseif ($action === 'join_room') {
        $roomCode = strtoupper($input['room_code'] ?? '');
        $username = $input['username'] ?? get_user()['username'];
        
        $stmt = $pdo->prepare("SELECT * FROM chess_rooms WHERE room_code = ?");
        $stmt->execute([$roomCode]);
        $room = $stmt->fetch();
        
        if (!$room) {
            echo json_encode(['success' => false, 'error' => 'Room not found']);
            exit;
        }
        
        if ($room['status'] === 'waiting') {
            // Allow joining as black even if playing against yourself for testing purposes!
            $opponent = $room['player_w_name'];
            $actualUsername = ($username === $opponent) ? $username . '_2' : $username;
            $stmt = $pdo->prepare("UPDATE chess_rooms SET player_b_name = ?, status = 'playing' WHERE room_code = ?");
            $stmt->execute([$actualUsername, $roomCode]);
            echo json_encode(['success' => true, 'color' => 'b', 'opponent' => $opponent]);
        } elseif ($room['player_w_name'] === $username) {
            echo json_encode(['success' => true, 'color' => 'w', 'opponent' => $room['player_b_name']]);
        } elseif ($room['player_b_name'] === $username || $room['player_b_name'] === $username . '_2') {
            echo json_encode(['success' => true, 'color' => 'b', 'opponent' => $room['player_w_name']]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Room is full']);
        }
    }
    
    elseif ($action === 'sync') {
        $roomCode = strtoupper($input['room_code'] ?? '');
        $sinceId = intval($input['last_move_id'] ?? 0);
        
        // Fetch only necessary room fields
        $stmt = $pdo->prepare("SELECT room_code, player_w_name, player_b_name, status, fen, result_reason FROM chess_rooms WHERE room_code = ?");
        $stmt->execute([$roomCode]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$room) {
            header('HTTP/1.1 404 Not Found');
            echo json_encode(['success' => false, 'error' => 'Room not found']);
            exit;
        }
        
        // Only fetch move fields we need
        $stmt = $pdo->prepare("SELECT id, color, move_from, move_to FROM chess_moves WHERE room_code = ? AND id > ? ORDER BY id ASC LIMIT 50");
        $stmt->execute([$roomCode, $sinceId]);
        $moves = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        header('Cache-Control: no-cache');
        echo json_encode([
            'success' => true,
            'room' => $room,
            'moves' => $moves,
            'move_count' => count($moves)
        ]);
    }
    
    elseif ($action === 'move') {
        $roomCode = strtoupper($input['room_code'] ?? '');
        $username = $input['username'] ?? get_user()['username'];
        $fen = $input['fen'] ?? '';
        $from = $input['from'] ?? '';
        $to = $input['to'] ?? '';
        $san = $input['san'] ?? '';
        $gameOver = $input['game_over'] ?? false;
        $reason = $input['reason'] ?? '';
        
        // Figure out color based on room
        $stmt = $pdo->prepare("SELECT player_w_name FROM chess_rooms WHERE room_code = ?");
        $stmt->execute([$roomCode]);
        $room = $stmt->fetch();
        $color = ($room && $room['player_w_name'] === $username) ? 'w' : 'b';
        
        // Insert move
        $stmt = $pdo->prepare("INSERT INTO chess_moves (room_code, color, move_from, move_to, fen_after) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$roomCode, $color, $from, $to, $fen]);
        $moveId = $pdo->lastInsertId();
        
        // Update room if game over
        if ($gameOver) {
            $stmt = $pdo->prepare("UPDATE chess_rooms SET status = 'finished', result_reason = ?, fen = ? WHERE room_code = ?");
            $stmt->execute([$reason, $fen, $roomCode]);
        } else {
            $stmt = $pdo->prepare("UPDATE chess_rooms SET fen = ? WHERE room_code = ?");
            $stmt->execute([$fen, $roomCode]);
        }
        
        echo json_encode(['success' => true, 'move_id' => $moveId]);
    }
    
    elseif ($action === 'abandon') {
        $roomCode = strtoupper($input['room_code'] ?? '');
        $stmt = $pdo->prepare("UPDATE chess_rooms SET status = 'abandoned', result_reason = 'Opponent Left' WHERE room_code = ? AND status != 'finished'");
        $stmt->execute([$roomCode]);
        echo json_encode(['success' => true]);
    }
    
    elseif ($action === 'get_players') {
        // Fetch recent/active users except the current one (limit to improve performance)
        $stmt = $pdo->prepare("SELECT username, display_name, avatar_url, xp FROM users WHERE username != ? AND status = 'active' ORDER BY last_seen DESC LIMIT 30");
        $stmt->execute([get_user()['username']]);
        $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
        header('Cache-Control: public, max-age=300');
        echo json_encode(['success' => true, 'players' => $players]);
    }
    
    elseif ($action === 'check_challenges') {
        $username = get_user()['username'];
        // Find rooms where we are player_b_name and status is 'waiting'
        $stmt = $pdo->prepare("SELECT room_code, player_w_name FROM chess_rooms WHERE player_b_name = ? AND status = 'waiting' AND created_at > NOW() - INTERVAL 5 MINUTE");
        $stmt->execute([$username]);
        $challenges = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'challenges' => $challenges]);
    }
    
    elseif ($action === 'decline_challenge') {
        $roomCode = strtoupper($input['room_code'] ?? '');
        $stmt = $pdo->prepare("UPDATE chess_rooms SET status = 'abandoned', result_reason = 'Declined' WHERE room_code = ? AND status = 'waiting'");
        $stmt->execute([$roomCode]);
        echo json_encode(['success' => true]);
    }
    
    elseif ($action === 'get_live_games') {
        // Get all ongoing games (playing status) that are marked as live
        $stmt = $pdo->prepare("
            SELECT 
                room_code,
                player_w_name,
                player_b_name,
                created_at as started_at,
                status,
                fen,
                TIMESTAMPDIFF(SECOND, created_at, NOW()) as elapsed_seconds,
                (SELECT COUNT(*) FROM chess_spectators WHERE room_code = chess_rooms.room_code) as spectator_count
            FROM chess_rooms 
            WHERE status = 'playing'
            AND is_live = 1
            AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ORDER BY created_at DESC
            LIMIT 20
        ");
        $stmt->execute();
        $games = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($games as &$game) {
            // Calculate remaining time (30 minutes = 1800 seconds)
            $game['elapsed_seconds'] = (int)$game['elapsed_seconds'];
            $game['remaining_seconds'] = max(0, 1800 - $game['elapsed_seconds']);
            $game['game_over'] = $game['remaining_seconds'] <= 0;
            $game['spectator_count'] = (int)$game['spectator_count'];
        }
        
        echo json_encode(['success' => true, 'games' => $games]);
    }
    
    elseif ($action === 'join_spectate') {
        $roomCode = strtoupper($input['room_code'] ?? '');
        $username = get_user()['username'];
        
        // Check if room exists and is playing
        $stmt = $pdo->prepare("SELECT room_code, status, fen FROM chess_rooms WHERE room_code = ? AND status = 'playing'");
        $stmt->execute([$roomCode]);
        $room = $stmt->fetch();
        
        if (!$room) {
            echo json_encode(['success' => false, 'error' => 'Game not found or not in progress']);
            exit;
        }
        
        // Add as spectator
        $stmt = $pdo->prepare("INSERT IGNORE INTO chess_spectators (room_code, username) VALUES (?, ?)");
        $stmt->execute([$roomCode, $username]);
        
        // Get full game state for spectator
        $stmt = $pdo->prepare("
            SELECT id, color, move_from, move_to, fen_after FROM chess_moves 
            WHERE room_code = ? ORDER BY id ASC
        ");
        $stmt->execute([$roomCode]);
        $moves = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'room' => $room, 'moves' => $moves]);
    }
    
    elseif ($action === 'watch_live') {
        $roomCode = strtoupper($input['room_code'] ?? '');
        $lastMoveId = intval($input['last_move_id'] ?? 0);
        
        // Verify room exists
        $stmt = $pdo->prepare("SELECT room_code, player_w_name, player_b_name, status, result_reason FROM chess_rooms WHERE room_code = ?");
        $stmt->execute([$roomCode]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$room) {
            echo json_encode(['success' => false, 'error' => 'Room not found']);
            exit;
        }
        
        // Get new moves since last check
        $stmt = $pdo->prepare("
            SELECT id, color, move_from, move_to, fen_after, created_at FROM chess_moves 
            WHERE room_code = ? AND id > ? ORDER BY id ASC LIMIT 50
        ");
        $stmt->execute([$roomCode, $lastMoveId]);
        $newMoves = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get latest analysis
        $stmt = $pdo->prepare("
            SELECT evaluation, best_move, depth, analysis_json FROM chess_analysis
            WHERE room_code = ? ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$roomCode]);
        $analysis = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'room' => $room,
            'moves' => $newMoves,
            'analysis' => $analysis,
            'spectator_count' => (int)($input['show_spectators'] ? 0 : 0) // TODO: implement live count
        ]);
    }
    
    elseif ($action === 'record_analysis') {
        $roomCode = strtoupper($input['room_code'] ?? '');
        $moveId = intval($input['move_id'] ?? 0);
        $evaluation = floatval($input['evaluation'] ?? 0);
        $bestMove = $input['best_move'] ?? null;
        $depth = intval($input['depth'] ?? 0);
        $analysisJson = json_encode($input['analysis'] ?? []);
        
        // Insert analysis record
        $stmt = $pdo->prepare("
            INSERT INTO chess_analysis 
            (room_code, move_id, evaluation, best_move, depth, analysis_json)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$roomCode, $moveId, $evaluation, $bestMove, $depth, $analysisJson]);
        
        echo json_encode(['success' => true, 'analysis_id' => $pdo->lastInsertId()]);
    }
    
    elseif ($action === 'get_game_analysis') {
        $roomCode = strtoupper($input['room_code'] ?? '');
        
        $stmt = $pdo->prepare("
            SELECT 
                move_id,
                evaluation,
                best_move,
                depth,
                analysis_json,
                created_at
            FROM chess_analysis
            WHERE room_code = ?
            ORDER BY created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$roomCode]);
        $analysis = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'analysis' => $analysis]);
    }
    
    elseif ($action === 'toggle_live') {
        $roomCode = strtoupper($input['room_code'] ?? '');
        $isLive = intval($input['is_live'] ?? 0);
        $username = get_user()['username'];
        
        // Get room to check if user is owner
        $stmt = $pdo->prepare("SELECT player_w_name, status FROM chess_rooms WHERE room_code = ?");
        $stmt->execute([$roomCode]);
        $room = $stmt->fetch();
        
        if (!$room) {
            echo json_encode(['success' => false, 'error' => 'Room not found']);
            exit;
        }
        
        if ($room['player_w_name'] !== $username) {
            echo json_encode(['success' => false, 'error' => 'Only room owner can change live status']);
            exit;
        }
        
        // Update is_live status
        $stmt = $pdo->prepare("UPDATE chess_rooms SET is_live = ? WHERE room_code = ?");
        $stmt->execute([$isLive, $roomCode]);
        
        echo json_encode(['success' => true, 'is_live' => $isLive]);
    }
    
    elseif ($action === 'send_chat') {
        $roomCode = strtoupper($input['room_code'] ?? '');
        $message = $input['message'] ?? '';
        $sender = $input['sender'] ?? get_user()['username'];
        
        if (empty($message) || strlen($message) > 500) {
            echo json_encode(['success' => false, 'error' => 'Invalid message']);
            exit;
        }
        
        // Create chat table if it doesn't exist
        $pdo->exec("CREATE TABLE IF NOT EXISTS chess_chat (
            id INT AUTO_INCREMENT PRIMARY KEY,
            room_code VARCHAR(10) NOT NULL,
            sender VARCHAR(50) NOT NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (room_code) REFERENCES chess_rooms(room_code) ON DELETE CASCADE,
            INDEX idx_room (room_code, created_at)
        )");
        
        $stmt = $pdo->prepare("INSERT INTO chess_chat (room_code, sender, message) VALUES (?, ?, ?)");
        $stmt->execute([$roomCode, $sender, $message]);
        
        echo json_encode(['success' => true, 'message_id' => $pdo->lastInsertId()]);
    }
    
    elseif ($action === 'get_chat') {
        $roomCode = strtoupper($input['room_code'] ?? '');
        $sinceId = intval($input['last_id'] ?? 0);
        
        // Fetch chat messages since last_id
        $stmt = $pdo->prepare("
            SELECT 
                id,
                sender,
                message,
                created_at
            FROM chess_chat
            WHERE room_code = ? AND id > ?
            ORDER BY created_at ASC
            LIMIT 50
        ");
        $stmt->execute([$roomCode, $sinceId]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'messages' => $messages]);
    }
    
    elseif ($action === 'send_direct_message') {
        $username = get_user()['username'];
        $toUsername = $input['to_username'] ?? '';
        $message = $input['message'] ?? '';
        
        if (empty($toUsername) || empty($message) || strlen($message) > 500) {
            echo json_encode(['success' => false, 'error' => 'Invalid message']);
            exit;
        }
        
        // Get recipient info
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND status = 'active'");
        $stmt->execute([$toUsername]);
        $recipient = $stmt->fetch();
        
        if (!$recipient) {
            echo json_encode(['success' => false, 'error' => 'User not found']);
            exit;
        }
        
        $user = get_user();
        
        // Create table if doesn't exist
        $pdo->exec("CREATE TABLE IF NOT EXISTS direct_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            from_user_id INT NOT NULL,
            from_username VARCHAR(50) NOT NULL,
            to_user_id INT NOT NULL,
            to_username VARCHAR(50) NOT NULL,
            message TEXT NOT NULL,
            is_read TINYINT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_conversation (from_user_id, to_user_id, created_at),
            KEY idx_to_user (to_user_id, is_read),
            FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE
        )");
        
        // Send message
        $stmt = $pdo->prepare("INSERT INTO direct_messages (from_user_id, from_username, to_user_id, to_username, message) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user['id'], $username, $recipient['id'], $toUsername, $message]);
        
        echo json_encode(['success' => true, 'message_id' => $pdo->lastInsertId()]);
    }
    
    elseif ($action === 'get_direct_messages') {
        $username = get_user()['username'];
        $withUser = $input['with_user'] ?? '';
        $sinceId = intval($input['last_id'] ?? 0);
        
        if (empty($withUser)) {
            echo json_encode(['success' => false, 'error' => 'Invalid request']);
            exit;
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                id,
                from_username,
                to_username,
                message,
                is_read,
                created_at
            FROM direct_messages
            WHERE (
                (from_username = ? AND to_username = ?) OR
                (from_username = ? AND to_username = ?)
            )
            AND id > ?
            ORDER BY created_at ASC
            LIMIT 100
        ");
        $stmt->execute([$username, $withUser, $withUser, $username, $sinceId]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'messages' => $messages]);
    }
    
    elseif ($action === 'get_conversations') {
        $username = get_user()['username'];
        
        // Get list of conversations with last message
        $stmt = $pdo->prepare("
            SELECT 
                CASE 
                    WHEN from_username = ? THEN to_username
                    ELSE from_username
                END as other_user,
                MAX(created_at) as last_message_time,
                MAX(id) as last_message_id,
                (SELECT message FROM direct_messages dm2 
                 WHERE (
                    (dm2.from_username = ? AND dm2.to_username = dm.other_user) OR
                    (dm2.from_username = dm.other_user AND dm2.to_username = ?)
                 )
                 ORDER BY created_at DESC LIMIT 1) as last_message,
                SUM(CASE WHEN to_username = ? AND is_read = 0 THEN 1 ELSE 0 END) as unread_count
            FROM (
                SELECT from_username, to_username, created_at, id, message FROM direct_messages WHERE from_username = ? OR to_username = ?
            ) as dm
            GROUP BY other_user
            ORDER BY last_message_time DESC
            LIMIT 20
        ");
        $stmt->execute([$username, $username, $username, $username, $username, $username]);
        $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'conversations' => $conversations]);
    }
    
    elseif ($action === 'mark_as_read') {
        $username = get_user()['username'];
        $fromUser = $input['from_user'] ?? '';
        
        if (empty($fromUser)) {
            echo json_encode(['success' => false, 'error' => 'Invalid request']);
            exit;
        }
        
        $stmt = $pdo->prepare("UPDATE direct_messages SET is_read = 1 WHERE to_username = ? AND from_username = ? AND is_read = 0");
        $stmt->execute([$username, $fromUser]);
        
        echo json_encode(['success' => true]);
    }
    
    else {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
