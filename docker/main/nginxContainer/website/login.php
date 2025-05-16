<?php
  session_start();
  require_once 'includes/db.php';
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("SELECT id, password FROM users WHERE username = ?");
    $stmt->execute([$_POST['username']]);
    $user = $stmt->fetch();

    if ($user && password_verify($_POST['password'], $user['password'])) {
      $_SESSION['user_id'] = $user['id'];
      header('Location: dashboard.php');
      exit;
    } else {
      $error = "Invalid credentials.";
    }
  }
?>
<!DOCTYPE html>
<html>
<head>
  <title>Login - DockHost</title>
  <link href="/css/tailwind.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
  <div class="min-h-screen flex items-center justify-center">
    <form method="post" class="bg-white p-6 rounded shadow w-96">
      <h2 class="text-2xl mb-4">Login</h2>
      <?php if (!empty($error)) echo "<p class='text-red-500'>$error</p>"; ?>
      <input type="text" name="username" placeholder="Username" class="w-full p-2 mb-4 border rounded" required>
      <input type="password" name="password" placeholder="Password" class="w-full p-2 mb-4 border rounded" required>
      <button type="submit" class="w-full bg-blue-500 text-white py-2 rounded">Login</button>
    </form>
  </div>
</body>
</html>