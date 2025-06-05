<?php
// Resolve DB file path from environment or default
$db_file = getenv('SQLITE_DB_FILE') ?: '/db/clients.sqlite';
$domain = getenv('DOMAIN');
try {
    // Connect to SQLite
    $pdo = new PDO("sqlite:$db_file", null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    $createTableSQL = "
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        email TEXT NOT NULL UNIQUE,
        subdomain NOT NULL UNIQUE,
        password TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    ";
    $pdo->exec($createTableSQL);
} catch (Throwable $e) {
    die("Database setup error: " . $e->getMessage());
}