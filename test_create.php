<?php
require_once(__DIR__ . '/config.php');

try {
    $username = 'testuser';
    $roomCode = strtoupper(substr(md5(uniqid()), 0, 6)); // 6 letter code
    
    $stmt = $pdo->prepare("INSERT INTO chess_rooms (room_code, player_w_name, status) VALUES (?, ?, 'waiting')");
    $stmt->execute([$roomCode, $username]);
    
    echo "Success! Room created: $roomCode\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
