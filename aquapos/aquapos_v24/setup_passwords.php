<?php
require_once 'includes/config.php';

$conn    = getConnection();
$results = [];

$accounts = [
    ['username' => 'admin',    'password' => 'password', 'role' => 'admin',   'full_name' => 'System Administrator'],
    ['username' => 'cashier1', 'password' => 'password', 'role' => 'cashier', 'full_name' => 'Maria Santos'],
];

foreach ($accounts as $a) {
    $hash      = password_hash($a['password'], PASSWORD_BCRYPT);
    $username  = $conn->real_escape_string($a['username']);
    $full_name = $conn->real_escape_string($a['full_name']);
    $role      = $conn->real_escape_string($a['role']);

    // Check if user exists
    $check = $conn->query("SELECT id FROM users WHERE username = '$username'");

    if ($check && $check->num_rows > 0) {
        $ok = $conn->query("UPDATE users SET password = '$hash' WHERE username = '$username'");
        $results[] = [
            'user'   => $a['username'],
            'status' => $ok ? 'success' : 'error',
            'msg'    => $ok ? 'Password updated successfully' : $conn->error,
        ];
    } else {
        $ok = $conn->query("INSERT INTO users (username, password, full_name, role, status)
                            VALUES ('$username', '$hash', '$full_name', '$role', 'active')");
        $results[] = [
            'user'   => $a['username'],
            'status' => $ok ? 'success' : 'error',
            'msg'    => $ok ? 'Account created successfully' : $conn->error,
        ];
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Setup — AquaStation</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;background:#060f1e;color:#e8f4ff;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:#0d1b2e;border:1px solid rgba(0,212,255,0.2);border-radius:16px;padding:32px 36px;width:100%;max-width:480px}
h2{font-size:20px;margin-bottom:6px;color:#00d4ff}
.sub{font-size:13px;color:#5a7a9a;margin-bottom:24px}
.result-row{display:flex;align-items:flex-start;justify-content:space-between;padding:12px 16px;border-radius:10px;margin-bottom:10px;font-size:14px}
.result-row.success{background:rgba(0,229,160,.08);border:1px solid rgba(0,229,160,.25)}
.result-row.error{background:rgba(255,77,109,.08);border:1px solid rgba(255,77,109,.25)}
.label{font-weight:600}
.label.success{color:#00e5a0}
.label.error{color:#ff4d6d}
.msg{font-size:12px;color:#5a7a9a;margin-top:3px}
.accounts{margin-top:20px;padding:16px;background:rgba(255,204,0,.06);border:1px solid rgba(255,204,0,.2);border-radius:10px}
.accounts h3{color:#ffcc00;font-size:13px;margin-bottom:10px}
.account-row{display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(255,255,255,.05);font-size:13px}
.account-row:last-child{border-bottom:none}
.creds{font-family:monospace;color:#e8f4ff}
.warning{margin-top:16px;padding:12px 16px;background:rgba(255,77,109,.08);border:1px solid rgba(255,77,109,.2);border-radius:10px;font-size:12px;color:#ff8080;line-height:1.6}
.btn{display:block;margin-top:20px;padding:13px;background:linear-gradient(135deg,#0d6efd,#00d4ff);border:none;border-radius:10px;font-size:14px;font-weight:700;color:#060f1e;cursor:pointer;text-align:center;text-decoration:none;width:100%}
.btn:hover{opacity:.9}
</style>
</head>
<body>
<div class="card">
    <h2>⚙️ Setup Passwords</h2>
    <p class="sub">Setting up default accounts for AquaStation</p>

    <?php foreach ($results as $r): ?>
    <div class="result-row <?= $r['status'] ?>">
        <div>
            <div class="label <?= $r['status'] ?>"><?= $r['status']==='success' ? '✓' : '✗' ?> <?= htmlspecialchars($r['user']) ?></div>
            <div class="msg"><?= htmlspecialchars($r['msg']) ?></div>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="accounts">
        <h3>Default Login Credentials</h3>
        <div class="account-row"><span>🛡️ Admin</span><span class="creds">admin / password</span></div>
        <div class="account-row"><span>🧾 Cashier</span><span class="creds">cashier1 / password</span></div>
    </div>

    <div class="warning">
        ⚠️ Change these passwords after your first login. You can delete this file once done — it is no longer needed after setup.
    </div>

    <a href="cashier_login.php" class="btn">Go to Login →</a>
</div>
</body>
</html>
