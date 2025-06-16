<?php
session_start();
require_once '../includes/classes/user_website_setup.class.php';
require_once '../includes/classes/login.class.php';
require_once '../includes/db/db.php';
require_once '../includes/functions/csrf.php';

$errorMessage = null;
$account = unserialize($_SESSION['account']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errorMessage = 'Invalid CSRF token.';
    } elseif (!isset($_POST['subdomain'],$_POST['db_password'], $_POST['confirm_db_password'])) {
        $errorMessage = "Missing required fields.";
    } else {
        try {
            $setup = new user_website_setup($account, $_POST['subdomain'], $_POST['db_password'], $_POST['confirm_db_password']);
            $setup->register();
            $login = new user_login($setup->get_username(),  $setup->get_password());
            $_SESSION['login'] = serialize($login);
            header('Location: admin.php');
            exit;
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
        }
    }
}
if (!isset($account)){
    header('Location: index.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>DockHost - Create Account</title>
  <link rel="stylesheet" href="../css/style.css">
</head>
<body>
  <?php include("../includes/navbars/links_navbar.php"); ?>
  <div class="fullscreen-center">
    <form method="POST">
      <h2>Your website settings</h2>
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">
      <input type="text" name="subdomain" placeholder="Subdomain" required>
      <div class="input-with-button">
        <input type="password" id="db_password" name="db_password" placeholder="Database password" required>
        <button type="button" class="toggle-password" onclick="togglePassword('db_password', this)"></button>
      </div>
      <div class="input-with-button">
        <input type="password" id="confirm_db_password" name="confirm_db_password" placeholder="Confirm database password" required>
        <button type="button" class="toggle-password" onclick="togglePassword('confirm_db_password', this)"></button>
      </div>
      <button type="submit">Activate</button>
      <?php if ($errorMessage): ?>
        <div class="error-message"><?= htmlspecialchars($errorMessage) ?></div>
      <?php endif; ?>
    </form>
  </div>
  <script src="../js/register.js"></script>
</body>
</html>