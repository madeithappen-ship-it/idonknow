<?php
require_once(__DIR__ . '/config.php');

echo "<h1>Database Setup</h1>";

try {
    // 1. Run schema migrations
    $sql = file_get_contents(__DIR__ . '/schema.sql');
    
    // PDO::exec executes multiple statements if supported, but to be safe we can run it block by block
    // Or just rely on PDO's ability to run schema script
    $pdo->exec($sql);
    echo "<p>✅ Database tables created successfully.</p>";

    // 2. Insert admin credentials
    $hash = password_hash('Casanova123', PASSWORD_DEFAULT);
    $pdo->exec("INSERT IGNORE INTO admin_users (username, email, password, role, is_active) VALUES ('admin', 'admin@sidequest.local', '$hash', 'super_admin', 1) ON DUPLICATE KEY UPDATE password='$hash'");
    $pdo->exec("INSERT IGNORE INTO users (username, email, password, display_name, status) VALUES ('admin', 'admin@sidequest.local', '$hash', 'Super Admin', 'active') ON DUPLICATE KEY UPDATE password='$hash'");
    echo "<p>✅ Admin user 'admin' (password: Casanova123) verified.</p>";

    // 3. Start quest generation in background
    exec("php " . escapeshellarg(__DIR__ . "/generate_quests.php") . " > /dev/null 2>&1 &");
    echo "<p>✅ Generating 10,000 quests in the background... (This will take a few moments)</p>";

    echo "<hr><h3>Setup Complete!</h3>";
    echo "<p><a href='/login.php'>Click here to go to the Login Page</a></p>";

} catch (PDOException $e) {
    echo "<p>❌ Database Error: " . htmlspecialchars($e->getMessage()) . "</p>";
} catch (Exception $e) {
    echo "<p>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
