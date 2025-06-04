<?php
session_start();
require_once '../includes/user.php';
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['username'], $_POST['password'])) {
        die("Missing required fields.");
    }

    try {
        $reg = new UserRegistration($_POST['username'], $_POST['password'], $domain);
        $reg->register($pdo);
        echo '<!DOCTYPE html>
          <html>
              <head>
                <link rel="stylesheet" href="../css/style.css">
              </head>
            <body>
              <a href="http://'.$_POST['username'].'.'.$domain.'/admin/" class="button">Administration</a>
            </body>
          </html>';
          exit;
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
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