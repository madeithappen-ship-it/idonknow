<?php
/**
 * Setup Live Chess Broadcasting System
 * Extends chess_rooms table to support public games and spectators
 */

require_once(__DIR__ . '/../config.php');

$queries = [
    // Add columns to chess_rooms if they don't exist
    "ALTER TABLE chess_rooms ADD COLUMN IF NOT EXISTS is_public TINYINT DEFAULT 1",
    "ALTER TABLE chess_rooms ADD COLUMN IF NOT EXISTS spectator_count INT DEFAULT 0",
    "ALTER TABLE chess_rooms ADD COLUMN IF NOT EXISTS ai_analysis JSON DEFAULT NULL",
    
    // Create spectators tracking table
    "CREATE TABLE IF NOT EXISTS chess_spectators (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_code VARCHAR(10) NOT NULL,
        username VARCHAR(50) NOT NULL,
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_spectator (room_code, username),
        FOREIGN KEY (room_code) REFERENCES chess_rooms(room_code) ON DELETE CASCADE
    )",
    
    // Create game analysis log
    "CREATE TABLE IF NOT EXISTS chess_analysis (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_code VARCHAR(10) NOT NULL,
        move_id INT NULL,
        evaluation DECIMAL(5, 2) NULL,
        best_move VARCHAR(10) NULL,
        depth INT NULL,
        analysis_json JSON NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (room_code) REFERENCES chess_rooms(room_code) ON DELETE CASCADE,
        INDEX idx_room_code (room_code)
    )"
];

foreach ($queries as $sql) {
    try {
        $pdo->exec($sql);
        echo "✓ Query executed successfully\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') === false) {
            echo "✗ Error: " . $e->getMessage() . "\n";
        } else {
            echo "✓ Column/Table already exists\n";
        }
    }
}

echo "\n✓ Live Chess Broadcasting System setup complete!\n";
echo "You can now watch live games and see AI analysis in real-time.\n";
