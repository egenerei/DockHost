<?php

require_once '../includes/db.php';

class UserRegistration {
    private string $rawUsername;
    private string $safeUsername;
    private string $password;
    private string $confirm_password;
    private string $hash;
    private string $userDir;
    private string $domain;
    private string $logFile = '../logs/dockhost_register.log';

    public function __construct(string $username, string $password, string $confirm_password, string $domain) {
        $this->rawUsername = strtolower(trim($username));
        $this->safeUsername = preg_replace('/[^a-z0-9_-]/', '', $this->rawUsername);

        // Reject if sanitization changes the original username
        if ($this->rawUsername !== $this->safeUsername) {
            $this->log("Sanitized username mismatch: raw='{$this->rawUsername}', safe='{$this->safeUsername}'");
            throw new Exception("Username contains invalid characters. Only lowercase letters, digits, hyphens, and underscores are allowed.");
        }

        if (empty($this->safeUsername)) {
            $this->log("Invalid sanitized username: '{$this->rawUsername}'");
            throw new Exception("Sanitized username is empty or invalid.");
        }

        $this->password = $password;
        $this->confirm_password = $confirm_password;
        $minLength = 12;
        $maxLength = 128;

        if ($this->password != $this->confirm_password) {
           throw new Exception("Passwords don't coincide");
        }

        if (strlen($password) < $minLength || strlen($password) > $maxLength) {
            throw new Exception("Password must be between $minLength and $maxLength characters.");
        }

        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_])/', $password)) {
            throw new Exception("Password must include at least one uppercase letter, one lowercase letter, one number, and one special character.");
        }
        $this->hash = password_hash($password, PASSWORD_BCRYPT);
        $this->domain = $domain;
        $this->userDir = '/clients/' . $this->safeUsername;
    }


    public function register(PDO $pdo): void {
        $this->storeInDatabase($pdo);
        $this->createUserDirectory();
        $this->generateUserFiles();
    }

    private function storeInDatabase(PDO $pdo): void {
        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
            $stmt->execute([$this->rawUsername, $this->hash]);
            $this->log("User registered: {$this->rawUsername}");
        } catch (PDOException $e) {
            $this->log("DB Error for {$this->rawUsername}: {$e->getMessage()}");
            throw new Exception("Database error: {$e->getMessage()}");
        }
    }

    private function createUserDirectory(): void {
        if (!is_dir($this->userDir) && !mkdir($this->userDir, 0755, true)) {
            $this->log("Failed to create directory: {$this->userDir}");
            throw new Exception("Failed to create directory. Check permissions.");
        }

        if (!is_writable($this->userDir)) {
            $this->log("Directory not writable: {$this->userDir}");
            throw new Exception("Directory exists but is not writable.");
        }

        $this->log("User directory ready: {$this->userDir}");
    }

    private function generateUserFiles(): void {
        $this->writeFile("dockerfile", $this->generateDockerfile());
        $this->writeFile("compose.yaml", $this->generateComposeYaml());
        $this->writeFile("httpd.conf", $this->generateHttpdConf());
        $this->writeFile(".filebrowser.json", $this->generateFileBrowserConfig());
    }

    private function writeFile(string $filename, string $content): void {
        $path = "{$this->userDir}/{$filename}";
        if (file_put_contents($path, $content) === false) {
            $this->log("Failed to write $filename for {$this->safeUsername}");
            throw new Exception("Failed to write file: $filename");
        }
    }

    private function generateComposeYaml(): string {
        return <<<YAML
services:
  apache:
    build: .
    restart: always
    volumes:
      - ./website:/var/www/html
      - ./admin:/var/www/admin:ro
      - ./httpd.conf:/etc/apache2/sites-available/000-default.conf:ro
    networks:
      intranet:
      main_client_intranet:
        aliases:
          - {$this->safeUsername}.{$this->domain}
  filebrowser:
    image: filebrowser/filebrowser
    container_name: files{$this->safeUsername}
    restart: always
    volumes:
      - ./website:/srv/website
      - ./.filebrowser.json:/.filebrowser.json:ro
      - db:/srv/database:ro
    networks:
      intranet:
    entrypoint: ["./filebrowser", "--noauth"]
  mariadb:
    image: mariadb:11.8.1-ubi9-rc
    container_name: mariadb{$this->safeUsername}
    restart: always
    environment:
      - MARIADB_ROOT_PASSWORD={$this->password}
    volumes:
      - db:/var/lib/mysql
    networks:
      intranet:
  phpmyadmin:
    image: phpmyadmin
    container_name: phpmyadmin{$this->safeUsername}
    restart: always
    depends_on:
      - mariadb
    environment:
      - PMA_HOST=mariadb{$this->safeUsername}
      - PMA_ABSOLUTE_URI=https://{$this->safeUsername}.{$this->domain}/admin/phpmyadmin/
    networks:
      intranet:
networks:
  main_client_intranet:
    external: true
  intranet:
    driver: bridge
volumes:
  db:
YAML;
    }

    private function generateHttpdConf(): string {
        return <<<HTTPD
<VirtualHost *:80>
    ServerName {$this->safeUsername}.{$this->domain}
    RedirectMatch 301 ^/admin$ /admin/
    DocumentRoot /var/www/html
    <Directory /var/www/html>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    Alias /admin/ /var/www/admin/
    <Directory /var/www/admin>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    <Location "/admin/">
        AuthType Basic
        AuthName "Restricted Admin Area"
        AuthUserFile "/etc/apache2/.htpasswd"
        Require valid-user
    </Location>
    <Directory "/var/www/admin">
        <Files ".ht*">
            Require all denied
        </Files>
    </Directory>
    RedirectMatch 301 ^/admin/phpmyadmin$ /admin/phpmyadmin/
    RedirectMatch 301 ^/admin/files$ /admin/files/
    ProxyPreserveHost On
    ProxyPass /admin/phpmyadmin/ http://phpmyadmin{$this->safeUsername}:80/
    ProxyPassReverse /admin/phpmyadmin/ http://phpmyadmin{$this->safeUsername}:80/
    ProxyPassReverseCookiePath /admin/phpmyadmin /admin/phpmyadmin
    ProxyPass /admin/files/ http://files{$this->safeUsername}:80/admin/files/
    ProxyPassReverse /admin/files/ http://files{$this->safeUsername}:80/admin/files/
    ProxyPassReverseCookiePath /admin/files /admin/files
    ErrorLog /proc/self/fd/2
    CustomLog /proc/self/fd/1 combined
</VirtualHost>
HTTPD;
    }

    private function generateFileBrowserConfig(): string {
        return json_encode([
            "port" => 80,
            "baseURL" => "/admin/files",
            "address" => "",
            "log" => "stdout",
            "database" => "/database.db",
            "root" => "/srv",
            "username" => $this->safeUsername,
            "password" => $this->hash
        ], JSON_PRETTY_PRINT);
    }

    private function generateDockerfile(): string {
        return <<<DOCKER
FROM php:8.2-apache
RUN a2enmod proxy proxy_http rewrite headers && \\
    docker-php-ext-install mysqli pdo pdo_mysql && \\
    cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini
RUN ["htpasswd", "-cbB", "/etc/apache2/.htpasswd", "{$this->safeUsername}", "{$this->password}"]
CMD ["apache2-foreground"]
DOCKER;
    }

    private function log(string $msg): void {
        $timestamp = date("Y-m-d H:i:s");
        $safeMsg = htmlspecialchars($msg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        file_put_contents($this->logFile, "[$timestamp] $safeMsg\n", FILE_APPEND);
    }
}
