<?php
$db_user = "root";
$db_user_password = getenv('MYSQL_ROOT_PASSWORD');
$db_name = getenv('MYSQL_DATABASE');
$db_server = "mysql_main";
$db_server_port = 3306;

try {
    // Connect without specifying dbname to create database if missing
    $pdo = new PDO("mysql:host=$db_server;port=$db_server_port;", $db_user, $db_user_password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");

    // Reconnect with dbname now that DB exists
    $pdo = new PDO("mysql:host=$db_server;port=$db_server_port;dbname=$db_name;", $db_user, $db_user_password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    $createTableSQL = "
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($createTableSQL);

} catch (PDOException $e) {
    die("Database setup error: " . $e->getMessage());
}
?>
