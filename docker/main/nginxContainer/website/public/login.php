<?php
session_start();
require_once '../includes/db/db.php';
require_once '../includes/classes/login.class.php';
require_once '../includes/functions/csrf.php';

if (isset($_SESSION['login'])) {
    header("Location: admin.php");
    exit;
}

$errorMessage = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errorMessage = 'Invalid CSRF token.';
    } elseif (!isset($_POST['username'], $_POST['password'])) {
        $errorMessage = "Missing required fields.";
    } else {
        try {
            $login = new user_login($_POST['username'], $_POST['password']);
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
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>DockHost - Login</title>
  <link rel="stylesheet" href="../css/style.css">
</head>
<body>
  <?php include("../includes/navbars/links_navbar.php"); ?>
  <div class="fullscreen-center">
    <form method="POST">
      <h2>Login</h2>
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">
      <input type="text" name="username" placeholder="Username" required>
      <div class="input-with-button">
        <input type="password" id="password" name="password" placeholder="Password" required>
        <button type="button" class="icon-btn toggle-password" aria-label="Show password" onclick="togglePassword('password', this)"></button>
      </div>
      <button type="submit">Login</button>
      <?php if ($errorMessage): ?>
        <div class="error-message"><?= htmlspecialchars($errorMessage) ?></div>
      <?php endif; ?>
    </form>
  </div>
  <script src="../js/register.js"></script>
</body>
</html>