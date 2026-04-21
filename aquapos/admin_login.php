<?php
require_once 'includes/config.php';
startAdminSession();
$base = baseUrl();

if (isset($_SESSION['user_id'])) {
    header('Location: ' . $base . '/admin/dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username && $password) {
        $conn = getConnection();
        $stmt = safePrepare($conn, "SELECT * FROM users WHERE username = ? AND role = 'admin' AND status = 'active'");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $conn->close();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role']      = $user['role'];
            header("Location: $base/admin/dashboard.php");
            exit();
        } else {
            $error = 'Invalid admin credentials.';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login — AquaStation</title>
<link rel="stylesheet" href="assets/css/auth.css">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
</head>
<body class="admin-page">
<div class="bubbles"><div class="b"></div><div class="b"></div><div class="b"></div><div class="b"></div><div class="b"></div></div>
<div class="card">
    <div class="logo"><div class="logo-icon">🛡️</div><div class="logo-text"><h1>AquaStation</h1><p>Management System</p></div></div>
    <div class="portal-tag">🔒 Admin Portal</div>
    <h2>Administrator Login</h2>
    <p class="subtitle">Restricted access — authorized personnel only</p>
    <?php if ($error): ?><div class="error-msg">⚠ <?=htmlspecialchars($error)?></div><?php endif; ?>
    <form method="POST">
        <div class="form-group"><label>Admin Username</label><input type="text" name="username" placeholder="Enter admin username" value="<?=htmlspecialchars($_POST['username']??'')?>" autofocus autocomplete="username"></div>
        <div class="form-group"><label>Password</label><input type="password" name="password" placeholder="Enter password" autocomplete="current-password"></div>
        <button type="submit" class="btn-login">Access Admin Panel →</button>
    </form>
    <div class="security-note">🔐 This portal is for administrators only. Unauthorized access is logged and may be reported.</div>
</div>
</body>
</html>
