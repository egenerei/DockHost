<?php
$db_user = "root";
$db_user_password = getenv('MYSQL_ROOT_PASSWORD');
$db_name = getenv('MYSQL_DATABASE');
$db_server = "mysql_main";
$db_server_port = 3306;

try {
    $pdo = new PDO("mysql:host=$db_server;port=$db_server_port;", $db_user, $db_user_password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$db_name`");
    $createTableSQL = "
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ";
    $pdo->exec($createTableSQL);
} catch (PDOException $e) {
    die("❌ Database setup error: " . $e->getMessage());
}
?>

<!-- Basic HTML for login and signup -->
<!DOCTYPE html>
<html>
<head>
    <title>Login / Signup</title>
    <style>
        form { margin-bottom: 20px; }
    </style>
</head>
<body>
    <h2>Signup</h2>
    <form method="post" action="signup.php">
        <label>Username: <input type="text" name="username" required></label><br>
        <label>Password: <input type="password" name="password" required></label><br>
        <button type="submit">Sign Up</button>
    </form>

    <h2>Login</h2>
    <form method="post" action="login.php">
        <label>Username: <input type="text" name="username" required></label><br>
        <label>Password: <input type="password" name="password" required></label><br>
        <button type="submit">Login</button>
    </form>
</body>
</html>