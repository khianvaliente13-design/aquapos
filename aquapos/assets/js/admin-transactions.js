
let deleteId = null;

function confirmDelete(id, code) {
  deleteId = id;
  document.getElementById('deleteMsg').textContent =
    `Are you sure you want to permanently delete transaction ${code}? This cannot be undone.`;
  document.getElementById('deleteOverlay').classList.add('show');
}
function closeDelete() {
  document.getElementById('deleteOverlay').classList.remove('show');
  deleteId = null;
}
async function doDelete() {
  if (!deleteId) return;
  const fd = new FormData(); fd.append('id', deleteId);
  const res  = await fetch('../api/admin.php?action=delete_transaction', { method:'POST', body:fd });
  const json = await res.json();
  closeDelete();
  closeModal();
  if (json.success) {
    loadTransactions(currentPage);
  }
}
document.getElementById('deleteOverlay').addEventListener('click', e => {
  if (e.target === e.currentTarget) closeDelete();
});


let searchTimer = null;
let currentDetailId = null;

function confirmDeleteFromModal() {
  if (!currentDetailId) return;
  const code = document.getElementById('modalCode').textContent.replace('🧾 ', '');
  closeModal();
  confirmDelete(currentDetailId, code);
}

// ── LOAD TRANSACTIONS ────────────────────────────────
async function loadTransactions(page = 1) {
  currentPage = page;
  const search   = document.getElementById('searchInput').value.trim();
  const dateFrom = document.getElementById('dateFrom').value;
  const dateTo   = document.getElementById('dateTo').value;
  const status   = document.getElementById('statusFilter').value;

  const params = new URLSearchParams({
    action: 'get_transactions', page,
    search, date_from: dateFrom, date_to: dateTo, status
  });

  document.getElementById('tbody').innerHTML =
    '<tr><td colspan="10" class="empty"><div class="spin" style="margin:auto"></div></td></tr>';

  const res  = await fetch('../api/admin.php?' + params);
  const json = await res.json();
  if (!json.success) return;

  renderSummary(json.summary, json.total);
  renderTable(json.data);
  renderPagination(json.total, json.pages, page);
}

function renderSummary(s, total) {
  const rev  = parseFloat(s.revenue  || 0).toLocaleString('en-PH', {minimumFractionDigits:2});
  const disc = parseFloat(s.discounts|| 0).toLocaleString('en-PH', {minimumFractionDigits:2});
  document.getElementById('summaryRow').innerHTML = `
    <div class="sum-card sum-cyan"><div class="sum-label">Transactions</div><div class="sum-value">${s.count || 0}</div></div>
    <div class="sum-card sum-green"><div class="sum-label">Total Revenue</div><div class="sum-value">₱${rev}</div></div>
    <div class="sum-card sum-yellow"><div class="sum-label">Total Discounts</div><div class="sum-value">₱${disc}</div></div>
    <div class="sum-card sum-red"><div class="sum-label">Total Records</div><div class="sum-value">${total}</div></div>
  `;
}

function renderTable(rows) {
  const tbody = document.getElementById('tbody');
  if (!rows.length) {
    tbody.innerHTML = '<tr><td colspan="10" class="empty">No transactions found.</td></tr>';
    return;
  }

  tbody.innerHTML = rows.map(t => {
    const dt      = new Date(t.created_at);
    const date    = dt.toLocaleDateString('en-PH', {month:'short', day:'numeric', year:'numeric'});
    const time    = dt.toLocaleTimeString('en-PH', {hour:'2-digit', minute:'2-digit'});
    const items   = t.items?.length || 0;
    const disc    = parseFloat(t.discount) + parseFloat(t.loyalty_discount);

    return `<tr>
      <td><span class="tx-code">${t.transaction_code}</span></td>
      <td style="font-size:12px">${date}<br><span style="color:var(--muted)">${time}</span></td>
      <td>${t.customer_name}</td>
      <td style="color:var(--muted)">${t.cashier_name}</td>
      <td style="text-align:center;color:var(--muted)">${items}</td>
      <td>₱${parseFloat(t.subtotal).toFixed(2)}</td>
      <td style="color:${disc>0?'var(--yellow)':'var(--muted)'}">
        ${disc > 0 ? '−₱' + disc.toFixed(2) : '—'}
      </td>
      <td><span class="amount">₱${parseFloat(t.total).toFixed(2)}</span></td>
      <td><span class="badge badge-${t.status}">${t.status}</span></td>
      <td><button class="view-btn" onclick="viewDetail(${t.id})">👁 View</button>
          <button class="view-btn del-btn" onclick="confirmDelete(${t.id}, '${t.transaction_code}')" style="margin-left:4px;color:var(--red);border-color:rgba(255,77,109,.25)">🗑</button></td>
    </tr>`;
  }).join('');
}

function renderPagination(total, pages, current) {
  const pg = document.getElementById('pagination');
  const limit = 20;
  const from  = (current - 1) * limit + 1;
  const to    = Math.min(current * limit, total);

  document.getElementById('pageInfo').textContent = `Showing ${from}–${to} of ${total}`;

  if (pages <= 1) { pg.style.display = 'none'; return; }
  pg.style.display = 'flex';

  let btns = `<button class="page-btn" onclick="loadTransactions(${current-1})" ${current<=1?'disabled':''}>← Prev</button>`;

  // Show at most 5 page buttons around current
  let start = Math.max(1, current - 2);
  let end   = Math.min(pages, start + 4);
  if (end - start < 4) start = Math.max(1, end - 4);

  for (let i = start; i <= end; i++) {
    btns += `<button class="page-btn ${i===current?'active':''}" onclick="loadTransactions(${i})">${i}</button>`;
  }
  btns += `<button class="page-btn" onclick="loadTransactions(${current+1})" ${current>=pages?'disabled':''}>Next →</button>`;
  document.getElementById('pageBtns').innerHTML = btns;
}

// ── VIEW DETAIL ───────────────────────────────────────
async function viewDetail(id) {
  currentDetailId = id;
  document.getElementById('modalCode').textContent = 'Loading…';
  document.getElementById('modalBody').innerHTML = '<div class="empty"><div class="spin" style="margin:auto"></div></div>';
  document.getElementById('detailOverlay').classList.add('show');

  const res  = await fetch('../api/admin.php?action=get_transaction&id=' + id);
  const json = await res.json();
  if (!json.success) return;

  const t    = json.data;
  const dt   = new Date(t.created_at);
  const date = dt.toLocaleDateString('en-PH', {weekday:'short', year:'numeric', month:'short', day:'numeric'});
  const time = dt.toLocaleTimeString('en-PH', {hour:'2-digit', minute:'2-digit', second:'2-digit'});
  const disc = parseFloat(t.discount) + parseFloat(t.loyalty_discount);

  document.getElementById('modalCode').textContent = '🧾 ' + t.transaction_code;

  document.getElementById('modalBody').innerHTML = `
    <div class="info-grid">
      <div class="info-box"><div class="info-label">Date & Time</div><div class="info-val">${date}, ${time}</div></div>
      <div class="info-box"><div class="info-label">Status</div><div class="info-val"><span class="badge badge-${t.status}">${t.status}</span></div></div>
      <div class="info-box"><div class="info-label">Customer</div><div class="info-val">${t.customer_name}${t.customer_phone ? '<br><span style="font-size:11px;color:var(--muted)">'+t.customer_phone+'</span>' : ''}</div></div>
      <div class="info-box"><div class="info-label">Cashier</div><div class="info-val">${t.cashier_name}</div></div>
    </div>

    <div class="receipt">
      <div class="receipt-header">
        <h3>💧 AquaStation</h3>
        <div class="receipt-code">${t.transaction_code}</div>
        <div style="font-size:11px;color:var(--muted);margin-top:4px">${date} · ${time}</div>
      </div>

      <div class="receipt-items">
        <div style="font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.7px;margin-bottom:6px">Items Purchased</div>
        ${t.items.map(i => `
          <div class="item-row">
            <div>
              <div style="color:var(--text)">${i.product_name}</div>
              <div style="color:var(--muted)">₱${parseFloat(i.unit_price).toFixed(2)} × ${i.quantity}</div>
            </div>
            <div style="font-weight:600;color:var(--text)">₱${parseFloat(i.subtotal).toFixed(2)}</div>
          </div>`).join('')}
      </div>

      <hr class="receipt-divider">

      <div class="receipt-row bold"><span>Subtotal</span><span>₱${parseFloat(t.subtotal).toFixed(2)}</span></div>
      ${parseFloat(t.discount) > 0 ? `<div class="receipt-row"><span>Discount</span><span style="color:var(--yellow)">−₱${parseFloat(t.discount).toFixed(2)}</span></div>` : ''}
      ${parseFloat(t.loyalty_discount) > 0 ? `<div class="receipt-row"><span>Points Discount (${t.points_used} pts)</span><span style="color:var(--yellow)">−₱${parseFloat(t.loyalty_discount).toFixed(2)}</span></div>` : ''}

      <hr class="receipt-divider">

      <div class="receipt-total"><span>TOTAL</span><span>₱${parseFloat(t.total).toFixed(2)}</span></div>

      <hr class="receipt-divider">

      <div class="receipt-row"><span>Amount Paid</span><span>₱${parseFloat(t.amount_paid).toFixed(2)}</span></div>
      <div class="receipt-row bold"><span>Change</span><span>₱${parseFloat(t.change_amount).toFixed(2)}</span></div>
      <div class="receipt-row" style="margin-top:8px"><span>Payment</span><span style="text-transform:uppercase;font-size:11px">💵 ${t.payment_method}</span></div>
      ${t.points_earned > 0 ? `<div class="receipt-row" style="margin-top:6px"><span>Points Earned</span><span style="color:var(--yellow)">+${t.points_earned} ⭐</span></div>` : ''}
    </div>
  `;
}

function closeModal() {
  document.getElementById('detailOverlay').classList.remove('show');
}
document.getElementById('detailOverlay').addEventListener('click', e => {
  if (e.target === e.currentTarget) closeModal();
});

// ── FILTERS ───────────────────────────────────────────
function resetFilters() {
  document.getElementById('searchInput').value = '';
  document.getElementById('dateFrom').value    = '';
  document.getElementById('dateTo').value      = '';
  document.getElementById('statusFilter').value= '';
  loadTransactions(1);
}

document.getElementById('searchInput').addEventListener('input', () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => loadTransactions(1), 400);
});
document.getElementById('dateFrom').addEventListener('change',    () => loadTransactions(1));
document.getElementById('dateTo').addEventListener('change',      () => loadTransactions(1));
document.getElementById('statusFilter').addEventListener('change',() => loadTransactions(1));

// Init — default to today
const today = new Date().toISOString().split('T')[0];
document.getElementById('dateFrom').value = today;
document.getElementById('dateTo').value   = today;
loadTransactions(1);
