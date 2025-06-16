<?php
session_start();
require_once '../includes/db/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>DockHost</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
  <?php include("../includes/navbars/links_navbar.php"); ?>
  <div class="fullscreen-center">
    <h1>Welcome to DockHost</h1>
    <p> Create and deploy your website in a second</p>
    <a href="create_account.php" class="button">Register</a>
  </div>
</body>
</html>
