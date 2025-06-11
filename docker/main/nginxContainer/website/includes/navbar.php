<nav class="dh-navbar">
    <!-- Brand / site title -->
    <a href="/" class="dh-nav-brand">DockHost</a>

    <!-- Hamburger button (hidden ≥768 px by CSS) -->
    <button class="dh-nav-toggle"
            type="button"
            aria-controls="primary-navigation"
            aria-expanded="false">
        <span class="sr-only"></span>
        &#x2630; <!-- ☰ -->
    </button>

    <!-- Link list -->
    <ul id="primary-navigation" class="dh-nav-links">
        <li><a href="/index.php">Home</a></li>
        <?php if (isset($_SESSION['login'])): ?>
            <li><a href="admin.php">Administration</a></li>
            <li><a href="logout.php">Logout</a></li>
        <?php else: ?>
            <li><a href="login.php">Login</a></li>
            <li><a href="create_account.php">Register</a></li>
        <?php endif; ?>
    </ul>
</nav>

<script>
/* toggle the mobile menu */
document.querySelector('.dh-nav-toggle')?.addEventListener('click', (btn) => {
    const links  = document.querySelector('.dh-nav-links');
    const open   = links.classList.toggle('open');
    btn.currentTarget.setAttribute('aria-expanded', open);
});
</script>
