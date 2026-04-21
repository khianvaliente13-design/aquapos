<?php
require_once '../includes/config.php';
startAdminSession();
require_once '../includes/auth.php';
requireRole('admin');
$user = getCurrentUser();
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Refill Prices — AquaStation Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Syne:wght@400;700;800&family=DM+Sans:wght@400;500;600&display=swap">
<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-page">
<!-- TOPBAR -->
<div class="topbar">
  <div class="topbar-brand">
    <div class="brand-icon">💧</div>
    <span class="brand-name">AquaStation</span>
    <span class="brand-tag">ADMIN</span>
  </div>
  <nav class="topbar-nav">
    <a href="dashboard.php"    class="nav-btn">📊 Dashboard</a>
    <a href="products.php"     class="nav-btn">📦 Products</a>
    <a href="refills.php"      class="nav-btn active">💧 Refills</a>
    <a href="customers.php"    class="nav-btn">👥 Customers</a>
    <a href="transactions.php" class="nav-btn">🧾 Transactions</a>
  </nav>
  <div class="topbar-right">
    <div class="admin-badge">
      <div class="admin-avatar"><?= strtoupper(substr($user['full_name'],0,1)) ?></div>
      <span><?= htmlspecialchars($user['full_name']) ?></span>
    </div>
    <a href="../logout.php?portal=admin" class="logout-btn">Sign out</a>
  </div>
</div>

<div class="admin-layout">
  <!-- SIDEBAR -->
  <div class="sidebar">
    <div class="sidebar-section">Main</div>
    <a href="dashboard.php"    class="sidebar-link"><span class="s-icon">📊</span> Dashboard</a>
    <a href="products.php"     class="sidebar-link"><span class="s-icon">📦</span> Products</a>
    <a href="refills.php"      class="sidebar-link active"><span class="s-icon">💧</span> Refills</a>
    <a href="customers.php"    class="sidebar-link"><span class="s-icon">👥</span> Customers</a>
    <a href="transactions.php" class="sidebar-link"><span class="s-icon">🧾</span> Transactions</a>
    <div class="sidebar-section">Account</div>
    <a href="../logout.php?portal=admin" class="sidebar-link"><span class="s-icon">🚪</span> Sign Out</a>
  </div>

  <div class="main-content">
    <div class="page-header">
      <div>
        <div class="page-title">Refill Prices</div>
        <div class="page-sub">Click any price to edit — changes sync to POS in real-time</div>
      </div>
      <div style="display:flex;align-items:center;gap:16px">
        <div style="display:flex;align-items:center;gap:6px;font-size:13px;color:var(--green)">
          <div class="sync-dot"></div>
          <span id="syncLabel">Live sync active</span>
        </div>
        <button class="btn-add" onclick="openAddModal()">+ Add Refill Option</button>
      </div>
    </div>

    <div class="table-wrap">
      <table>
        <thead><tr>
          <th>#</th>
          <th>Container / Refill Name</th>
          <th>Description</th>
          <th>Price (click to edit)</th>
          <th>Status</th>
          <th>Actions</th>
        </tr></thead>
        <tbody id="refillsTbody">
          <tr class="empty-row"><td colspan="6"><div class="spin" style="margin:auto"></div></td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ADD REFILL MODAL -->
<div class="modal-overlay" id="addModal">
  <div class="modal sm">
    <div class="modal-title">💧 Add Refill Option <button class="modal-close" onclick="closeModal('addModal')">✕</button></div>
    <div class="form-group">
      <label class="form-label">Container / Name</label>
      <input type="text" id="addName" class="form-input" placeholder="e.g. 5-Gallon Refill">
    </div>
    <div class="form-group">
      <label class="form-label">Description (optional)</label>
      <input type="text" id="addDesc" class="form-input" placeholder="e.g. Standard round container">
    </div>
    <div class="form-group">
      <label class="form-label">Price (₱)</label>
      <input type="number" id="addPrice" class="form-input" min="0" step="0.01" placeholder="0.00">
    </div>
    <div class="modal-actions">
      <button class="btn-cancel" onclick="closeModal('addModal')">Cancel</button>
      <button class="btn-submit" onclick="submitAdd()">Add Refill Option</button>
    </div>
  </div>
</div>

<!-- EDIT REFILL MODAL -->
<div class="modal-overlay" id="editModal">
  <div class="modal sm">
    <div class="modal-title">✏️ Edit Refill Option <button class="modal-close" onclick="closeModal('editModal')">✕</button></div>
    <input type="hidden" id="editId">
    <div class="form-group">
      <label class="form-label">Container / Name</label>
      <input type="text" id="editName" class="form-input">
    </div>
    <div class="form-group">
      <label class="form-label">Description</label>
      <input type="text" id="editDesc" class="form-input">
    </div>
    <div class="form-group">
      <label class="form-label">Price (₱)</label>
      <input type="number" id="editPrice" class="form-input" min="0" step="0.01">
    </div>
    <div class="form-group">
      <label class="form-label">Status</label>
      <select id="editStatus" class="form-select">
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
      </select>
    </div>
    <div class="modal-actions">
      <button class="btn-cancel" onclick="closeModal('editModal')">Cancel</button>
      <button class="btn-submit" onclick="submitEdit()">Save Changes</button>
    </div>
  </div>
</div>

<div id="toast" class="toast"></div>
<script src="../assets/js/admin-refills.js"></script>
</body>
</html>
