<?php
// Resolve DB file path and domain from environment
$db_file = getenv('SQLITE_DB_FILE') ?: '/db/clients.sqlite';
$domain = getenv('DOMAIN');

/**
 * Returns a singleton PDO connection to the SQLite database.
 *
 * @return PDO
 */
function get_pdo(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        try {
            global $db_file;

            $pdo = new PDO("sqlite:$db_file", null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            // Create table if it doesn't exist
            $createTableSQL = "
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                email TEXT NOT NULL UNIQUE,
                subdomain TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
            ";

            $pdo->exec($createTableSQL);
        } catch (Throwable $e) {
            die("Database setup error: " . $e->getMessage());
        }
    }

    return $pdo;
}
