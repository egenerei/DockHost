<?php
session_start();
require_once '../includes/login.class.php';
require_once '../includes/db.php';
$errorMessage = null;

if (isset($_SESSION['login'])) {
    header("Location: admin.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['username'], $_POST['password'])) {
        $errorMessage = "Missing required fields.";
    } else {
        try {
            $login = new user_login($_POST['username'],  $_POST['password']);
            $_SESSION['login'] = serialize($login);
            header("Location: admin.php");
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>DockHost - Login</title>
  <link rel="stylesheet" href="../css/style.css">
</head>
<body>
  <?php include("../includes/navbar.php"); ?>
  <div class="fullscreen-center">
    <?php if ($errorMessage): ?>
      <div class="error-message"><?= htmlspecialchars($errorMessage) ?></div>
    <?php endif; ?>
    <form method="POST">
      <h2>Login</h2>
      <input type="text" name="username" placeholder="Username" required>
      <div class="input-with-button">
        <input type="password" id="password" name="password" placeholder="Password" required>
        <button type="button" class="toggle-password" onclick="togglePassword('password', this)"></button>
      </div>
      <button type="submit">Login</button>
    </form>
  </div>
  <script src="../js/register.js"></script>
</body>
</html>