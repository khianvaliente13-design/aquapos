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
<title>Products & Prices — AquaStation Admin</title>
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
    <a href="products.php" class="nav-btn active">📦 Products</a>
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
    <a href="dashboard.php" class="sidebar-link"><span class="s-icon">📊</span> Dashboard</a>
    <a href="products.php" class="sidebar-link active"><span class="s-icon">📦</span> Products</a>
    <a href="customers.php" class="sidebar-link"><span class="s-icon">👥</span> Customers</a>
    <a href="transactions.php" class="sidebar-link"><span class="s-icon">🧾</span> Transactions</a>
    <div class="sidebar-section">Account</div>
    <a href="../logout.php?portal=admin" class="sidebar-link"><span class="s-icon">🚪</span> Sign Out</a>
  </div>

  <div class="main-content">
    <div class="page-header">
      <div>
        <div class="page-title">Products &amp; Prices</div>
        <div class="page-sub">Click any price to edit it instantly — changes sync to POS in real-time</div>
      </div>
      <div class="sync-indicator">
        <div class="sync-dot"></div>
        <span id="syncLabel">Live sync active</span>
      </div>
    </div>

    <div class="toolbar">
      <div class="search-box">
        <span class="search-icon">🔍</span>
        <input type="text" id="searchInput" placeholder="Search products…">
      </div>
      <select class="filter-select" id="catFilter"><option value="">All Categories</option></select>
      <select class="filter-select" id="statusFilter">
        <option value="">All Status</option>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
      </select>
      <button class="btn-add" onclick="openAddModal()">+ Add Product</button>
    </div>

    <div class="products-table-wrap">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Product Name</th>
            <th>Category</th>
            <th>Price (Click to Edit)</th>
            <th>Stock</th>
            <th>Unit</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="productsTbody">
          <tr class="empty-row"><td colspan="8"><div class="spin" style="margin:auto"></div></td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ADD PRODUCT MODAL -->
<div class="modal-overlay" id="addModal">
  <div class="modal">
    <div class="modal-title">📦 Add New Product</div>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Product Name *</label>
        <input type="text" class="form-input" id="addName" placeholder="e.g. 5-Gallon Purified">
      </div>
      <div class="form-group">
        <label class="form-label">Category</label>
        <select class="form-select" id="addCategory"></select>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Price (₱) *</label>
        <input type="number" class="form-input" id="addPrice" placeholder="0.00" min="0" step="0.01">
      </div>
      <div class="form-group">
        <label class="form-label">Initial Stock</label>
        <input type="number" class="form-input" id="addStock" placeholder="0" min="0">
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Unit</label>
      <select class="form-select" id="addUnit">
        <option value="gallon">Gallon</option>
        <option value="bottle">Bottle</option>
        <option value="piece">Piece</option>
        <option value="pack">Pack</option>
      </select>
    </div>
    <div class="modal-actions">
      <button class="btn-cancel" onclick="closeModal('addModal')">Cancel</button>
      <button class="btn-submit" onclick="submitAdd()">Add Product</button>
    </div>
  </div>
</div>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="editModal">
  <div class="modal">
    <div class="modal-title">✏️ Edit Product</div>
    <input type="hidden" id="editId">
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Product Name *</label>
        <input type="text" class="form-input" id="editName">
      </div>
      <div class="form-group">
        <label class="form-label">Price (₱) *</label>
        <input type="number" class="form-input" id="editPrice" min="0" step="0.01">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Stock</label>
        <input type="number" class="form-input" id="editStock" min="0">
      </div>
      <div class="form-group">
        <label class="form-label">Status</label>
        <select class="form-select" id="editStatus">
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>
    </div>
    <div class="modal-actions">
      <button class="btn-cancel" onclick="closeModal('editModal')">Cancel</button>
      <button class="btn-submit" onclick="submitEdit()">Save Changes</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>
<script src="../assets/js/admin-products.js"></script>
</body>
</html>
