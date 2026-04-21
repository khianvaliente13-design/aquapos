<?php
require_once 'includes/config.php';
startCashierSession();
require_once 'includes/auth.php';
requireRole('cashier', 'delivery');
$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS — AquaStation</title>
    <link rel="stylesheet" href="assets/css/pos.css">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,400&display=swap" rel="stylesheet">
</head>
<body>

<!-- TOP BAR -->
<div class="topbar">
    <div class="topbar-brand">
        <div class="brand-icon">💧</div>
        <span class="brand-name">AquaStation</span>
    </div>

    <div class="topbar-nav">
        <a href="pos.php" class="nav-btn active">🛒 POS</a>
    </div>

    <div class="topbar-right">
        <div id="priceUpdateBanner" style="display:none;padding:5px 12px;background:rgba(255,204,0,.15);border:1px solid rgba(255,204,0,.3);border-radius:20px;font-size:12px;color:#ffcc00;animation:fadeIn .4s ease">
            🔄 Prices updated by Admin
        </div>
        <div style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--muted);padding:5px 12px;background:var(--surface2);border:1px solid var(--border);border-radius:20px;">
            <div id="syncDot" style="width:7px;height:7px;border-radius:50%;background:var(--green);animation:pulse 2s infinite"></div>
            <span id="syncLabel">Live</span>
        </div>
        <div class="datetime">
            <div class="time" id="clock">--:--:--</div>
            <div id="dateDisplay">--</div>
        </div>
        <div class="user-badge">
            <div class="user-avatar"><?= strtoupper(substr($user['full_name'], 0, 1)) ?></div>
            <span><?= htmlspecialchars($user['full_name']) ?></span>
            <span style="color:var(--muted);font-size:11px;"><?= ucfirst($user['role']) ?></span>
        </div>
        <a href="logout.php?portal=cashier" class="logout-btn">Sign out</a>
    </div>
</div>

<!-- MAIN LAYOUT -->
<div class="pos-layout">

    <!-- LEFT: Products -->
    <div class="products-panel">
        <div class="panel-header">
            <div class="search-box">
                <span class="search-icon">🔍</span>
                <input type="text" id="searchInput" placeholder="Search products…">
            </div>
        </div>

        <div class="category-tabs" id="categoryTabs">
            <div class="cat-tab active" data-id="" onclick="setCategory('', this)">All Products</div>
        </div>

        <div class="products-grid" id="productsGrid">
            <div class="loading"><div class="spinner"></div> Loading products…</div>
        </div>
    </div>

    <!-- RIGHT: Cart -->
    <div class="cart-panel">
        <div class="cart-header">
            <div style="display:flex;align-items:center;gap:10px;">
                <h2>Cart</h2>
                <span class="cart-count" id="cartCount">0</span>
            </div>
            <button class="clear-cart" onclick="clearCart()">Clear all</button>
        </div>

        <div class="customer-row">
            <div class="customer-label">Customer</div>
            <div style="position:relative">
                <input type="text" id="customerSearch" class="customer-select"
                    placeholder="🔍 Search by name or phone…"
                    oninput="searchCustomers(this.value)" autocomplete="off">
                <div id="customerDropdown" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:200;background:var(--surface2);border:1px solid rgba(0,212,255,0.25);border-radius:10px;margin-top:4px;overflow:hidden;box-shadow:0 8px 24px rgba(0,0,0,0.4)"></div>
            </div>
            <!-- Selected customer chip — compact single row -->
            <div id="selectedCustomer" style="display:none;margin-top:6px;padding:7px 10px;background:var(--cyan-dim);border:1px solid rgba(0,212,255,0.25);border-radius:10px">
                <div style="display:flex;align-items:center;gap:8px">
                    <div style="flex:1;min-width:0">
                        <span style="font-size:13px;font-weight:600;color:var(--text)" id="selName"></span>
                        <span style="font-size:11px;color:var(--muted);margin-left:6px" id="selPhone"></span>
                    </div>
                    <span style="font-size:11px;color:var(--yellow);white-space:nowrap">⭐ <span id="selPoints">0</span> pts</span>
                    <input type="number" id="pointsToUse" min="0" value="0"
                        placeholder="pts"
                        style="width:56px;padding:3px 6px;background:var(--surface);border:1px solid rgba(255,204,0,.3);border-radius:6px;font-size:12px;color:var(--yellow);text-align:center;outline:none"
                        oninput="applyLoyalty()">
                    <button onclick="clearCustomer()" style="background:none;border:none;color:var(--muted);cursor:pointer;font-size:14px;padding:0;flex-shrink:0">✕</button>
                </div>
                <div id="loyaltyDiscount" style="display:none;margin-top:4px;font-size:11px;color:var(--green);text-align:right"></div>
            </div>
        </div>

        <div class="cart-items" id="cartItems">
            <div class="empty-cart">
                <div class="icon">🛒</div>
                <p>Cart is empty<br>Tap a product to add</p>
            </div>
        </div>

        <div class="cart-footer">
            <div class="totals-row">
                <span>Subtotal</span>
                <span id="subtotalDisplay">₱0.00</span>
            </div>

            <div class="discount-row">
                <span class="discount-label">Discount (₱)</span>
                <input type="number" class="discount-input" id="discountInput"
                    placeholder="0.00" min="0" value="0">
            </div>

            <div class="totals-row total-line">
                <span>Total</span>
                <span id="totalDisplay">₱0.00</span>
            </div>

            <div class="payment-methods" id="paymentMethods">
                <div class="pay-method active" data-method="cash">💵 Cash</div>
            </div>

            <div class="quick-amounts" id="quickAmounts">
                <div class="quick-amt" onclick="setExact()">Exact</div>
                <div class="quick-amt" onclick="setQuick(100)">₱100</div>
                <div class="quick-amt" onclick="setQuick(200)">₱200</div>
                <div class="quick-amt" onclick="setQuick(500)">₱500</div>
                <div class="quick-amt" onclick="setQuick(1000)">₱1K</div>
            </div>

            <div class="amount-paid-row">
                <span class="amount-paid-label">Amount Paid</span>
                <input type="number" class="amount-paid-input" id="amountPaidInput"
                    placeholder="0.00" min="0">
            </div>

            <div class="change-display">
                <span class="change-label">Change</span>
                <span class="change-amount" id="changeDisplay">₱0.00</span>
            </div>

            <button class="checkout-btn" id="checkoutBtn" onclick="processCheckout()" disabled>
                Process Payment →
            </button>
        </div>
    </div>
</div>

<!-- RECEIPT MODAL -->
<div class="success-screen" id="successScreen">
    <div class="success-wrap">
        <div class="check-circle"><span class="check-icon">✓</span></div>
        <div class="success-title">Payment Successful!</div>
        <div class="success-subtitle" id="successSubtitle">Transaction completed</div>

        <!-- Reference number -->
        <div class="ref-box">
            <div class="ref-label">Reference Number</div>
            <div class="ref-number" id="refNumber">—</div>
            <div class="ref-date" id="refDate">—</div>
            <div class="ref-cashier" id="refCashier">—</div>
        </div>

        <!-- Points earned badge (hidden if no points) -->
        <div class="pts-earned-badge" id="ptsEarnedBadge" style="display:none">
            ⭐ <span id="ptsEarnedText"></span>
        </div>

        <!-- Full receipt -->
        <div class="receipt-card">
            <div class="receipt-card-header">
                <h3>💧 AquaStation</h3>
                <p>Water Refilling Station</p>
            </div>
            <div class="receipt-body" id="receiptBody">
                <!-- Filled by JS -->
            </div>
            <div class="receipt-footer" id="receiptFooter">
                Thank you for your purchase!
            </div>
        </div>

        <!-- Action buttons -->
        <div class="success-actions">
            <button class="btn-print" onclick="printReceipt()">🖨 Print</button>
            <button class="btn-new-tx" onclick="newTransaction()">New Transaction →</button>
        </div>
    </div>
</div>

<!-- TOAST -->
<div class="toast" id="toast"></div>
<script src="assets/js/pos.js"></script>
</body>
</html>
