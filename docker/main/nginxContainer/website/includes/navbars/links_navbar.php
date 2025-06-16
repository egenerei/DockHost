<nav class="dh-navbar">
    <!-- Brand / site title -->
    <div class="dh-nav-brand ">DockHost</div>

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
            <?php if (strpos($_SERVER['REQUEST_URI'], '/admin.php') !== false): ?>
                <li class="dh-nav-item dropdown">
                    <a href="#" class="dropdown-toggle-btn" onclick="toggleDropdown(); return false;">Manage Files ▾</a>
                    <ul class="dropdown-menu" id="fileToolsDropdown">
                        <li><button onclick="openModal('uploadModal')">Upload Files</button></li>
                        <li><button onclick="openModal('uploadDirModal')">Upload Directory</button></li>
                        <li><button onclick="openModal('createFileModal')">Create File</button></li>
                        <li><button onclick="openModal('createDirModal')">Create Directory</button></li>
                    </ul>
                </li>
            <?php endif; ?>
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
