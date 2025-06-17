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
            <li><a href="logout.php">Logout</a></li>
        <?php else: ?>
            <li><a href="login.php">Login</a></li>
            <li><a href="create_account.php">Register</a></li>
        <?php endif; ?>
    </ul>
</nav>
<?php

$uri_matches_admin = strpos($_SERVER['REQUEST_URI'], '/admin.php') !== false;
$not_editing_file = !isset($_GET['action']) || $_GET['action'] !== 'edit';

if ($uri_matches_admin && $not_editing_file): ?>
    <nav class="dh-sub-navbar">
        <div class="dh-sub-actions centered">
            <button onclick="openModal('uploadModal')" class="btn btn-outline">📤 Upload Files</button>
            <button onclick="openModal('createFileModal')" class="btn btn-outline">📝 Create File</button>
            <button onclick="openModal('uploadDirModal')" class="btn btn-outline">📁 Upload Directory</button>
            <button onclick="openModal('createDirModal')" class="btn btn-outline">📂 Create Directory</button>
            <?php if (isset($client_domain)): ?>
                <a href="https://<?= htmlspecialchars($client_domain) ?>" class="btn btn-outline">🌐 Visit your Site</a>
                <a href="https://<?= htmlspecialchars($client_domain_phpmyadmin) ?>" class="btn btn-outline">🛠 phpMyAdmin</>
            <?php endif; ?>
        </div>
    </nav>
<?php endif; ?>
<script>
/* toggle the mobile menu */
document.querySelector('.dh-nav-toggle')?.addEventListener('click', (btn) => {
    const links  = document.querySelector('.dh-nav-links');
    const open   = links.classList.toggle('open');
    btn.currentTarget.setAttribute('aria-expanded', open);
});
</script>
