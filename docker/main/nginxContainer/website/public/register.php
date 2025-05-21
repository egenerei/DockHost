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
    image: httpd:latest
    restart: always
    volumes:
      - website:/usr/local/apache2/
      - ./httpd.conf:/usr/local/apache2/conf/httpd.conf
    command: >
      sh -c "
        htpasswd -cbB /usr/local/apache2/conf/.htpasswd {$safeUsername} {$_POST['password']} &&
        httpd-foreground
      "
    networks:
      {$safeUsername}_intranet:
      main_client_intranet:
        aliases:
          - {$safeUsername}.egenerei.es

  filemanager:
    image: filebrowser/filebrowser
    container_name: files{$safeUsername}
    restart: always
    volumes:
      - website:/srv
      - ./.filebrowser.json:/.filebrowser.json
    networks:
      {$safeUsername}_intranet:

networks:
  main_client_intranet:
    external: true
  {$safeUsername}_intranet:
    driver: bridge

volumes:
  website:
    labels:
      - tenant={$safeUsername}
YAML;

    $httpdConfContent = <<<HTTPD
ServerRoot "/usr/local/apache2"
ServerName {$safeUsername}.egenerei.es:80
Listen 80
LoadModule mpm_event_module modules/mod_mpm_event.so
LoadModule authn_file_module modules/mod_authn_file.so
LoadModule authn_core_module modules/mod_authn_core.so
LoadModule authz_host_module modules/mod_authz_host.so
LoadModule authz_groupfile_module modules/mod_authz_groupfile.so
LoadModule authz_user_module modules/mod_authz_user.so
LoadModule authz_core_module modules/mod_authz_core.so
LoadModule access_compat_module modules/mod_access_compat.so
LoadModule auth_basic_module modules/mod_auth_basic.so
LoadModule reqtimeout_module modules/mod_reqtimeout.so
LoadModule filter_module modules/mod_filter.so
LoadModule mime_module modules/mod_mime.so
LoadModule log_config_module modules/mod_log_config.so
LoadModule env_module modules/mod_env.so
LoadModule headers_module modules/mod_headers.so
LoadModule setenvif_module modules/mod_setenvif.so
LoadModule version_module modules/mod_version.so
LoadModule unixd_module modules/mod_unixd.so
LoadModule status_module modules/mod_status.so
LoadModule autoindex_module modules/mod_autoindex.so
LoadModule dir_module modules/mod_dir.so
LoadModule alias_module modules/mod_alias.so
LoadModule rewrite_module modules/mod_rewrite.so
LoadModule proxy_module modules/mod_proxy.so
LoadModule proxy_http_module modules/mod_proxy_http.so
User www-data
Group www-data
ServerAdmin you@example.com
<Directory />
    AllowOverride All
    Require all granted
</Directory>
DocumentRoot "/usr/local/apache2/htdocs"
<Directory "/usr/local/apache2/htdocs">
    Options -Indexes +FollowSymLinks
    Require all granted
</Directory>
DirectoryIndex index.html
<Files ".ht*">
    Require all denied
</Files>
ErrorLog /proc/self/fd/2
LogLevel warn
LogFormat "%h %l %u %t \"%r\" %>s %b \"%{Referer}i\" \"%{User-Agent}i\"" combined
LogFormat "%h %l %u %t \"%r\" %>s %b" common
CustomLog /proc/self/fd/1 common
<IfModule logio_module>$safeUsername
    LogFormat "%h %l %u %t \"%r\" %>s %b \"%{Referer}i\" \"%{User-Agent}i\" %I %O" combinedio
</IfModule>
ScriptAlias /cgi-bin/ "/usr/local/apache2/cgi-bin/"
<Directory "/usr/local/apache2/cgi-bin">
    AllowOverride None
    Require all granted
</Directory>
RequestHeader unset Proxy early
TypesConfig conf/mime.types
AddType application/x-compress .Z
AddType application/x-gzip .gz .tgz
<IfModule proxy_html_module>
    Include conf/extra/proxy-html.conf
</IfModule>
<VirtualHost *:80>
    ServerName {$safeUsername}.egenerei.es

    ProxyPreserveHost On
    ProxyRequests Off

    # Reverse proxy for /admin
    <Location "/admin/">
        AuthType Basic
        AuthName "Restricted Admin Area"
        AuthUserFile "/usr/local/apache2/conf/.htpasswd"
        Require valid-user

        ProxyPass "http://files{$safeUsername}:80/admin/"
        ProxyPassReverse "http://files{$safeUsername}:80/admin/"
        ProxyPassReverseCookiePath /admin /admin/
    </Location>

    ErrorLog /proc/self/fd/2
    CustomLog /proc/self/fd/1 combined
</VirtualHost>


HTTPD;

    $fileBrowserContent = <<<JSON
{
  "port": 80,
  "baseURL": "/admin",
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
        die("Failed to write to $confPath");
    }

    log_info("Wrote config files for " . htmlspecialchars($safeUsername, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));

    echo '<!DOCTYPE html>
          <html>
              <head>
                <link rel="stylesheet" href="../css/style.css">
              </head>
            <body>
              <a href="http://'.$safeUsername.'.egenerei.es/admin" class="button">Administration</a>
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
