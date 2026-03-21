<?php
require_once('config.php');

echo "Testing database connection...\n";
echo "Host: " . getenv('DB_HOST') . "\n";
echo "Port: " . getenv('DB_PORT') . "\n";
echo "User: " . getenv('DB_USER') . "\n";
echo "Database: " . getenv('DB_NAME') . "\n";

try {
    $result = $pdo->query('SELECT 1');
    echo "SUCCESS! Database is connected.\n";
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
}
