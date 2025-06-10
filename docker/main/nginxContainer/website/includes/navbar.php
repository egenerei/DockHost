<nav class="navbar">
  <div class="navbar-brand">DockHost</div>
  <ul class="navbar-links">
    <li><a href="index.php">Home</a></li>
    <?php if (isset($_SESSION['login'])): ?>
        <li><a href="admin.php">Administration</a></li>
        <li><a href="logout.php">Logout</a></li>
    <?php else: ?>
        <li><a href="login.php">Login</a></li>
        <li><a href="create_account.php">Register</a></li>
    <?php endif; ?>
    <li><a href="docs.php">Docs</a></li>
  </ul>
</nav>