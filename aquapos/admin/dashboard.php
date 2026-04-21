<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireRole('admin');
$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard — AquaStation</title>
<link rel="stylesheet" href="../assets/css/admin.css">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
</head>
<body>
<div class="topbar">
  <div class="topbar-brand">
    <div class="brand-icon">💧</div>
    <span class="brand-name">AquaStation</span>
    <span style="font-size:11px;color:var(--yellow);background:rgba(255,204,0,.1);padding:2px 8px;border-radius:6px;margin-left:8px;font-weight:700">ADMIN</span>
  </div>
  <div class="topbar-nav">
    <a href="dashboard.php" class="nav-btn active">📊 Dashboard</a>
    <a href="products.php" class="nav-btn">📦 Products</a>
    <a href="refills.php" class="nav-btn">💧 Refills</a>
    <a href="customers.php" class="nav-btn">👥 Customers</a>
    <a href="transactions.php" class="nav-btn">🧾 Transactions</a>
  </div>
  <div class="topbar-right">
    <div class="admin-badge">
      <div class="admin-avatar"><?=strtoupper(substr($user['full_name'],0,1))?></div>
      <span><?=htmlspecialchars($user['full_name'])?></span>
      <span class="role-tag">Admin</span>
    </div>
    <a href="../logout.php?portal=admin" class="logout-btn">Sign out</a>
  </div>
</div>

<div class="admin-layout">
  <div class="sidebar">
    <div class="sidebar-section">Main</div>
    <a href="dashboard.php" class="sidebar-link active"><span class="s-icon">📊</span> Dashboard</a>
    <a href="products.php" class="sidebar-link"><span class="s-icon">📦</span> Products</a>
    <a href="refills.php" class="sidebar-link"><span class="s-icon">💧</span> Refills</a>
    <a href="customers.php" class="sidebar-link"><span class="s-icon">👥</span> Customers</a>
    <a href="transactions.php" class="sidebar-link"><span class="s-icon">🧾</span> Transactions</a>
    <div class="sidebar-section">Account</div>
    <a href="../logout.php?portal=admin" class="sidebar-link"><span class="s-icon">🚪</span> Sign Out</a>
  </div>

  <div class="main-content">
    <div class="page-title">Dashboard</div>
    <div class="page-sub" id="dateNow">Loading...</div>

    <div class="stats-grid" id="statsGrid">
      <div class="stat-card stat-cyan"><div class="stat-label">Today's Transactions</div><div class="stat-value"><div class="spin"></div></div></div>
      <div class="stat-card stat-green"><div class="stat-label">Today's Revenue</div><div class="stat-value"><div class="spin"></div></div></div>
      <div class="stat-card stat-yellow"><div class="stat-label">This Month</div><div class="stat-value"><div class="spin"></div></div></div>
      <div class="stat-card stat-red"><div class="stat-label">Low Stock Items</div><div class="stat-value"><div class="spin"></div></div></div>
    </div>

    <div class="section-card">
      <div class="section-head">
        <h3>Recent Transactions</h3>
        <a href="#">View all →</a>
      </div>
      <table>
        <thead><tr><th>Code</th><th>Customer</th><th>Cashier</th><th>Total</th><th>Method</th><th>Status</th><th>Time</th></tr></thead>
        <tbody id="recentTbody"><tr><td colspan="7" style="text-align:center;padding:24px;color:var(--muted)"><div class="spin" style="margin:auto"></div></td></tr></tbody>
      </table>
    </div>
  </div>
</div>
<script src="../assets/js/admin-dashboard.js"></script>
</body>
</html>
