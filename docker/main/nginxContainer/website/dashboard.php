<?php
  session_start();
  if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
  }
  echo'
    <!DOCTYPE html>
    <html>
    <head>
    <title>Dashboard - DockHost</title>
    <link href="/css/tailwind.css" rel="stylesheet">
    </head>
    <body class="bg-gray-100">
    <div class="min-h-screen flex flex-col items-center justify-center">
        <h1 class="text-3xl mb-6">Welcome to your Dashboard</h1>
        <p>Here you can manage your Docker containers.</p>
        <a href="logout.php" class="mt-4 text-red-600 underline">Logout</a>
    </div>
    </body>
    </html>';
?>
