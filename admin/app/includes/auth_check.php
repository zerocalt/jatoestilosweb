<?php
// admin/app/includes/auth_check.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    // We are usually 3 levels deep from the admin root (e.g., app/pages/dashboard/index.php)
    // Redirecting to login.php in the admin root.
    header("Location: /jatoestilos/admin/login.php");
    exit;
}
?>