
let customers = [], selectedId = null;

// ── LOAD DATA ──────────────────────────────────────
async function loadCustomers() {
  const res  = await fetch('../api/admin.php?action=get_customers');
  const json = await res.json();
  if (!json.success) return;
  customers = json.data;
  renderStats(json.stats);
  renderTable();
}

function renderStats(s) {
  if (!s) return;
  document.getElementById('statsRow').innerHTML = `
    <div class="stat-card stat-cyan"><div class="stat-label">Total Customers</div><div class="stat-value">${s.total}</div></div>
    <div class="stat-card stat-green"><div class="stat-label">Active</div><div class="stat-value">${s.active}</div></div>
    <div class="stat-card stat-yellow"><div class="stat-label">Total Points Issued</div><div class="stat-value">${Number(s.total_points).toLocaleString()}</div></div>
    <div class="stat-card stat-red"><div class="stat-label">Inactive</div><div class="stat-value">${s.total - s.active}</div></div>
  `;
}

function renderTable() {
  const q  = document.getElementById('searchInput').value.toLowerCase();
  const st = document.getElementById('statusFilter').value;
  const filtered = customers.filter(c => {
    const mQ  = !q  || c.name.toLowerCase().includes(q) || c.phone.includes(q) || c.address.toLowerCase().includes(q);
    const mSt = !st || c.status === st;
    return mQ && mSt;
  });

  const tbody = document.getElementById('tbody');
  if (!filtered.length) {
    tbody.innerHTML = '<tr><td colspan="10" class="empty">No customers found.</td></tr>';
    return;
  }

  tbody.innerHTML = filtered.map((c, i) => {
    const date = new Date(c.created_at).toLocaleDateString('en-PH', {month:'short', day:'numeric', year:'numeric'});
    return `<tr>
      <td style="color:var(--muted);font-size:12px">${i+1}</td>
      <td style="font-weight:600">${c.name}</td>
      <td style="font-family:monospace;color:var(--cyan);font-size:12px">${c.phone}</td>
      <td style="color:var(--muted);font-size:12px;max-width:160px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="${c.address}">${c.address}</td>
      <td><span class="pts-chip">⭐ ${c.loyalty_points}</span></td>
      <td style="text-align:center;color:var(--text)">${c.total_purchases}</td>
      <td><span class="badge badge-${c.status}">${c.status}</span></td>
      <td style="font-size:12px;color:var(--muted)">${date}</td>
      <td>
        <button class="action-btn" onclick="openDetail(${c.id})" title="View Details">👁</button>
        <button class="action-btn" onclick="openEdit(${c.id})" title="Edit">✏️</button>
        <button class="action-btn danger" onclick="openConfirmToggle(${c.id})" title="${c.status==='active'?'Deactivate':'Activate'}">${c.status==='active'?'🔴':'🟢'}</button>
      </td>
    </tr>`;
  }).join('');
}

// ── DETAIL MODAL ───────────────────────────────────
async function openDetail(id) {
  selectedId = id;
  const c = customers.find(x => x.id == id);
  if (!c) return;

  document.getElementById('detailTitle').textContent = '👤 ' + c.name;

  // Load transaction history
  const res  = await fetch('../api/admin.php?action=get_customer_transactions&id=' + id);
  const json = await res.json();
  const txs  = json.data || [];

  const txHtml = txs.length
    ? txs.map(t => `
        <div class="tx-row">
          <div>
            <div class="tx-code">${t.transaction_code}</div>
            <div class="tx-date">${new Date(t.created_at).toLocaleDateString('en-PH')}</div>
          </div>
          <div class="tx-amt">₱${parseFloat(t.total).toFixed(2)}</div>
        </div>`).join('')
    : '<div style="text-align:center;padding:16px;color:var(--muted);font-size:13px">No transactions yet</div>';

  document.getElementById('detailBody').innerHTML = `
    <div class="detail-grid">
      <div class="detail-box"><div class="detail-label">Phone</div><div class="detail-val" style="font-family:monospace;color:var(--cyan)">${c.phone}</div></div>
      <div class="detail-box"><div class="detail-label">Email</div><div class="detail-val">${c.email || '—'}</div></div>
      <div class="detail-box" style="grid-column:1/-1"><div class="detail-label">Address</div><div class="detail-val" style="font-size:13px">${c.address}</div></div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:20px">
      <div class="big-stat bg-yellow"><div class="num" style="color:var(--yellow)">${c.loyalty_points}</div><div class="lbl">Points Balance</div></div>
      <div class="big-stat bg-green"><div class="num" style="color:var(--green)">${c.total_purchases}</div><div class="lbl">Total Purchases</div></div>
    </div>

    <div style="margin-bottom:16px">
      <div style="font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.7px;margin-bottom:8px">⭐ Adjust Points</div>
      <div class="pts-adjust">
        <input type="number" id="ptsInput" min="0" value="0" placeholder="Points amount">
        <button class="pts-btn add" onclick="adjustPoints('add')">+ Add</button>
        <button class="pts-btn sub" onclick="adjustPoints('subtract')">− Deduct</button>
      </div>
      <div id="ptsMsg" style="font-size:12px;margin-top:6px"></div>
    </div>

    <div>
      <div style="font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.7px;margin-bottom:8px">🧾 Recent Transactions</div>
      <div style="background:var(--surface2);border-radius:10px;padding:0 14px;max-height:200px;overflow-y:auto">${txHtml}</div>
    </div>
  `;

  document.getElementById('detailActions').innerHTML = `
    <button class="btn-cancel" onclick="closeAll()">Close</button>
    <button class="btn-submit" onclick="openEdit(${id});closeAll();">✏️ Edit</button>
    <button class="btn-danger" onclick="openConfirmToggle(${id});closeAll();">${c.status==='active'?'Deactivate':'Activate'}</button>
  `;

  document.getElementById('detailModal').classList.add('show');
}

// ── ADJUST POINTS ──────────────────────────────────
async function adjustPoints(type) {
  const pts = parseInt(document.getElementById('ptsInput').value) || 0;
  if (pts <= 0) { showPtsMsg('Enter a valid number of points', 'error'); return; }

  const fd = new FormData();
  fd.append('id', selectedId);
  fd.append('points', pts);
  fd.append('type', type);

  const res  = await fetch('../api/admin.php?action=adjust_points', { method:'POST', body:fd });
  const json = await res.json();

  if (json.success) {
    showPtsMsg((type==='add'?'+ Added ':'− Deducted ') + pts + ' points', 'success');
    // Update local data
    const c = customers.find(x => x.id == selectedId);
    if (c) c.loyalty_points = json.new_points;
    loadCustomers();
  } else {
    showPtsMsg(json.message, 'error');
  }
}

function showPtsMsg(msg, type) {
  const el = document.getElementById('ptsMsg');
  el.style.color = type === 'success' ? 'var(--green)' : 'var(--red)';
  el.textContent = (type==='success'?'✓ ':'✗ ') + msg;
  setTimeout(() => el.textContent = '', 3000);
}

// ── ADD CUSTOMER ───────────────────────────────────
function openAddModal() {
  document.getElementById('aName').value = '';
  document.getElementById('aPhone').value = '';
  document.getElementById('aAddress').value = '';
  document.getElementById('aEmail').value = '';
  document.getElementById('addMsg').innerHTML = '';
  document.getElementById('addModal').classList.add('show');
}

async function submitAdd() {
  const name  = document.getElementById('aName').value.trim();
  const phone = document.getElementById('aPhone').value.trim();
  const addr  = document.getElementById('aAddress').value.trim();
  const email = document.getElementById('aEmail').value.trim();

  if (!name || !phone || !addr) {
    document.getElementById('addMsg').innerHTML = '<div style="color:var(--red);font-size:13px;margin-bottom:12px">⚠ Name, phone and address are required.</div>';
    return;
  }

  const fd = new FormData();
  fd.append('name', name); fd.append('phone', phone);
  fd.append('address', addr); fd.append('email', email);

  const res  = await fetch('../api/admin.php?action=add_customer', { method:'POST', body:fd });
  const json = await res.json();

  if (json.success) {
    closeAll();
    showToast('Customer added successfully!', 'success');
    loadCustomers();
  } else {
    document.getElementById('addMsg').innerHTML = `<div style="color:var(--red);font-size:13px;margin-bottom:12px">⚠ ${json.message}</div>`;
  }
}

// ── EDIT CUSTOMER ──────────────────────────────────
function openEdit(id) {
  selectedId = id;
  const c = customers.find(x => x.id == id);
  if (!c) return;
  document.getElementById('eId').value           = c.id;
  document.getElementById('eName').value         = c.name;
  document.getElementById('ePhone').value        = c.phone;
  document.getElementById('eAddress').value      = c.address;
  document.getElementById('eEmail').value        = c.email || '';
  document.getElementById('editMsg').innerHTML   = '';
  document.getElementById('editModal').classList.add('show');
}

async function submitEdit() {
  const id   = document.getElementById('eId').value;
  const name = document.getElementById('eName').value.trim();
  const addr = document.getElementById('eAddress').value.trim();

  if (!name || !addr) {
    document.getElementById('editMsg').innerHTML = '<div style="color:var(--red);font-size:13px;margin-bottom:12px">⚠ Name and address are required.</div>';
    return;
  }

  const fd = new FormData();
  fd.append('id',      id);
  fd.append('name',    name);
  fd.append('address', addr);
  fd.append('email',   document.getElementById('eEmail').value.trim());

  const res  = await fetch('../api/admin.php?action=edit_customer', { method:'POST', body:fd });
  const json = await res.json();

  if (json.success) {
    closeAll();
    showToast('Customer updated!', 'success');
    loadCustomers();
  } else {
    document.getElementById('editMsg').innerHTML = `<div style="color:var(--red);font-size:13px;margin-bottom:12px">⚠ ${json.message}</div>`;
  }
}

// ── TOGGLE STATUS ──────────────────────────────────
function openConfirmToggle(id) {
  selectedId = id;
  const c = customers.find(x => x.id == id);
  if (!c) return;
  const action = c.status === 'active' ? 'deactivate' : 'activate';
  document.getElementById('confirmTitle').textContent = action === 'deactivate' ? '🔴 Deactivate Customer' : '🟢 Activate Customer';
  document.getElementById('confirmMsg').textContent   = `Are you sure you want to ${action} ${c.name}?`;
  document.getElementById('confirmBtn').textContent   = action.charAt(0).toUpperCase() + action.slice(1);
  document.getElementById('confirmBtn').onclick       = doToggle;
  document.getElementById('confirmModal').classList.add('show');
}

async function doToggle() {
  const fd = new FormData(); fd.append('id', selectedId);
  const res  = await fetch('../api/admin.php?action=toggle_customer', { method:'POST', body:fd });
  const json = await res.json();
  if (json.success) {
    closeAll();
    showToast('Customer ' + json.status, 'success');
    loadCustomers();
  }
}

// ── UTILS ──────────────────────────────────────────
function closeAll() {
  document.querySelectorAll('.overlay').forEach(o => o.classList.remove('show'));
}
function showToast(msg, type) {
  const t = document.getElementById('toast');
  t.textContent = (type==='success'?'✓ ':'✗ ') + msg;
  t.className = `toast show ${type}`;
  setTimeout(() => t.classList.remove('show'), 3500);
}

// Close overlay on backdrop click
document.querySelectorAll('.overlay').forEach(o => {
  o.addEventListener('click', e => { if (e.target === o) closeAll(); });
});

document.getElementById('searchInput').addEventListener('input', renderTable);
document.getElementById('statusFilter').addEventListener('change', renderTable);

// Init
loadCustomers();
setInterval(loadCustomers, 30000);
