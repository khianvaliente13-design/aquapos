<?php
require_once 'includes/config.php';
$base = baseUrl();

// Clear both sessions
startAdminSession();
$_SESSION = [];
session_destroy();
session_write_close();

startCashierSession();
$_SESSION = [];
session_destroy();

header("Location: $base/cashier_login.php");
exit();
