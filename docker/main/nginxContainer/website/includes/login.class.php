<?php
require_once 'db.php';
class user_login {

    protected string $username;
    protected string $password;

    public function __construct( string $username, string $password) {
        #Set properties
        $this->username = $username;
        $this->password = $password;
        $this->authenticate();
    }
    protected function authenticate(): bool {
        $stmt = get_pdo()->prepare("SELECT password FROM users WHERE username = :username");
        $stmt->execute(['username' => $this->username]);
        $stored_hash = $stmt->fetchColumn();
        if ($stored_hash === false) {
            throw new RuntimeException("Invalid credentials.");
        }
        return password_verify($this->password, $stored_hash);
    }

    public function get_username(): string {
        return $this->username;
    }
    public function get_subdomain(): string {
        $stmt = get_pdo()->prepare("SELECT subdomain, password FROM users WHERE username = :username");
        $stmt->execute([':username' => $this->username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || !password_verify($this->password, $row['password'])) {
            throw new RuntimeException("Invalid credentials.");
        }
        return $row['subdomain'];
    }

}
