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
<title>Transactions — AquaStation Admin</title>
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
    <a href="dashboard.php" class="nav-btn">📊 Dashboard</a>
    <a href="products.php" class="nav-btn">📦 Products</a>
    <a href="customers.php" class="nav-btn">👥 Customers</a>
    <a href="transactions.php" class="nav-btn active">🧾 Transactions</a>
  </div>
  <div class="topbar-right">
    <div class="admin-badge">
      <div class="admin-avatar"><?=strtoupper(substr($user['full_name'],0,1))?></div>
      <span><?=htmlspecialchars($user['full_name'])?></span>
    </div>
    <a href="../logout.php?portal=admin" class="logout-btn">Sign out</a>
  </div>
</div>

<div class="layout">
  <div class="sidebar">
    <div class="sidebar-section">Main</div>
    <a href="dashboard.php" class="sidebar-link"><span class="s-icon">📊</span> Dashboard</a>
    <a href="products.php" class="sidebar-link"><span class="s-icon">📦</span> Products</a>
    <a href="customers.php" class="sidebar-link"><span class="s-icon">👥</span> Customers</a>
    <a href="transactions.php" class="sidebar-link active"><span class="s-icon">🧾</span> Transactions</a>
    <div class="sidebar-section">Account</div>
    <a href="../logout.php?portal=admin" class="sidebar-link"><span class="s-icon">🚪</span> Sign Out</a>
  </div>

  <div class="main">
    <div class="page-title">Transaction History</div>
    <div class="page-sub">All sales records — search, filter, and view receipts</div>

    <!-- SUMMARY CARDS -->
    <div class="summary-row" id="summaryRow">
      <div class="sum-card sum-cyan"><div class="sum-label">Transactions</div><div class="sum-value">—</div></div>
      <div class="sum-card sum-green"><div class="sum-label">Total Revenue</div><div class="sum-value">—</div></div>
      <div class="sum-card sum-yellow"><div class="sum-label">Total Discounts</div><div class="sum-value">—</div></div>
      <div class="sum-card sum-red"><div class="sum-label">Showing</div><div class="sum-value">—</div></div>
    </div>

    <!-- FILTERS -->
    <div class="filters">
      <div class="search-wrap">
        <span class="search-icon">🔍</span>
        <input type="text" id="searchInput" placeholder="Search by code, customer, or cashier…">
      </div>
      <input type="date" class="filter-input" id="dateFrom" title="From date">
      <input type="date" class="filter-input" id="dateTo" title="To date">
      <select class="filter-input" id="statusFilter">
        <option value="">All Status</option>
        <option value="completed">Completed</option>
        <option value="pending">Pending</option>
        <option value="cancelled">Cancelled</option>
      </select>
      <button class="btn-reset" onclick="resetFilters()">Reset</button>
    </div>

    <!-- TABLE -->
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Code</th>
            <th>Date & Time</th>
            <th>Customer</th>
            <th>Cashier</th>
            <th>Items</th>
            <th>Subtotal</th>
            <th>Discount</th>
            <th>Total</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody id="tbody">
          <tr><td colspan="10" class="empty"><div class="spin" style="margin:auto"></div></td></tr>
        </tbody>
      </table>
      <div class="pagination" id="pagination" style="display:none">
        <span id="pageInfo">—</span>
        <div class="page-btns" id="pageBtns"></div>
      </div>
    </div>
  </div>
</div>

<!-- TRANSACTION DETAIL MODAL -->
<div class="overlay" id="detailOverlay">
  <div class="modal">
    <div class="modal-title">
      <span id="modalCode">Transaction Detail</span>
      <div style="display:flex;gap:8px;align-items:center">
        <button id="modalDeleteBtn" style="padding:5px 12px;background:rgba(255,77,109,.1);border:1px solid rgba(255,77,109,.3);border-radius:7px;font-size:12px;color:var(--red);cursor:pointer" onclick="confirmDeleteFromModal()">🗑 Delete</button>
        <button class="modal-close" onclick="closeModal()">✕</button>
      </div>
    </div>
    <div id="modalBody"><div class="empty"><div class="spin" style="margin:auto"></div></div></div>
  </div>
</div>

<!-- DELETE CONFIRM MODAL -->
<div class="overlay" id="deleteOverlay">
  <div class="modal" style="width:380px">
    <div class="modal-title" style="color:var(--red)">🗑 Delete Transaction</div>
    <p id="deleteMsg" style="font-size:14px;color:var(--muted);margin-bottom:20px;line-height:1.6"></p>
    <div style="display:flex;gap:10px">
      <button style="flex:1;padding:11px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;font-size:14px;color:var(--text);cursor:pointer" onclick="closeDelete()">Cancel</button>
      <button style="flex:1;padding:11px;background:rgba(255,77,109,.12);border:1px solid rgba(255,77,109,.3);border-radius:10px;font-size:14px;font-weight:700;color:var(--red);cursor:pointer" onclick="doDelete()">Delete</button>
    </div>
  </div>
</div>

<script src="../assets/js/admin-transactions.js"></script>
</body>
</html>
