<?php
require_once 'db.php';
require_once 'user_account.class.php';

class user_website_setup extends user_account {
    private string $raw_subdomain;
    private string $safe_subdomain;
    private string $db_password;
    private string $confirm_db_password;
    private string $domain;
    private string $user_dir;
    private string $log_file = '../logs/dockhost_register.log';

    public function __construct(user_account $account, string $subdomain, string $db_password, string $confirm_db_password, string $domain) {
        parent::__construct( $account->getUsername(), $account->getEmail(), $account->password, $account->confirm_password);

        $this->raw_subdomain = strtolower(trim($subdomain));
        if (!preg_match('/^(?!-)[a-z0-9-]{1,63}(?<!-)$/', $this->raw_subdomain)) {
            $this->log("Invalid subdomain format: '{$this->raw_subdomain}'");
            throw new Exception("Invalid subdomain. Must follow RFC-1123!");
        }
        if ($this->subdomain_exists()) {
            $this->log("Subdomain already in use: {$this->raw_subdomain}");
            throw new Exception("Subdomain already exists!");
        }
        $this->safe_subdomain = $this->raw_subdomain;

        $this->db_password = $db_password;
        $this->confirm_db_password = $confirm_db_password;

        $this->validate_db_password();

        $this->domain = $domain;
        $this->user_dir = '/clients/' . $this->safe_subdomain;
    }

    private function subdomain_exists(): bool {
        $stmt = get_pdo()->prepare("SELECT 1 FROM users WHERE subdomain = :subdomain LIMIT 1");
        $stmt->execute([':subdomain' => $this->raw_subdomain]);
        return (bool) $stmt->fetchColumn();
    }

    private function validate_db_password(): void {
        if ($this->db_password !== $this->confirm_db_password) {
            throw new Exception("Database passwords do not match!");
        }

        if (strlen($this->db_password) < 12 || strlen($this->db_password) > 128) {
            throw new Exception("DB password must be between 12–128 characters!");
        }

        if (!preg_match('/(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_])/', $this->db_password)) {
            throw new Exception("DB password must include upper/lowercase, number, special character!");
        }
    }

    public function register(): void {
        $this->store_in_database();
        $this->create_user_directory();
        $this->generate_user_files();
    }

    private function store_in_database(): void {
        try {
            $stmt = get_pdo()->prepare("INSERT INTO users (username,email,subdomain, password) VALUES (?, ?, ?, ?)");
            $stmt->execute([$this->raw_username,$this->email, $this->safe_subdomain, $this->hash]);
            $this->log("User registered: {$this->raw_username}");
        } catch (PDOException $e) {
            $this->log("DB Error for {$this->raw_username}: {$e->getMessage()}");
            throw new Exception("Database error: {$e->getMessage()}");
        }
    }

    private function create_user_directory(): void {
        if (!is_dir($this->user_dir) && !mkdir($this->user_dir, 0755, true)) {
            $this->log("Failed to create directory: {$this->user_dir}");
            throw new Exception("Failed to create directory. Check permissions.");
        }

        if (!is_writable($this->user_dir)) {
            $this->log("Directory not writable: {$this->user_dir}");
            throw new Exception("Directory exists but is not writable.");
        }

        $this->log("User directory ready: {$this->user_dir}");
    }

    private function generate_user_files(): void {
        $this->write_file("dockerfile", $this->generate_dockerfile());
        $this->write_file("compose.yaml", $this->generate_compose());
        $this->write_file("httpd.conf", $this->generate_httpdconf());
        $this->write_file(".filebrowser.json", $this->generate_filebrowser_config());
    }

    private function write_file(string $filename, string $content): void {
        $path = "{$this->user_dir}/{$filename}";
        if (file_put_contents($path, $content) === false) {
            $this->log("Failed to write $filename for {$this->safe_username}");
            throw new Exception("Failed to write file: $filename");
        }
    }

    private function generate_compose(): string {
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
          - {$this->safe_subdomain}.{$this->domain}
  filebrowser:
    image: filebrowser/filebrowser
    container_name: files{$this->safe_subdomain}
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
    container_name: mariadb{$this->safe_subdomain}
    restart: always
    environment:
      - MARIADB_ROOT_PASSWORD={$this->db_password}
    volumes:
      - db:/var/lib/mysql
    networks:
      intranet:
  phpmyadmin:
    image: phpmyadmin
    container_name: phpmyadmin{$this->safe_subdomain}
    restart: always
    depends_on:
      - mariadb
    environment:
      - PMA_HOST=mariadb{$this->safe_subdomain}
      - PMA_ABSOLUTE_URI=https://{$this->safe_subdomain}.{$this->domain}/admin/phpmyadmin/
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

    private function generate_httpdconf(): string {
        return <<<HTTPD
<VirtualHost *:80>
    ServerName {$this->safe_subdomain}.{$this->domain}
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
    ProxyPass /admin/phpmyadmin/ http://phpmyadmin{$this->safe_subdomain}:80/
    ProxyPassReverse /admin/phpmyadmin/ http://phpmyadmin{$this->safe_subdomain}:80/
    ProxyPassReverseCookiePath /admin/phpmyadmin /admin/phpmyadmin
    ProxyPass /admin/files/ http://files{$this->safe_subdomain}:80/admin/files/
    ProxyPassReverse /admin/files/ http://files{$this->safe_subdomain}:80/admin/files/
    ProxyPassReverseCookiePath /admin/files /admin/files
    ErrorLog /proc/self/fd/2
    CustomLog /proc/self/fd/1 combined
</VirtualHost>
HTTPD;
    }

    private function generate_filebrowser_config(): string {
        return json_encode([
            "port" => 80,
            "baseURL" => "/admin/files",
            "address" => "",
            "log" => "stdout",
            "database" => "/database.db",
            "root" => "/srv",
            "username" => "admin",
            "password" => "admin",
        ], JSON_PRETTY_PRINT);
    }

    private function generate_dockerfile(): string {
        return <<<DOCKER
FROM php:8.2-apache
RUN a2enmod proxy proxy_http rewrite headers && \\
    docker-php-ext-install mysqli pdo pdo_mysql && \\
    cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini
RUN ["htpasswd", "-cbB", "/etc/apache2/.htpasswd", "{$this->raw_username}", "{$this->password}"]
CMD ["apache2-foreground"]
DOCKER;
    }

    private function log(string $msg): void {
        $timestamp = date("Y-m-d H:i:s");
        $safeMsg = htmlspecialchars($msg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        file_put_contents($this->log_file, "[$timestamp] $safeMsg\n", FILE_APPEND);
    }
}

