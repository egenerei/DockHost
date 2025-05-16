<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<pre>";

    // Check POST data
    if (empty($_POST['username']) || empty($_POST['password'])) {
        die("Username or password not provided.");
    }

    var_dump($_POST);

    // Hash the password
    $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Prepare and execute SQL
    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
        $stmt->execute([$_POST['username'], $hash]);
    } catch (PDOException $e) {
        die("Database error: " . $e->getMessage());
    }

    // Sanitize username
    $basePath = '/clients';
    $safeUsername = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['username']);

    if (empty($safeUsername)) {
        die("Sanitized username is empty or invalid.");
    }

    $userDir = $basePath . '/' . $safeUsername;

    echo "Safe username: $safeUsername\n";
    echo "Target directory: $userDir\n";

    // Directory creation
    if (!is_dir($userDir)) {
        echo "Directory does not exist. Attempting to create...\n";
        if (!mkdir($userDir, 0755, true)) {
            die("Failed to create directory. Check permissions on $basePath.");
        }
    } else {
        echo "Directory already exists.\n";
    }

    // Directory writability
    if (!is_writable($userDir)) {
        die("Directory exists but is not writable: $userDir");
    }

    echo "Directory is writable.\n";

    // Create file contents
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
          - $safeUsername.egenerei.es

  filemanager:
    image: filebrowser/filebrowser
    restart: always
    volumes:
      - website:/srv
    networks:
      main_client_intranet:
        aliases:
          - files.$safeUsername.egenerei.es

networks:
  main_client_intranet:
    external: true

volumes:
  website:
    labels:
      - tenant=$safeUsername
YAML;

    $phpConfContent = <<<PHP
server {
    listen 80;
    server_name $safeUsername.egenerei.es;

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
}
PHP;

    // Write files and check results
    $composePath = $userDir . '/compose.yaml';
    $confPath = $userDir . '/php.conf';

    $write1 = file_put_contents($composePath, $composeYamlContent);
    $write2 = file_put_contents($confPath, $phpConfContent);

    if ($write1 === false) {
        die("Failed to write to $composePath");
    } else {
        echo "Wrote compose.yaml ($write1 bytes)\n";
    }

    if ($write2 === false) {
        die("Failed to write to $confPath");
    } else {
        echo "Wrote php.conf ($write2 bytes)\n";
    }

    echo "All operations completed successfully.\n";

    echo "</pre>";

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
