<?php
require_once '../includes/user.php';
require_once '../includes/db.php';

$errorMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['username'], $_POST['password'])) {
        $errorMessage = "Missing required fields.";
    } else {
        try {
            $reg = new UserRegistration($_POST['username'],$_POST['email'], $_POST['subdomain'],$_POST['password'], $_POST['confirm_password'],$_POST['db_password'],$_POST['confirm_db_password'], $domain);
            $reg->register($pdo);
            echo '<!DOCTYPE html>
              <html>
                  <head>
                    <link rel="stylesheet" href="../css/style.css">
                  </head>
                <body>
                  <a href="http://' . $_POST['subdomain'] . '.' . $domain . '/admin/" class="button">Administration</a>
                </body>
              </html>';
            exit;
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
  <title>DockHost - Register</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
  <script src="js/register.js"></script>
  <?php include("../includes/navbar.php"); ?>
  <div class="fullscreen-center">
    <?php if ($errorMessage): ?>
      <div class="error-message"><?php echo htmlspecialchars($errorMessage); ?></div>
    <?php endif; ?>
    <form method="POST">
      <h2>Create Account</h2>
      <div class="form-section">
        <h3>Create your account</h3>
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
      </div>

      <div class="form-section">
        <h3>Website & Database settings</h3>
        <input type="text" name="subdomain" placeholder="Subdomain for your web" required>
        <div class="input-with-button">
          <input type="password" id="db_password" name="db_password" placeholder="Database root password" required>
          <button type="button" class="toggle-password" onclick="togglePassword('db_password', this)"></button>
        </div>
        <div class="input-with-button">
          <input type="password" id="confirm_db_password" name="confirm_db_password" placeholder="Confirm database root password" required>
          <button type="button" class="toggle-password" onclick="togglePassword('confirm_db_password', this)"></button>
        </div>
      </div>
      <button type="submit">Register</button>
    </form>
  </div>
</body>
</html>