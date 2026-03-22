<?php
require_once(__DIR__ . '/config.php');

$queries = [
    "CREATE TABLE IF NOT EXISTS chess_rooms (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_code VARCHAR(10) UNIQUE NOT NULL,
        status ENUM('waiting', 'playing', 'finished', 'abandoned') DEFAULT 'waiting',
        fen VARCHAR(100) DEFAULT 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1',
        turn ENUM('w', 'b') DEFAULT 'w',
        player_w_id INT NULL,
        player_w_name VARCHAR(50) NULL,
        player_b_id INT NULL,
        player_b_name VARCHAR(50) NULL,
        result_reason VARCHAR(50) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS chess_moves (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_code VARCHAR(10) NOT NULL,
        move_from VARCHAR(5) NOT NULL,
        move_to VARCHAR(5) NOT NULL,
        promotion VARCHAR(1) NULL,
        color ENUM('w', 'b') NOT NULL,
        fen_after VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )"
];

foreach ($queries as $sql) {
    try {
        $pdo->exec($sql);
        echo "Successfully executed query.\n";
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
echo "Chess database setup complete.\n";
