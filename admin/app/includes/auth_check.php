<?php
// admin/app/includes/auth_check.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    // We are usually 3 levels deep from the admin root (e.g., app/pages/dashboard/index.php)
    // Redirecting to login.php in the admin root.
    $host = $_SERVER['HTTP_HOST'];

    if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
        define('BASE_URL', '/jatoestilos');
    } else {
        define('BASE_URL', '');
    }
    header("Location: " . BASE_URL . "/admin/login.php");
    exit;
}
?>