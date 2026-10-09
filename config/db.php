<?php
// config/db.php
// Creates the single shared PDO connection.
require_once __DIR__ . '/../classes/Database.php';   // include the Database class

$dsn  = "mysql:host=localhost;dbname=training_db;charset=utf8mb4";
$user = "root";
$pass = "";

// Optional, git-ignored override so real credentials are never committed.
// Copy config/db.local.sample.php to config/db.local.php and edit it.
$localConfig = __DIR__ . '/db.local.php';
if (is_file($localConfig)) {
    require $localConfig;   // may redefine $dsn, $user, $pass
}

try {
    // obtain the single shared PDO connection (Singleton)
    $db = Database::getInstance($dsn, $user, $pass);
} catch (PDOException $e) {
    error_log('DB connect failed: ' . $e->getMessage());
    die('Database unavailable. Check config/db.php and that MySQL is running.');
}
