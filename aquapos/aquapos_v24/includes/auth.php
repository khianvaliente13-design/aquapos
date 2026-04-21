<?php
require_once __DIR__ . '/config.php';

function requireRole(...$roles) {
    // Determine which session to check based on roles requested
    if (in_array('admin', $roles)) {
        startAdminSession();
    } else {
        startCashierSession();
    }

    if (!isset($_SESSION['user_id'])) {
        $base = baseUrl();
        if (in_array('admin', $roles)) {
            header("Location: $base/admin_login.php");
        } else {
            header("Location: $base/cashier_login.php");
        }
        exit();
    }

    if (!in_array($_SESSION['role'], $roles)) {
        $base = baseUrl();
        if ($_SESSION['role'] === 'admin') {
            header("Location: $base/admin/dashboard.php");
        } else {
            header("Location: $base/pos.php");
        }
        exit();
    }
}

function isAdmin() {
    return ($_SESSION['role'] ?? '') === 'admin';
}
