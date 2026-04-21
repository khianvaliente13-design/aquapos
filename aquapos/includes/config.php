<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'aquastation');
define('APP_NAME', 'AquaStation POS');
define('APP_VERSION', '2.0');

function baseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'];
    $script   = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME']);
    $docroot  = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
    $relative = str_replace($docroot, '', $script);
    $parts    = explode('/', trim($relative, '/'));
    $base     = '/' . $parts[0];
    return $protocol . '://' . $host . $base;
}

// ── Separate sessions per portal ─────────────────────────────────────
// Admin and Cashier use different session names so both can be
// logged in simultaneously in the same browser.

function startAdminSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_name('aquapos_admin');
        session_start();
    }
}

function startCashierSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_name('aquapos_cashier');
        session_start();
    }
}

// Generic start (used by APIs that need to detect which session is active)
function startSession($name = 'aquapos_cashier') {
    if (session_status() === PHP_SESSION_NONE) {
        session_name($name);
        session_start();
    }
}

function getConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        if (defined('IS_API')) {
            echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
            exit();
        }
        die('<h3 style="font-family:sans-serif;color:red;padding:20px">
            Database connection failed: ' . htmlspecialchars($conn->connect_error) . '<br><br>
            Make sure MySQL is running in XAMPP and the database <strong>' . DB_NAME . '</strong> exists.
        </h3>');
    }
    $conn->set_charset("utf8");
    return $conn;
}

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        $base = baseUrl();
        header("Location: $base/cashier_login.php");
        exit();
    }
}

function getCurrentUser() {
    return $_SESSION ?? null;
}

function safePrepare($conn, $sql) {
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        if (defined('IS_API')) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error . '. Please re-import database.sql']);
            exit();
        }
        die('<h3 style="font-family:sans-serif;color:red;padding:20px">
            Query failed: ' . htmlspecialchars($conn->error) . '<br>
            Please re-import <strong>database.sql</strong> in phpMyAdmin.
        </h3>');
    }
    return $stmt;
}
