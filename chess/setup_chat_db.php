<?php
/**
 * Setup Live Chat Database
 * Creates tables for player-to-player messaging
 */

require_once(__DIR__ . '/../config.php');

$queries = [
    // Create direct messages table
    "CREATE TABLE IF NOT EXISTS direct_messages (
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
    )",
    
    // Create public chat (room/global chat)
    "CREATE TABLE IF NOT EXISTS public_chat (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        username VARCHAR(50) NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_created (created_at),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
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
            echo "✓ Table/Column already exists\n";
        }
    }
}

echo "\n✓ Live Chat Database setup complete!\n";
?>
