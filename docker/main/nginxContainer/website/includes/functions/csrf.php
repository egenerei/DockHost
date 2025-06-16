<?php
function csrf_token(): string
{
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function verify_csrf(): bool {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        return false;
    }
    unset($_SESSION['csrf']); // Optional: rotate token
    csrf_token();
    return true;
}
