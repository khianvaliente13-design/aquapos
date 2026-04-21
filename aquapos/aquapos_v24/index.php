<?php
require_once 'includes/config.php';
$base = baseUrl();

// Check admin session first
startAdminSession();
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin') {
    header("Location: $base/admin/dashboard.php");
    exit();
}
session_write_close();

// Then check cashier session
startCashierSession();
if (isset($_SESSION['user_id'])) {
    header("Location: $base/pos.php");
    exit();
}

header("Location: $base/cashier_login.php");
exit();
