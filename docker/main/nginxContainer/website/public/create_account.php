<?php
session_start();
require_once '../includes/user_account.class.php';
require_once '../includes/db.php';
$errorMessage = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['username'],$_POST['email'], $_POST['password'], $_POST['confirm_password'])) {
        $errorMessage = "Missing required fields.";
    } else {
        try {
          $account = new user_account($_POST['username'], $_POST['email'], $_POST['password'], $_POST['confirm_password']);
          $_SESSION['account'] = serialize($account);
          header('Location: setup_site.php');
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
  <title>DockHost - Create Account</title>
  <link rel="stylesheet" href="../css/style.css">
</head>
<body>
  <?php include("../includes/navbar.php"); ?>
  <div class="fullscreen-center">
    <?php if ($errorMessage): ?>
      <div class="error-message"><?= htmlspecialchars($errorMessage) ?></div>
    <?php endif; ?>
    <form method="POST">
      <h2>Create Account</h2>
      <input type="text" name="username" placeholder="Username" required>
      <input type="email" name="email" placeholder="Email" required>
      <div class="input-with-button">
        <input type="password" id="password" name="password" placeholder="Password" required>
        <button type="button" class="toggle-password" onclick="togglePassword('password', this)"></button>
      </div>
      <div class="input-with-button">
        <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required>
        <button type="button" class="toggle-password" onclick="togglePassword('confirm_password', this)"></button>
      </div>
      <button type="submit">Continue</button>
    </form>
  </div>
  <script src="../js/register.js"></script>
</body>
</html>