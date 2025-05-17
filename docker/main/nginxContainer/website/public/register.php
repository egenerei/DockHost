<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- Logging setup ---
$logFile = '../logs/dockhost_register.log'; // Ensure writable

function log_info($msg) {
    global $logFile;
    $timestamp = date("Y-m-d H:i:s");
    // Sanitize for logs in case they get viewed in a browser
    $safeMsg = htmlspecialchars($msg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    file_put_contents($logFile, "[$timestamp] $safeMsg\n", FILE_APPEND);
}

require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Check input presence
    if (empty($_POST['username']) || empty($_POST['password'])) {
        log_info("Missing username or password");
        die("Username or password not provided.");
    }

    // Sanitize and normalize username
    $rawUsername = strtolower(trim($_POST['username']));
    $safeUsername = preg_replace('/[^a-z0-9_-]/', '', $rawUsername);

    if (empty($safeUsername)) {
        log_info("Sanitized username is invalid or empty: '" . htmlspecialchars($rawUsername, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "'");
        die("Sanitized username is empty or invalid.");
    }

    // Hash password securely
    $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Store in DB
    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
        $stmt->execute([$rawUsername, $hash]);
        log_info("User registered: " . htmlspecialchars($rawUsername, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    } catch (PDOException $e) {
        log_info("DB Error for " . htmlspecialchars($rawUsername, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ": " . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        die("Database error: " . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }

    // Setup paths
    $basePath = '/clients';
    $userDir = $basePath . '/' . $safeUsername;

    // Directory creation
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

    // Compose YAML and NGINX config — these use strictly sanitized $safeUsername, so no htmlspecialchars needed here
    // (since these are not output to HTML but config files)

    $composeYamlContent = <<<YAML
services:
  nginx:
    image: nginx:latest
    restart: always
    volumes:
      - website:/usr/share/nginx/html
      - ./php.conf:/etc/nginx/conf.d/default.conf
    networks:
      main_client_intranet:
        aliases:
          - {$safeUsername}.egenerei.es

  filemanager:
    image: filebrowser/filebrowser
    restart: always
    volumes:
      - website:/srv
    networks:
      main_client_intranet:
        aliases:
          - files{$safeUsername}.egenerei.es

networks:
  main_client_intranet:
    external: true

volumes:
  website:
    labels:
      - tenant={$safeUsername}
YAML;

    $phpConfContent = <<<PHP
server {
    listen 80;
    server_name {$safeUsername}.egenerei.es;

    root /usr/share/nginx/html;
    index index.html;

    location / {
        try_files \$uri \$uri/ =404;
        add_header Cache-Control "no-store, no-cache, must-revalidate, proxy-revalidate, max-age=0" always;
        expires off;
    }

    location ~* \.js$ {
        add_header Content-Type application/javascript;
        add_header Cache-Control "no-store, no-cache, must-revalidate, proxy-revalidate, max-age=0" always;
        expires off;
    }

    location ~* \.(css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot|otf)$ {
        add_header Cache-Control "no-store, no-cache, must-revalidate, proxy-revalidate, max-age=0" always;
        expires off;
    }

    location /files/ {
        proxy_pass http://files{$safeUsername}.es:80/;
    }
}
PHP;

    // Write files
    $composePath = $userDir . '/compose.yaml';
    $confPath = $userDir . '/php.conf';

    if (file_put_contents($composePath, $composeYamlContent) === false) {
        log_info("Failed to write compose.yaml for " . htmlspecialchars($safeUsername, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        die("Failed to write to $composePath");
    }

    if (file_put_contents($confPath, $phpConfContent) === false) {
        log_info("Failed to write php.conf for " . htmlspecialchars($safeUsername, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        die("Failed to write to $confPath");
    }

    log_info("Wrote config files for " . htmlspecialchars($safeUsername, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));

    // Redirect
    header('Location: login.php');
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register - DockHost</title>
    <link href="/css/tailwind.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center">
        <form method="post" class="bg-white p-6 rounded shadow w-96">
            <h2 class="text-2xl mb-4">Register</h2>
            <input type="text" name="username" placeholder="Username" class="w-full p-2 mb-4 border rounded" required>
            <input type="password" name="password" placeholder="Password" class="w-full p-2 mb-4 border rounded" required>
            <button type="submit" class="w-full bg-blue-500 text-white py-2 rounded">Register</button>
        </form>
    </div>
</body>
</html>
