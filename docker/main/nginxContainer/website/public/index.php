<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>DockHost</title>
  <link href="/css/tailwind.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
  <div class="min-h-screen flex items-center justify-center">
    <div class="text-center">
      <h1 class="text-4xl font-bold">Welcome to DockHost</h1>
      <p class="mt-4">Manage your isolated Docker environments easily.</p>
      <div class="mt-6">
        <a href="login.php" class="text-blue-600 underline">Login</a> | 
        <a href="register.php" class="text-blue-600 underline">Register</a>
      </div>
    </div>
  </div>
</body>
</html>