<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
# --- Logging setup ---
$logFile = '../logs/dockhost_register.log'; # Ensure writable
function log_info($msg) {
    global $logFile;
    $timestamp = date("Y-m-d H:i:s");
    # Sanitize for logs in case they get viewed in a browser
    $safeMsg = htmlspecialchars($msg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    file_put_contents($logFile, "[$timestamp] $safeMsg\n", FILE_APPEND);
}
require_once '../includes/db.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    # Check input presence
    if (empty($_POST['username']) || empty($_POST['password'])) {
        log_info("Missing username or password");
        die("Username or password not provided.");
    }
    # Sanitize and normalize username
    $rawUsername = strtolower(trim($_POST['username']));
    $safeUsername = preg_replace('/[^a-z0-9_-]/', '', $rawUsername);
    if (empty($safeUsername)) {
        log_info("Sanitized username is invalid or empty: '" . htmlspecialchars($rawUsername, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "'");
        die("Sanitized username is empty or invalid.");
    }
    # Hashing password securely
    $hash = password_hash($_POST['password'], PASSWORD_BCRYPT);
    # Store in DB
    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
        $stmt->execute([$rawUsername, $hash]);
        log_info("User registered: " . htmlspecialchars($rawUsername, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    } catch (PDOException $e) {
        log_info("DB Error for " . htmlspecialchars($rawUsername, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ": " . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        die("Database error: " . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }
    # Setup path for user
    $basePath = '/clients';
    $userDir = $basePath . '/' . $safeUsername;
    # User Directory creation
    if (!is_dir($userDir)) {
        if (!mkdir($userDir, 0755, true)) {
            log_info("Failed to create directory: " . htmlspecialchars($userDir, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
            die("Failed to create directory. Check permissions on $basePath.");
        } else {
            log_info("Created directory: " . htmlspecialchars($userDir, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        }
    } else {
        log_info("Directory already exists: " . htmlspecialchars($userDir, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }

    if (!is_writable($userDir)) {
        log_info("Directory not writable: " . htmlspecialchars($userDir, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        die("Directory exists but is not writable: $userDir");
    }
    $composeYamlContent = <<<YAML
services:
  apache:
    image: php:8.2-apache
    restart: always
    volumes:
      - ./website:/var/www/html
      - ./website/admin:/var/www/html/admin:ro
      - ./httpd.conf:/etc/apache2/sites-available/000-default.conf
    command: >
      sh -c "a2enmod proxy proxy_http rewrite headers && htpasswd -cbB /etc/apache2/.htpasswd {$safeUsername} {$_POST['password']} && apache2-foreground"
    networks:
      {$safeUsername}_intranet:
      main_client_intranet:
        aliases:
          - {$safeUsername}.{$domain}
  filemanager:
    image: filebrowser/filebrowser
    container_name: files{$safeUsername}
    restart: always
    volumes:
      - ./website:/srv
      - ./website/admin:/var/www/html/admin:ro
      - ./.filebrowser.json:/.filebrowser.json
    networks:
      {$safeUsername}_intranet:
    entrypoint: ["./filebrowser", "--noauth"]
  mysql:
    image: mysql:latest
    container_name: mysql{$safeUsername}
    restart: always
    environment:
      - MYSQL_ROOT_PASSWORD={$_POST['password']}
    volumes:
      - db:/var/lib/mysql
    networks:
      {$safeUsername}_intranet:
  phpmyadmin:
    image: phpmyadmin
    container_name: phpmyadmin{$safeUsername}
    restart: always
    depends_on:
      - mysql
    environment:
      - PMA_HOST=mysql{$safeUsername}
      - PMA_ABSOLUTE_URI=https://{$safeUsername}.{$domain}/admin/phpmyadmin/
    networks:
      {$safeUsername}_intranet:
networks:
  main_client_intranet:
    external: true
  {$safeUsername}_intranet:
    driver: bridge
volumes:
  db:
    labels:
      - tenant={$safeUsername}
YAML;
    $httpdConfContent = <<<HTTPD
<VirtualHost *:80>
    ServerName {$safeUsername}.{$domain}
    DocumentRoot /var/www/html
    <Directory /var/www/html>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    <Files ".ht*">
        Require all denied
    </Files>
    <Location "/admin/">
        AuthType Basic
        AuthName "Restricted Admin Area"
        AuthUserFile "/etc/apache2/.htpasswd"
        Require valid-user
    </Location>
    RedirectMatch 301 ^/admin/phpmyadmin$ /admin/phpmyadmin/
    RedirectMatch 301 ^/admin/files$ /admin/files/
    ProxyPreserveHost On
    ProxyPass /admin/phpmyadmin/ http://phpmyadmin{$safeUsername}:80/
    ProxyPassReverse /admin/phpmyadmin/ http://phpmyadmin{$safeUsername}:80/
    ProxyPassReverseCookiePath /admin/phpmyadmin /admin/phpmyadmin
    ProxyPass /admin/files/ http://files{$safeUsername}:80/admin/files/
    ProxyPassReverse /admin/files/ http://files{$safeUsername}:80/admin/files/
    ProxyPassReverseCookiePath /admin/files /admin/files
    ErrorLog /proc/self/fd/2
    CustomLog /proc/self/fd/1 combined
</VirtualHost>

HTTPD;

    $fileBrowserContent = <<<JSON
{
  "port": 80,
  "baseURL": "/admin/files",
  "address": "",
  "log": "stdout",
  "database": "/database.db",
  "root": "/srv",
  "username": "{$safeUsername}",
  "password": "{$hash}"
}
JSON;
    #User files creation & register in log
    $composePath = $userDir . '/compose.yaml';
    $confPath = $userDir . '/httpd.conf';
    $fileBrowserPath = $userDir . '/.filebrowser.json';
    if (file_put_contents($composePath, $composeYamlContent) === false) {
        log_info("Failed to write compose.yaml for " . htmlspecialchars($safeUsername, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        die("Failed to write to $composePath");
    }
    if (file_put_contents($confPath, $httpdConfContent) === false) {
        log_info("Failed to write php.conf for " . htmlspecialchars($safeUsername, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        die("Failed to write to $confPath");
    }
    if (file_put_contents($fileBrowserPath, $fileBrowserContent) === false) {
        log_info("Failed to write .filebrowser.json for " . htmlspecialchars($safeUsername, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        die("Failed to write to $fileBrowserPath");
    }
    log_info("Wrote config files for " . htmlspecialchars($safeUsername, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    echo '<!DOCTYPE html>
          <html>
              <head>
                <link rel="stylesheet" href="../css/style.css">
              </head>
            <body>
              <a href="http://'.$safeUsername.'.'.$domain.'/admin/" class="button">Administration</a>
            </body>
          </html>';
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Register - DockHost</title>
  <link rel="stylesheet" href="../css/style.css">
</head>
<body>
  <form method="post">
    <h2>Register</h2>
    <input type="text" name="username" placeholder="Username" required>
    <input type="password" name="password" placeholder="Password" required>
    <button type="submit">Register</button>
  </form>
</body>
</html>
