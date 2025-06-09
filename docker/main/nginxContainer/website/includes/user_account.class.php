<?php
require_once 'db.php';
class user_account {
    protected string $raw_username;
    protected string $safe_username;
    protected string $email;
    protected string $password;
    protected string $confirm_password;
    protected string $hash;

    public function __construct( string $username, string $email, string $password, string $confirm_password) {
        #Username checks
        $this->raw_username = trim($username);
        if (!preg_match('/^[a-zA-Z0-9_-]{4,32}$/', $this->raw_username)) {
            throw new Exception("Username must contain only letters (A–Z, a–z), digits, hyphens, and underscores, and be 4–32 characters long.");
        }
        if ($this->username_exists()) {
            throw new Exception("Username already registered!");
        }
        $this->safe_username = $this->raw_username;
        
        #Email checks
        $this->email = $email;
        if ($this->email_exists()) {
            throw new Exception("Email already registered!");
        }
        #Password checks
        $this->password = $password;
        $this->confirm_password = $confirm_password;
        $this->validate_password();
        $this->hash = password_hash($this->password, PASSWORD_BCRYPT);
    }

    protected function username_exists(): bool {
        $stmt = get_pdo()->prepare("SELECT 1 FROM users WHERE username = :username LIMIT 1");
        $stmt->execute([':username' => $this->raw_username]);
        return (bool) $stmt->fetchColumn();
    }

    protected function email_exists(): bool {
        $stmt = get_pdo()->prepare("SELECT 1 FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $this->email]);
        return (bool) $stmt->fetchColumn();
    }
    private function validate_password(): void {
        $minLength = 12;
        $maxLength = 128;

        if ($this->password !== $this->confirm_password) {
            throw new Exception("Passwords are not the same!");
        }

        if (strlen($this->password) < $minLength || strlen($this->password) > $maxLength) {
            throw new Exception("Password must be between $minLength and $maxLength characters!");
        }

        if (!preg_match('/(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_])/', $this->password)) {
            throw new Exception("Password must include uppercase, lowercase, number, and a special character!");
        }
    }

    public function get_username(): string {
        return $this->raw_username;
    }

    public function get_email(): string {
        return $this->email;
    }

    public function get_password(): string {
        return $this->password;
    }

}
