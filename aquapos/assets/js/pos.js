// ── STATE ──────────────────────────────────────────
let cart = [];
let categories = [];
let products = [];
let currentCategory = '';
let searchQuery = '';
let paymentMethod = 'cash';
let lastTransaction = null;
let lastTxData = null;   // full transaction + items stored for printing
let refills = [];        // refill options loaded from API
let currentMode = 'products'; // 'products' | 'refill'
let lastRefillVersion = null;
let lastPriceVersion = null;
let selectedCustomer = null;  // { id, name, phone, loyalty_points }
let loyaltyDiscount  = 0;
let loyaltyPointsUsed = 0;

// ── INIT ───────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    updateClock();
    setInterval(updateClock, 1000);
    loadCategories();
    loadProducts();
    loadRefills();

    // ── Real-time price sync (poll every 12 seconds) ──
    setInterval(checkPriceUpdates, 12000);
    setInterval(checkRefillUpdates, 12000);
    checkPriceUpdates();

    document.getElementById('searchInput').addEventListener('input', e => {
        searchQuery = e.target.value;
        renderProducts();
    });

    document.getElementById('discountInput').addEventListener('input', updateTotals);
    document.getElementById('amountPaidInput').addEventListener('input', updateChange);

    // Payment method is always cash — no listener needed
});

function updateClock() {
    const now = new Date();
    document.getElementById('clock').textContent = now.toLocaleTimeString('en-PH');
    document.getElementById('dateDisplay').textContent =
        now.toLocaleDateString('en-PH', { weekday:'short', month:'short', day:'numeric', year:'numeric' });
}

// ── REAL-TIME PRICE SYNC ────────────────────────────
async function checkPriceUpdates() {
    try {
        const res  = await fetch('api/pos.php?action=price_version');
        const json = await res.json();
        if (!json.success) return;
        const ts = json.ts;
        if (lastPriceVersion === null) {
            lastPriceVersion = ts; // first load — just store baseline
            return;
        }
        if (ts !== lastPriceVersion) {
            lastPriceVersion = ts;
            await loadProducts(); // refresh all product prices
            showPriceUpdateBanner();
        }
    } catch (e) {
        // Silently fail — no network disruption to cashier
        document.getElementById('syncDot').style.background = 'var(--yellow)';
        document.getElementById('syncLabel').textContent = 'Reconnecting…';
        setTimeout(() => {
            document.getElementById('syncDot').style.background = 'var(--green)';
            document.getElementById('syncLabel').textContent = 'Live';
        }, 3000);
    }
}

function showPriceUpdateBanner() {
    const banner = document.getElementById('priceUpdateBanner');
    banner.style.display = 'block';
    setTimeout(() => { banner.style.display = 'none'; }, 5000);
    showToast('Admin updated prices — cart refreshed!', 'success');
    // Also update cart prices to new values
    cart.forEach(item => {
        const updated = products.find(p => p.id == item.product_id);
        if (updated) item.price = parseFloat(updated.price);
    });
    renderCart();
}

// ── CUSTOMER SEARCH ─────────────────────────────────
let searchTimer = null;
async function searchCustomers(q) {
    clearTimeout(searchTimer);
    const dd = document.getElementById('customerDropdown');
    if (q.length < 2) { dd.style.display = 'none'; return; }
    searchTimer = setTimeout(async () => {
        const res  = await fetch(`api/customer.php?action=search&q=${encodeURIComponent(q)}`);
        const json = await res.json();
        if (!json.data.length) {
            dd.innerHTML = `<div style="padding:12px 14px;font-size:13px;color:var(--muted)">No customers found</div>`;
        } else {
            dd.innerHTML = json.data.map(c => `
                <div onclick="selectCustomer(${c.id},'${c.name.replace(/'/g,"\\'")}','${c.phone}',${c.loyalty_points})"
                    style="padding:11px 14px;cursor:pointer;border-bottom:1px solid var(--border);transition:background .15s"
                    onmouseover="this.style.background='var(--surface)'" onmouseout="this.style.background=''">
                    <div style="font-size:13px;font-weight:600;color:var(--text)">${c.name}</div>
                    <div style="font-size:11px;color:var(--muted);margin-top:2px">${c.phone} · ⭐ ${c.loyalty_points} pts</div>
                </div>`).join('');
        }
        dd.style.display = 'block';
    }, 300);
}

function selectCustomer(id, name, phone, points) {
    selectedCustomer = { id, name, phone, loyalty_points: points };
    document.getElementById('customerSearch').value = '';
    document.getElementById('customerDropdown').style.display = 'none';
    document.getElementById('selectedCustomer').style.display = 'block';
    document.getElementById('selName').textContent  = name;
    document.getElementById('selPhone').textContent = phone;
    document.getElementById('selPoints').textContent = points;
    document.getElementById('pointsToUse').max = points;
    loyaltyDiscount = 0; loyaltyPointsUsed = 0;
    document.getElementById('pointsToUse').value = 0;
    document.getElementById('loyaltyDiscount').style.display = 'none';
    updateTotals();
}

function clearCustomer() {
    selectedCustomer = null;
    loyaltyDiscount  = 0;
    loyaltyPointsUsed = 0;
    document.getElementById('customerSearch').value = '';
    document.getElementById('selectedCustomer').style.display = 'none';
    document.getElementById('loyaltyDiscount').style.display  = 'none';
    updateTotals();
}

async function applyLoyalty() {
    if (!selectedCustomer) return;
    const pts = parseInt(document.getElementById('pointsToUse').value) || 0;
    if (pts <= 0) {
        loyaltyDiscount   = 0;
        loyaltyPointsUsed = 0;
        document.getElementById('loyaltyDiscount').style.display = 'none';
        updateTotals();
        return;
    }

    const subtotal = cart.reduce((s,i) => s + i.price * i.quantity, 0);
    const disc     = parseFloat(document.getElementById('discountInput').value) || 0;
    const total    = Math.max(0, subtotal - disc);

    const res  = await fetch(`api/customer.php?action=calc_loyalty&customer_id=${selectedCustomer.id}&total=${total}&points_to_use=${pts}`);
    const json = await res.json();
    if (!json.success) return;

    loyaltyDiscount   = json.discount;
    loyaltyPointsUsed = json.points_used;

    const ldEl = document.getElementById('loyaltyDiscount');
    ldEl.textContent   = `Using ${json.points_used} pts → −₱${json.discount.toFixed(2)} off`;
    ldEl.style.display = 'block';

    // Just recalculate totals and change — do NOT auto-fill amount paid
    updateTotals();
}

// Close dropdown when clicking outside
document.addEventListener('click', e => {
    if (!e.target.closest('#customerSearch') && !e.target.closest('#customerDropdown')) {
        document.getElementById('customerDropdown').style.display = 'none';
    }
});

// ── DATA LOADING ───────────────────────────────────
async function loadCategories() {
    try {
        const res = await fetch('api/pos.php?action=get_categories');
        const json = await res.json();
        if (!json.success) return;
        categories = json.data;

        const tabs = document.getElementById('categoryTabs');
        categories.forEach(cat => {
            const tab = document.createElement('div');
            tab.className = 'cat-tab';
            tab.dataset.id = cat.id;
            tab.textContent = cat.name;
            tab.onclick = () => setCategory(cat.id, tab);
            tabs.appendChild(tab);
        });
    } catch(e) {
        console.error('loadCategories error:', e);
    }
}

async function loadProducts() {
    try {
        const params = new URLSearchParams({ action: 'get_products' });
        if (currentCategory) params.set('category', currentCategory);
        if (searchQuery)     params.set('search',   searchQuery);

        const res  = await fetch('api/pos.php?' + params);
        const json = await res.json();
        if (!json.success) {
            document.getElementById('productsGrid').innerHTML =
                `<div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--red)">
                    Failed to load products: ${json.message || 'Unknown error'}</div>`;
            return;
        }
        products = json.data;
        renderProducts();
    } catch(e) {
        document.getElementById('productsGrid').innerHTML =
            `<div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--red)">
                Connection error. Please refresh the page.</div>`;
        console.error('loadProducts error:', e);
    }
}

// ── MODE SWITCH ─────────────────────────────────────
function switchMode(mode) {
    currentMode = mode;
    document.getElementById('productsModePanel').style.display = mode === 'products' ? '' : 'none';
    document.getElementById('refillModePanel').style.display   = mode === 'refill'   ? '' : 'none';
    document.getElementById('modeProducts').classList.toggle('active', mode === 'products');
    document.getElementById('modeRefill').classList.toggle('active', mode === 'refill');
}

// ── REFILL LOADING ───────────────────────────────────
async function loadRefills() {
    try {
        const res  = await fetch('api/pos.php?action=get_refills');
        const json = await res.json();
        if (!json.success) return;
        refills = json.data;
        renderRefills();
    } catch(e) {
        document.getElementById('refillGrid').innerHTML =
            `<div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--red)">
                Connection error loading refills.</div>`;
    }
}

// ── REFILL RENDERING ─────────────────────────────────
function renderRefills() {
    const grid = document.getElementById('refillGrid');
    if (!refills.length) {
        grid.innerHTML = `<div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--muted)">
            No refill options available.</div>`;
        return;
    }
    grid.innerHTML = refills.map(r => `
        <div class="refill-card" onclick="addRefillToCart(${r.id})">
            <div class="r-badge">REFILL</div>
            <div class="r-icon">💧</div>
            <div class="r-name">${r.name}</div>
            <div class="r-desc">${r.description || ''}</div>
            <div class="r-price">₱${parseFloat(r.price).toFixed(2)}</div>
        </div>`).join('');
}

// ── ADD REFILL TO CART ───────────────────────────────
function addRefillToCart(refillId) {
    const r = refills.find(r => r.id == refillId);
    if (!r) return;
    const cartKey = 'refill_' + r.id;
    const existing = cart.find(i => i.product_id === cartKey);
    if (existing) {
        existing.quantity++;
    } else {
        cart.push({
            product_id: cartKey,
            name:       r.name,
            price:      parseFloat(r.price),
            quantity:   1,
            icon:       '💧',
            isRefill:   true,
        });
    }
    renderCart();
    showToast(`${r.name} added`, 'success');
}

// ── REFILL PRICE SYNC ────────────────────────────────
async function checkRefillUpdates() {
    try {
        const res  = await fetch('api/pos.php?action=refill_version');
        const json = await res.json();
        if (lastRefillVersion && json.ts !== lastRefillVersion) {
            await loadRefills();
        }
        lastRefillVersion = json.ts;
    } catch(e) {}
}

// ── PRODUCT RENDERING ──────────────────────────────
function setCategory(id, el) {
    currentCategory = id;
    document.querySelectorAll('.cat-tab').forEach(t => t.classList.remove('active'));
    if (el) el.classList.add('active');
    renderProducts();
}

const PRODUCT_ICONS = {
    'slim':      '💧',
    'round':     '🫙',
    'gallon':    '🪣',
    'mineral':   '💎',
    'container': '🗂️',
    'pump':      '⚙️',
    'default':   '🚰',
};

function getProductIcon(name) {
    const n = name.toLowerCase();
    if (n.includes('slim'))      return '🧴';
    if (n.includes('gallon'))    return '🪣';
    if (n.includes('mineral'))   return '💎';
    if (n.includes('round'))     return '🫙';
    if (n.includes('container')) return '🗃️';
    if (n.includes('pump'))      return '⚙️';
    return '💧';
}

function renderProducts() {
    const grid = document.getElementById('productsGrid');
    const q = searchQuery.toLowerCase();

    let filtered = products.filter(p => {
        const matchCat = !currentCategory || p.category_id == currentCategory;
        const matchSearch = !q || p.name.toLowerCase().includes(q);
        return matchCat && matchSearch;
    });

    if (filtered.length === 0) {
        grid.innerHTML = `<div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--muted)">
            No products found.</div>`;
        return;
    }

    grid.innerHTML = filtered.map(p => {
        const oos = p.stock <= 0;
        const lowStock = p.stock > 0 && p.stock <= 10;
        const stockClass = oos ? 'stock-out' : (lowStock ? 'stock-low' : '');
        const stockText = oos ? 'Out of stock' : `${p.stock} ${p.unit}s left`;
        return `
        <div class="product-card ${oos ? 'out-of-stock' : ''}"
             onclick="${oos ? '' : `addToCart(${JSON.stringify(p).replace(/"/g,"'")})`}">
            <div class="product-icon">${getProductIcon(p.name)}</div>
            <div class="product-name">${p.name}</div>
            <div class="product-price">₱${parseFloat(p.price).toFixed(2)}</div>
            <div class="product-stock ${stockClass}">${stockText}</div>
        </div>`;
    }).join('');
}

// ── CART ────────────────────────────────────────────
function addToCart(product) {
    const existing = cart.find(i => i.product_id == product.id);
    if (existing) {
        existing.quantity++;
    } else {
        cart.push({
            product_id: product.id,
            name: product.name,
            price: parseFloat(product.price),
            quantity: 1,
            icon: getProductIcon(product.name),
        });
    }
    renderCart();
    showToast(`${product.name} added`, 'success');
}

function updateQty(productId, delta) {
    const item = cart.find(i => i.product_id == productId);
    if (!item) return;
    item.quantity += delta;
    if (item.quantity <= 0) {
        cart = cart.filter(i => i.product_id != productId);
    }
    renderCart();
}

function removeItem(productId) {
    cart = cart.filter(i => i.product_id != productId);
    renderCart();
}

function clearCart() {
    cart = [];
    renderCart();
}

function renderCart() {
    const container = document.getElementById('cartItems');
    document.getElementById('cartCount').textContent = cart.reduce((s,i) => s+i.quantity, 0);

    if (cart.length === 0) {
        container.innerHTML = `<div class="empty-cart">
            <div class="icon">🛒</div>
            <p>Cart is empty<br>Tap a product to add</p>
        </div>`;
        updateTotals();
        return;
    }

    container.innerHTML = cart.map(item => {
        const pid   = JSON.stringify(String(item.product_id)); // safe for onclick
        const badge = item.isRefill
            ? `<span style="font-size:9px;font-weight:700;padding:2px 5px;border-radius:4px;background:rgba(0,229,160,.15);color:var(--green);margin-left:5px;vertical-align:middle">REFILL</span>`
            : '';
        return `
        <div class="cart-item">
            <div class="cart-item-icon">${item.icon || '📦'}</div>
            <div class="cart-item-info">
                <div class="cart-item-name">${item.name}${badge}</div>
                <div class="cart-item-price">₱${item.price.toFixed(2)} each</div>
            </div>
            <div class="qty-controls">
                <button class="qty-btn" onclick="updateQty(${pid}, -1)">−</button>
                <span class="qty-num">${item.quantity}</span>
                <button class="qty-btn" onclick="updateQty(${pid}, 1)">+</button>
            </div>
            <span class="item-total">₱${(item.price * item.quantity).toFixed(2)}</span>
            <button class="remove-item" onclick="removeItem(${pid})">✕</button>
        </div>`;
    }).join('');

    updateTotals();
}

function updateTotals() {
    const subtotal = cart.reduce((s, i) => s + i.price * i.quantity, 0);
    const discount = parseFloat(document.getElementById('discountInput').value) || 0;
    const total    = Math.max(0, subtotal - discount - loyaltyDiscount);

    document.getElementById('subtotalDisplay').textContent = `₱${subtotal.toFixed(2)}`;
    document.getElementById('totalDisplay').textContent    = `₱${total.toFixed(2)}`;

    updateChange();
}

function updateChange() {
    const discount   = parseFloat(document.getElementById('discountInput').value) || 0;
    const subtotal   = cart.reduce((s, i) => s + i.price * i.quantity, 0);
    const total      = Math.max(0, subtotal - discount - loyaltyDiscount);
    const rawInput   = document.getElementById('amountPaidInput').value.trim();
    const hasInput   = rawInput !== '' && rawInput !== '0';
    const amountPaid = parseFloat(rawInput) || 0;
    const change     = amountPaid - total;

    const changeEl = document.getElementById('changeDisplay');

    if (!hasInput) {
        // Nothing typed yet — just show 0.00 neutrally
        changeEl.textContent = '₱0.00';
        changeEl.classList.remove('negative');
    } else if (change < 0) {
        // Typed but not enough
        changeEl.textContent = `-₱${Math.abs(change).toFixed(2)}`;
        changeEl.classList.add('negative');
    } else {
        // Enough paid — show change
        changeEl.textContent = `₱${change.toFixed(2)}`;
        changeEl.classList.remove('negative');
    }

    const btn = document.getElementById('checkoutBtn');
    btn.disabled = cart.length === 0 || !hasInput || amountPaid < total;
}

function setExact() {
    const discount = parseFloat(document.getElementById('discountInput').value) || 0;
    const subtotal = cart.reduce((s,i) => s + i.price * i.quantity, 0);
    const total    = Math.max(0, subtotal - discount - loyaltyDiscount);
    document.getElementById('amountPaidInput').value = total.toFixed(2);
    updateChange();
}

function setQuick(amount) {
    const discount = parseFloat(document.getElementById('discountInput').value) || 0;
    const subtotal = cart.reduce((s,i) => s + i.price * i.quantity, 0);
    const total    = Math.max(0, subtotal - discount - loyaltyDiscount);
    // Round up to the given denomination
    const needed = Math.ceil(total / amount) * amount;
    document.getElementById('amountPaidInput').value = needed;
    updateChange();
}

// ── CHECKOUT ────────────────────────────────────────
async function processCheckout() {
    const discount   = parseFloat(document.getElementById('discountInput').value) || 0;
    const amountPaid = parseFloat(document.getElementById('amountPaidInput').value) || 0;

    const payload = {
        items: cart.map(i => ({
            product_id: i.isRefill ? null : i.product_id,
            name:       i.isRefill ? '[REFILL] ' + i.name : i.name,
            price:      i.price,
            quantity:   i.quantity,
            type:       i.isRefill ? 'refill' : 'product',
        })),
        customer_id:    selectedCustomer ? selectedCustomer.id : null,
        payment_method: paymentMethod,
        amount_paid:    amountPaid,
        discount:       discount,
        loyalty_discount: loyaltyDiscount,
        points_used:    loyaltyPointsUsed,
        type:           'walk-in',
    };

    const btn = document.getElementById('checkoutBtn');
    btn.disabled = true;
    btn.textContent = 'Processing…';

    try {
        const res  = await fetch('api/pos.php?action=process_transaction', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const json = await res.json();

        if (json.success) {
            lastTransaction = json;
            await showReceipt(json.transaction_id, json.code, json.total, json.change);
        } else {
            showToast(json.message || 'Transaction failed', 'error');
            btn.disabled = false;
            btn.textContent = 'Process Payment →';
        }
    } catch (e) {
        showToast('Network error', 'error');
        btn.disabled = false;
        btn.textContent = 'Process Payment →';
    }
}

async function showReceipt(transId, code, total, change) {
    try {
        const res  = await fetch(`api/pos.php?action=get_receipt&id=${transId}`);
        const json = await res.json();
        if (!json.success || !json.transaction) {
            // Fallback: show basic success screen without full receipt details
            document.getElementById('refNumber').textContent  = code;
            document.getElementById('refDate').textContent    = new Date().toLocaleString('en-PH');
            document.getElementById('refCashier').textContent = '';
            document.getElementById('successSubtitle').textContent = `₱${parseFloat(total).toFixed(2)} received — Change: ₱${parseFloat(change).toFixed(2)}`;
            document.getElementById('receiptBody').innerHTML  = '<p style="text-align:center;color:var(--muted);padding:16px">Receipt details unavailable</p>';
            document.getElementById('successScreen').classList.add('show');
            return;
        }

        const t     = json.transaction;
        const items = json.items || [];
        lastTxData  = { t, items }; // save for printReceipt
        const dt    = new Date(t.created_at);
        const dateStr = dt.toLocaleDateString('en-PH', { weekday:'short', year:'numeric', month:'short', day:'numeric' });
        const timeStr = dt.toLocaleTimeString('en-PH', { hour:'2-digit', minute:'2-digit', second:'2-digit' });

        // Reference box
        document.getElementById('refNumber').textContent  = t.transaction_code;
        document.getElementById('refDate').textContent    = dateStr + ' · ' + timeStr;
        document.getElementById('refCashier').textContent = 'Cashier: ' + (t.cashier_name || '—');

        // Subtitle
        document.getElementById('successSubtitle').textContent =
            `₱${parseFloat(total).toFixed(2)} received — Change: ₱${parseFloat(change).toFixed(2)}`;

        // Points earned badge
        if (parseInt(t.points_earned) > 0) {
            document.getElementById('ptsEarnedText').textContent = `+${t.points_earned} loyalty points earned!`;
            document.getElementById('ptsEarnedBadge').style.display = 'inline-flex';
        } else {
            document.getElementById('ptsEarnedBadge').style.display = 'none';
        }

        // Receipt items
        const itemsHtml = items.map(i => {
            const isRefill = i.product_name.startsWith('[REFILL]');
            const displayName = isRefill
                ? `<span style="font-size:9px;background:rgba(0,229,160,.15);color:var(--green);padding:1px 5px;border-radius:4px;margin-right:4px;font-weight:700">REFILL</span>${i.product_name.replace('[REFILL] ','')}`
                : i.product_name;
            return `
            <div class="receipt-item">
                <div>
                    <div>${displayName}</div>
                    <div class="item-n">₱${parseFloat(i.unit_price).toFixed(2)} × ${i.quantity}</div>
                </div>
                <div style="font-weight:600">₱${parseFloat(i.subtotal).toFixed(2)}</div>
            </div>`;
        }).join('');

        const disc    = parseFloat(t.discount) || 0;
        const ldisc   = parseFloat(t.loyalty_discount) || 0;
        const total   = parseFloat(t.total);

        // Philippine VAT computation: 12% VAT inclusive
        // VAT-able amount = total / 1.12
        // VAT = total - VAT-able amount
        const vatRate    = 0.12;
        const vatExempt  = total / (1 + vatRate);
        const vatAmount  = total - vatExempt;

        document.getElementById('receiptBody').innerHTML = `
            ${itemsHtml}
            <hr class="receipt-divider">
            <div class="receipt-row bold"><span>Subtotal</span><span>₱${parseFloat(t.subtotal).toFixed(2)}</span></div>
            ${disc  > 0 ? `<div class="receipt-row"><span>Discount</span><span style="color:var(--yellow)">−₱${disc.toFixed(2)}</span></div>` : ''}
            ${ldisc > 0 ? `<div class="receipt-row"><span>Points discount (${t.points_used} pts)</span><span style="color:var(--yellow)">−₱${ldisc.toFixed(2)}</span></div>` : ''}
            <div class="receipt-row total"><span>TOTAL</span><span>₱${total.toFixed(2)}</span></div>
            <hr class="receipt-divider">
            <div class="receipt-row" style="font-size:11px"><span>VAT-able Amount (excl. VAT)</span><span>₱${vatExempt.toFixed(2)}</span></div>
            <div class="receipt-row" style="font-size:11px"><span>VAT 12%</span><span>₱${vatAmount.toFixed(2)}</span></div>
            <div class="receipt-row" style="font-size:11px"><span>VAT-Exempt Sales</span><span>₱0.00</span></div>
            <div class="receipt-row" style="font-size:11px"><span>Zero-Rated Sales</span><span>₱0.00</span></div>
            <hr class="receipt-divider">
            <div class="receipt-row"><span>Amount Paid (Cash)</span><span>₱${parseFloat(t.amount_paid).toFixed(2)}</span></div>
            <div class="receipt-row change-row"><span>Change</span><span>₱${parseFloat(t.change_amount).toFixed(2)}</span></div>
            ${t.customer_name && t.customer_name !== 'Walk-in' ? `<hr class="receipt-divider"><div class="receipt-row" style="font-size:11px"><span>Customer</span><span>${t.customer_name}</span></div>` : ''}
            ${parseInt(t.points_earned) > 0 ? `<div class="receipt-row" style="font-size:11px;color:var(--yellow)"><span>Points Earned</span><span>+${t.points_earned} pts</span></div>` : ''}
        `;

        document.getElementById('receiptFooter').innerHTML =
            `Cashier: ${t.cashier_name || '—'} &nbsp;·&nbsp; ${dateStr}`;

        document.getElementById('successScreen').classList.add('show');

    } catch(e) {
        // Even if receipt fetch fails, still show the success screen with basic info
        document.getElementById('refNumber').textContent  = code;
        document.getElementById('refDate').textContent    = new Date().toLocaleString('en-PH');
        document.getElementById('refCashier').textContent = '';
        document.getElementById('successSubtitle').textContent = `₱${parseFloat(total).toFixed(2)} received — Change: ₱${parseFloat(change).toFixed(2)}`;
        document.getElementById('receiptBody').innerHTML  = '<p style="text-align:center;color:var(--muted);padding:16px">Transaction saved successfully.</p>';
        document.getElementById('successScreen').classList.add('show');
    }
}

function printReceipt() {
    // Use stored transaction data — never rely on DOM scraping
    if (!lastTxData) {
        alert('No receipt data available. Please process a transaction first.');
        return;
    }
    const { t, items } = lastTxData;

    const ref     = t.transaction_code;
    const cashier = t.cashier_name || '—';
    const dt      = new Date(t.created_at);
    const dateStr = dt.toLocaleDateString('en-PH', { weekday:'short', year:'numeric', month:'short', day:'numeric' });
    const timeStr = dt.toLocaleTimeString('en-PH', { hour:'2-digit', minute:'2-digit', second:'2-digit' });

    const subtotal = parseFloat(t.subtotal)          || 0;
    const disc     = parseFloat(t.discount)          || 0;
    const ldisc    = parseFloat(t.loyalty_discount)  || 0;
    const total    = parseFloat(t.total)             || 0;
    const paid     = parseFloat(t.amount_paid)       || 0;
    const change   = parseFloat(t.change_amount)     || 0;
    const ptsUsed  = parseInt(t.points_used)         || 0;
    const ptsEarned= parseInt(t.points_earned)       || 0;
    const customer = (t.customer_name && t.customer_name !== 'Walk-in') ? t.customer_name : null;

    // PH VAT: 12% inclusive
    const vatExcl = total / 1.12;
    const vatAmt  = total - vatExcl;

    // Build items HTML
    const itemsHtml = items.map(i => {
        const isRefill    = i.product_name.startsWith('[REFILL]');
        const displayName = i.product_name.replace('[REFILL] ', '');
        const refillTag   = isRefill ? ' [REFILL]' : '';
        return `
        <div class="item-block">
            <div class="item-name">${displayName}${refillTag}</div>
            <div class="item-detail">
                <span class="item-qty">₱${parseFloat(i.unit_price).toFixed(2)} x ${i.quantity}</span>
                <span class="item-amount">₱${parseFloat(i.subtotal).toFixed(2)}</span>
            </div>
        </div>`;
    }).join('');

    const win = window.open('', '_blank', 'width=320,height=700');
    win.document.write(`<!DOCTYPE html>
<html>
<head>
<title>Receipt - ${ref}</title>
<style>
  @page { size: 80mm auto; margin: 0; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: 'Courier New', Courier, monospace;
    font-size: 11.5px;
    width: 80mm;
    padding: 5mm 4mm 14mm;
    color: #000;
    background: #fff;
  }
  .store-name   { font-size: 18px; font-weight: bold; text-align: center; letter-spacing: 1px; margin-bottom: 1px; }
  .store-sub    { font-size: 10.5px; text-align: center; margin-bottom: 1px; }
  .store-small  { font-size: 9.5px; text-align: center; color: #333; margin-bottom: 1px; }
  .store-tiny   { font-size: 9px;   text-align: center; color: #555; }
  .dash  { border: none; border-top: 1px dashed #666; margin: 5px 0; }
  .solid { border: none; border-top: 1px solid  #000; margin: 5px 0; }
  .eq    { border: none; border-top: 3px double  #000; margin: 6px 0; }
  .tx-row { display: flex; justify-content: space-between; font-size: 10px; padding: 1px 0; }
  .item-block   { margin: 4px 0; }
  .item-name    { font-weight: bold; font-size: 11.5px; word-break: break-word; }
  .item-detail  { display: flex; justify-content: space-between; font-size: 10.5px; padding-left: 8px; }
  .item-qty     { color: #333; }
  .item-amount  { font-weight: bold; }
  .row  { display: flex; justify-content: space-between; padding: 2px 0; font-size: 11px; }
  .bold { font-weight: bold; }
  .grand{ font-weight: bold; font-size: 14px; padding: 3px 0; }
  .sm   { font-size: 10px; color: #333; }
  .vat-row { display: flex; justify-content: space-between; padding: 1.5px 0; font-size: 10px; }
  .footer-msg   { text-align: center; font-size: 10.5px; margin-top: 7px; line-height: 1.6; }
  .footer-legal { text-align: center; font-size: 9px; color: #555; margin-top: 4px; line-height: 1.5; }
  .barcode { text-align: center; font-size: 30px; letter-spacing: -3px; margin: 8px 0 2px; line-height: 1; }
  .ref-bottom { text-align: center; font-size: 9px; color: #555; }
  @media print { body { width: 80mm; } }
</style>
</head>
<body>

  <div class="store-name">AquaStation</div>
  <div class="store-sub">Water Refilling Station</div>
  <div class="store-small">123 Sample St., Brgy. 1, Bacolod City</div>
  <div class="store-small">Tel: (034) 000-0000</div>
  <div class="store-small">VAT Reg TIN: 000-000-000-000</div>
  <div class="store-tiny">Permit No.: 2025-00001 &nbsp;|&nbsp; MIN: 0000000</div>

  <hr class="dash">

  <div class="tx-row"><span>SI No.:</span><span>${ref}</span></div>
  <div class="tx-row"><span>Date:</span><span>${dateStr}</span></div>
  <div class="tx-row"><span>Time:</span><span>${timeStr}</span></div>
  <div class="tx-row"><span>Cashier:</span><span>${cashier}</span></div>
  <div class="tx-row"><span>Type:</span><span>Walk-in / Cash</span></div>

  <hr class="solid">

  ${itemsHtml}

  <hr class="dash">

  <div class="row bold"><span>Subtotal</span><span>₱${subtotal.toFixed(2)}</span></div>
  ${disc  > 0 ? `<div class="row sm"><span>Discount</span><span>-₱${disc.toFixed(2)}</span></div>` : ''}
  ${ldisc > 0 ? `<div class="row sm"><span>Points Discount (${ptsUsed} pts)</span><span>-₱${ldisc.toFixed(2)}</span></div>` : ''}
  <div class="row grand"><span>TOTAL</span><span>₱${total.toFixed(2)}</span></div>

  <hr class="eq">

  <div class="vat-row"><span>VAT-able Amount (excl. VAT)</span><span>₱${vatExcl.toFixed(2)}</span></div>
  <div class="vat-row"><span>VAT Amount (12%)</span><span>₱${vatAmt.toFixed(2)}</span></div>
  <div class="vat-row"><span>VAT-Exempt Sales</span><span>₱0.00</span></div>
  <div class="vat-row"><span>Zero-Rated Sales</span><span>₱0.00</span></div>
  <div class="vat-row"><span>Discount</span><span>₱${(disc + ldisc).toFixed(2)}</span></div>

  <hr class="dash">

  <div class="row bold"><span>Amount Paid (Cash)</span><span>₱${paid.toFixed(2)}</span></div>
  <div class="row grand"><span>Change</span><span>₱${change.toFixed(2)}</span></div>

  ${customer ? `<hr class="dash"><div class="row sm"><span>Customer</span><span>${customer}</span></div>` : ''}
  ${ptsEarned > 0 ? `<div class="row sm"><span>Loyalty Points Earned</span><span>+${ptsEarned} pts</span></div>` : ''}

  <div class="footer-msg">
    ** Thank you for your purchase! **<br>
    Please come again.
  </div>
  <div class="footer-legal">
    This serves as your official receipt.<br>
    BIR Accreditation No.: 0000-0000-00000<br>
    For complaints: DTI Hotline 1-800-10-DTI-BAGO
  </div>
  <div class="barcode">||| |||| ||| || ||||</div>
  <div class="ref-bottom">${ref}</div>

</body>
</html>`);
    win.document.close();
    win.focus();
    setTimeout(() => win.print(), 400);
}

function newTransaction() {
    cart = [];
    document.getElementById('discountInput').value   = '0';
    document.getElementById('amountPaidInput').value = '';
    clearCustomer();
    paymentMethod = 'cash';
    renderCart();
    document.getElementById('successScreen').classList.remove('show');
    loadProducts();
    document.getElementById('checkoutBtn').textContent = 'Process Payment →';
    document.getElementById('checkoutBtn').disabled   = true;
}


// ── UTILS ──────────────────────────────────────────
function showToast(msg, type = 'success') {
    const toast = document.getElementById('toast');
    toast.textContent = (type === 'success' ? '✓ ' : '✗ ') + msg;
    toast.className = `toast show ${type}`;
    setTimeout(() => toast.classList.remove('show'), 3000);
}
