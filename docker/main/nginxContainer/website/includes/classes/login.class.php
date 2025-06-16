<?php
require_once __DIR__ . '/../db/db.php';
class user_login {

    protected string $username;
    protected string $password;

    public function __construct( string $username, string $password) {
        #Set properties
        $this->username = $username;
        $this->password = $password;
        $this->authenticate();
    }
    public function authenticate(): bool {
        $stmt = get_pdo()->prepare("SELECT password FROM users WHERE username = :username");
        $stmt->execute(['username' => $this->username]);
        $stored_hash = $stmt->fetchColumn();
        if (!password_verify($this->password, $stored_hash)){
            throw new Exception("Wrong credentials");
        }
        return true;
    }

    public function get_username(): string {
        return $this->username;
    }
    public function get_subdomain(): string {
        $subdomain = '';
        if ($this->authenticate()){
            $stmt = get_pdo()->prepare("SELECT subdomain FROM users WHERE username = :username");
            $stmt->execute([':username' => $this->username]);
            $subdomain = $stmt->fetchColumn();
        }
        return $subdomain;
    }

}
