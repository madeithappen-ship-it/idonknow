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
    // Auto-cleanup old rooms occasionally (basic garbage collection for demo)
    if (rand(1, 100) <= 5) {
        $pdo->exec("DELETE FROM chess_rooms WHERE created_at < NOW() - INTERVAL 2 HOUR");
        $pdo->exec("DELETE FROM chess_moves WHERE created_at < NOW() - INTERVAL 2 HOUR");
    }

    if ($action === 'create_room') {
        $username = $input['username'] ?? get_user()['username'];
        $target = $input['target_opponent'] ?? null;
        $roomCode = strtoupper(substr(md5(uniqid()), 0, 6)); // 6 letter code
        
        $stmt = $pdo->prepare("INSERT INTO chess_rooms (room_code, player_w_name, player_b_name, status) VALUES (?, ?, ?, 'waiting')");
        $stmt->execute([$roomCode, $username, $target]);
        
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
        
        $stmt = $pdo->prepare("SELECT * FROM chess_rooms WHERE room_code = ?");
        $stmt->execute([$roomCode]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$room) {
            echo json_encode(['success' => false, 'error' => 'Room not found']);
            exit;
        }
        
        $stmt = $pdo->prepare("SELECT * FROM chess_moves WHERE room_code = ? AND id > ? ORDER BY id ASC");
        $stmt->execute([$roomCode, $sinceId]);
        $moves = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'room' => $room,
            'moves' => $moves
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
        // Fetch all recent/active users except the current one
        $stmt = $pdo->prepare("SELECT username, display_name, avatar_url, last_seen, xp FROM users WHERE username != ? ORDER BY last_seen DESC LIMIT 50");
        $stmt->execute([get_user()['username']]);
        $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    
    else {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
