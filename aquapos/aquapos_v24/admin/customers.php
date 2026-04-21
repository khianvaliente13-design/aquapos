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
<title>Customers — AquaStation Admin</title>
<link rel="stylesheet" href="../assets/css/admin.css">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
</head>
<body>

<!-- TOPBAR -->
<div class="topbar">
  <div class="topbar-brand">
    <div class="brand-icon">💧</div>
    <span class="brand-name">AquaStation</span>
    <span style="font-size:11px;color:var(--yellow);background:rgba(255,204,0,.1);padding:2px 8px;border-radius:6px;margin-left:8px;font-weight:700">ADMIN</span>
  </div>
  <div class="topbar-nav">
    <a href="dashboard.php" class="nav-btn">📊 Dashboard</a>
    <a href="products.php" class="nav-btn">📦 Products</a>
    <a href="customers.php" class="nav-btn active">👥 Customers</a>
    <a href="transactions.php" class="nav-btn">🧾 Transactions</a>
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
  <!-- SIDEBAR -->
  <div class="sidebar">
    <div class="sidebar-section">Main</div>
    <a href="dashboard.php" class="sidebar-link"><span class="s-icon">📊</span> Dashboard</a>
    <a href="products.php" class="sidebar-link"><span class="s-icon">📦</span> Products</a>
    <a href="customers.php" class="sidebar-link active"><span class="s-icon">👥</span> Customers</a>
    <a href="transactions.php" class="sidebar-link"><span class="s-icon">🧾</span> Transactions</a>
    <div class="sidebar-section">Account</div>
    <a href="../logout.php?portal=admin" class="sidebar-link"><span class="s-icon">🚪</span> Sign Out</a>
  </div>

  <!-- MAIN -->
  <div class="main">
    <div class="page-header">
      <div>
        <div class="page-title">Customer Management</div>
        <div class="page-sub">View, edit, and manage all customers</div>
      </div>
      <button class="btn-add" onclick="openAddModal()">+ Add Customer</button>
    </div>

    <!-- STATS -->
    <div class="stats-row" id="statsRow">
      <div class="stat-card stat-cyan"><div class="stat-label">Total Customers</div><div class="stat-value"><div class="spin"></div></div></div>
      <div class="stat-card stat-green"><div class="stat-label">Active</div><div class="stat-value"><div class="spin"></div></div></div>
      <div class="stat-card stat-yellow"><div class="stat-label">Total Points Issued</div><div class="stat-value"><div class="spin"></div></div></div>
      <div class="stat-card stat-red"><div class="stat-label">Inactive</div><div class="stat-value"><div class="spin"></div></div></div>
    </div>

    <!-- TOOLBAR -->
    <div class="toolbar">
      <div class="search-wrap">
        <span class="search-icon">🔍</span>
        <input type="text" id="searchInput" placeholder="Search by name, phone, or address…">
      </div>
      <select class="filter-sel" id="statusFilter">
        <option value="">All Status</option>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
      </select>
    </div>

    <!-- TABLE -->
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Name</th>
            <th>Phone</th>
            <th>Address</th>
            
            <th>Points</th>
            <th>Purchases</th>
            <th>Status</th>
            <th>Registered</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="tbody">
          <tr><td colspan="10" class="empty"><div class="spin" style="margin:auto"></div></td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ── ADD CUSTOMER MODAL ── -->
<div class="overlay" id="addModal">
  <div class="modal">
    <div class="modal-title">➕ Add Customer</div>
    <div id="addMsg"></div>
    <div class="modal-grid">
      <div class="form-group">
        <label class="form-label">Full Name *</label>
        <input type="text" class="form-input" id="aName" placeholder="Juan dela Cruz">
      </div>
      <div class="form-group">
        <label class="form-label">Phone Number *</label>
        <input type="tel" class="form-input" id="aPhone" placeholder="09xxxxxxxxx" maxlength="11">
      </div>
      <div class="form-group full">
        <label class="form-label">Address *</label>
        <textarea class="form-textarea" id="aAddress" placeholder="House No., Street, Barangay, City"></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Email (optional)</label>
        <input type="email" class="form-input" id="aEmail" placeholder="you@email.com">
      </div>
    </div>
    <div class="modal-actions">
      <button class="btn-cancel" onclick="closeAll()">Cancel</button>
      <button class="btn-submit" onclick="submitAdd()">Add Customer</button>
    </div>
  </div>
</div>

<!-- ── EDIT CUSTOMER MODAL ── -->
<div class="overlay" id="editModal">
  <div class="modal">
    <div class="modal-title">✏️ Edit Customer</div>
    <div id="editMsg"></div>
    <input type="hidden" id="eId">
    <div class="modal-grid">
      <div class="form-group">
        <label class="form-label">Full Name *</label>
        <input type="text" class="form-input" id="eName">
      </div>
      <div class="form-group">
        <label class="form-label">Phone Number</label>
        <input type="tel" class="form-input" id="ePhone" disabled style="opacity:.5">
      </div>
      <div class="form-group full">
        <label class="form-label">Address *</label>
        <textarea class="form-textarea" id="eAddress"></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Email</label>
        <input type="email" class="form-input" id="eEmail">
      </div>
    </div>
    <div class="modal-actions">
      <button class="btn-cancel" onclick="closeAll()">Cancel</button>
      <button class="btn-submit" onclick="submitEdit()">Save Changes</button>
    </div>
  </div>
</div>

<!-- ── DETAIL / MANAGE MODAL ── -->
<div class="overlay" id="detailModal">
  <div class="modal">
    <div class="modal-title" id="detailTitle">Customer Details</div>
    <div id="detailBody"></div>
    <div class="modal-actions" id="detailActions"></div>
  </div>
</div>

<!-- ── CONFIRM DEACTIVATE MODAL ── -->
<div class="overlay" id="confirmModal">
  <div class="modal sm">
    <div class="modal-title" id="confirmTitle">Confirm Action</div>
    <p id="confirmMsg" style="font-size:14px;color:var(--muted);margin-bottom:20px;line-height:1.6"></p>
    <div class="modal-actions">
      <button class="btn-cancel" onclick="closeAll()">Cancel</button>
      <button class="btn-danger" id="confirmBtn">Confirm</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>
<script src="../assets/js/admin-customers.js"></script>
</body>
</html>
