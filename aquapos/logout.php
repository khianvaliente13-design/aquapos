<?php
require_once 'includes/config.php';
$base = baseUrl();

// Detect which portal is logging out via ?portal= param
// Default: destroy whichever session is currently active
$portal = $_GET['portal'] ?? 'cashier';

if ($portal === 'admin') {
    startAdminSession();
    $redirect = "$base/admin_login.php";
} else {
    startCashierSession();
    $redirect = "$base/cashier_login.php";
}

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $p["path"], $p["domain"], $p["secure"], $p["httponly"]
    );
}
session_destroy();
header("Location: $redirect");
exit();
