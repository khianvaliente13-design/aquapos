<?php
// This page is no longer linked publicly.
// Each portal has its own direct URL:
//   Admin:    /aquapos/admin_login.php
//   Cashier:  /aquapos/cashier_login.php
//   Customer: /aquapos/customer/index.php
require_once 'includes/config.php';
$base = baseUrl();
header("Location: $base/cashier_login.php");
exit();
