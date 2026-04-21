<?php
require_once 'includes/config.php';
startCashierSession();
$base = baseUrl();

if (isset($_SESSION['user_id'])) {
    header('Location: ' . $base . '/pos.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username && $password) {
        $conn = getConnection();
        $stmt = safePrepare($conn, "SELECT * FROM users WHERE username = ? AND role IN ('cashier','delivery') AND status = 'active'");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $conn->close();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role']      = $user['role'];
            header("Location: $base/pos.php");
            exit();
        } else {
            $error = 'Invalid username or password.';
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
<title>Cashier Login — AquaStation</title>
<link rel="stylesheet" href="assets/css/auth.css">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
</head>
<body class="cashier-page">
<div class="bubbles"><div class="b"></div><div class="b"></div><div class="b"></div><div class="b"></div><div class="b"></div></div>
<div class="card">
    <div class="logo"><div class="logo-icon">🧾</div><div class="logo-text"><h1>AquaStation</h1><p>Management System</p></div></div>
    <div class="portal-tag">🧾 Cashier Portal</div>
    <h2>Staff Login</h2>
    <p class="subtitle">Sign in to access the Point of Sale</p>
    <?php if ($error): ?><div class="error-msg">⚠ <?=htmlspecialchars($error)?></div><?php endif; ?>
    <form method="POST">
        <div class="form-group"><label>Username</label><input type="text" name="username" placeholder="Enter your username" value="<?=htmlspecialchars($_POST['username']??'')?>" autofocus autocomplete="username"></div>
        <div class="form-group"><label>Password</label><input type="password" name="password" placeholder="Enter your password" autocomplete="current-password"></div>
        <button type="submit" class="btn-login">Open POS →</button>
    </form>
    <div class="info-note">💡 Contact your administrator if you forgot your password or need access.</div>
</div>
</body>
</html>
