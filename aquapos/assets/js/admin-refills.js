let refills = [];

async function loadRefills() {
    const res  = await fetch('../api/admin.php?action=get_refills');
    const json = await res.json();
    if (!json.success) return;
    refills = json.data;
    renderTable();
}

function renderTable() {
    const tbody = document.getElementById('refillsTbody');
    if (!refills.length) {
        tbody.innerHTML = '<tr class="empty-row"><td colspan="6">No refill options found.</td></tr>';
        return;
    }
    tbody.innerHTML = refills.map((r, i) => `
    <tr id="refill-row-${r.id}">
      <td style="color:var(--muted);font-size:12px">${i + 1}</td>
      <td style="font-weight:500">💧 ${r.name}</td>
      <td style="font-size:12px;color:var(--muted)">${r.description || '—'}</td>
      <td>
        <div class="price-cell">
          <span class="price-display" id="rpd-${r.id}" onclick="startEditPrice(${r.id})" title="Click to edit">
            ₱${parseFloat(r.price).toFixed(2)}
          </span>
          <div class="price-input-wrap" id="rpiw-${r.id}">
            <input class="price-inp" id="rpi-${r.id}" type="number" value="${parseFloat(r.price).toFixed(2)}"
              min="0" step="0.01" onkeydown="handlePriceKey(event,${r.id})" onfocus="this.select()">
            <button class="price-save" onclick="savePrice(${r.id})">✓</button>
            <button class="price-cancel" onclick="cancelEditPrice(${r.id})">✕</button>
          </div>
        </div>
      </td>
      <td>
        <button class="status-toggle status-${r.status}" id="rst-${r.id}" onclick="toggleStatus(${r.id})">
          ${r.status === 'active' ? '● Active' : '○ Inactive'}
        </button>
      </td>
      <td>
        <button class="action-btn" onclick="openEditModal(${r.id})">✏️ Edit</button>
      </td>
    </tr>`).join('');
}

// ── INLINE PRICE EDIT ───────────────────────────────
function startEditPrice(id) {
    document.getElementById('rpd-' + id).classList.add('editing');
    document.getElementById('rpiw-' + id).classList.add('show');
    document.getElementById('rpi-' + id).focus();
}

function cancelEditPrice(id) {
    document.getElementById('rpd-' + id).classList.remove('editing');
    document.getElementById('rpiw-' + id).classList.remove('show');
    const r = refills.find(r => r.id == id);
    if (r) document.getElementById('rpi-' + id).value = parseFloat(r.price).toFixed(2);
}

function handlePriceKey(e, id) {
    if (e.key === 'Enter')  savePrice(id);
    if (e.key === 'Escape') cancelEditPrice(id);
}

async function savePrice(id) {
    const price = parseFloat(document.getElementById('rpi-' + id).value);
    if (isNaN(price) || price < 0) { showToast('Invalid price', 'error'); return; }

    const fd = new FormData();
    fd.append('action', 'update_refill_price');
    fd.append('id', id);
    fd.append('price', price.toFixed(2));

    const res  = await fetch('../api/admin.php', { method:'POST', body: fd });
    const json = await res.json();
    if (!json.success) { showToast(json.message || 'Failed', 'error'); return; }

    const r = refills.find(r => r.id == id);
    if (r) r.price = price;
    document.getElementById('rpd-' + id).textContent = '₱' + price.toFixed(2);
    cancelEditPrice(id);
    showToast('Price updated ✓', 'success');
}

// ── STATUS TOGGLE ───────────────────────────────────
async function toggleStatus(id) {
    const fd = new FormData();
    fd.append('action', 'toggle_refill');
    fd.append('id', id);
    const res  = await fetch('../api/admin.php', { method:'POST', body: fd });
    const json = await res.json();
    if (!json.success) return;
    const r = refills.find(r => r.id == id);
    if (r) r.status = json.status;
    const btn = document.getElementById('rst-' + id);
    btn.className = `status-toggle status-${json.status}`;
    btn.textContent = json.status === 'active' ? '● Active' : '○ Inactive';
    showToast(`Refill ${json.status}`, 'success');
}

// ── ADD MODAL ───────────────────────────────────────
function openAddModal() {
    document.getElementById('addName').value  = '';
    document.getElementById('addDesc').value  = '';
    document.getElementById('addPrice').value = '';
    document.getElementById('addModal').classList.add('show');
}

async function submitAdd() {
    const name  = document.getElementById('addName').value.trim();
    const desc  = document.getElementById('addDesc').value.trim();
    const price = parseFloat(document.getElementById('addPrice').value) || 0;
    if (!name) { showToast('Name is required', 'error'); return; }

    const fd = new FormData();
    fd.append('action', 'add_refill');
    fd.append('name', name);
    fd.append('description', desc);
    fd.append('price', price.toFixed(2));

    const res  = await fetch('../api/admin.php', { method:'POST', body: fd });
    const json = await res.json();
    if (!json.success) { showToast(json.message || 'Failed', 'error'); return; }
    closeModal('addModal');
    showToast('Refill option added ✓', 'success');
    loadRefills();
}

// ── EDIT MODAL ──────────────────────────────────────
function openEditModal(id) {
    const r = refills.find(r => r.id == id);
    if (!r) return;
    document.getElementById('editId').value       = r.id;
    document.getElementById('editName').value     = r.name;
    document.getElementById('editDesc').value     = r.description || '';
    document.getElementById('editPrice').value    = parseFloat(r.price).toFixed(2);
    document.getElementById('editStatus').value   = r.status;
    document.getElementById('editModal').classList.add('show');
}

async function submitEdit() {
    const id     = document.getElementById('editId').value;
    const name   = document.getElementById('editName').value.trim();
    const desc   = document.getElementById('editDesc').value.trim();
    const price  = parseFloat(document.getElementById('editPrice').value) || 0;
    const status = document.getElementById('editStatus').value;
    if (!name) { showToast('Name is required', 'error'); return; }

    const fd = new FormData();
    fd.append('action', 'update_refill');
    fd.append('id', id);
    fd.append('name', name);
    fd.append('description', desc);
    fd.append('price', price.toFixed(2));
    fd.append('status', status);

    const res  = await fetch('../api/admin.php', { method:'POST', body: fd });
    const json = await res.json();
    if (!json.success) { showToast(json.message || 'Failed', 'error'); return; }
    closeModal('editModal');
    showToast('Refill updated ✓', 'success');
    loadRefills();
}

// ── UTILS ───────────────────────────────────────────
function closeModal(id) { document.getElementById(id).classList.remove('show'); }

function showToast(msg, type = 'success') {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = `toast show ${type}`;
    setTimeout(() => t.classList.remove('show'), 2800);
}

// Live sync indicator
let lastRefillTs = null;
async function checkRefillSync() {
    try {
        const res  = await fetch('../api/admin.php?action=refill_version');
        const json = await res.json();
        if (lastRefillTs && json.ts !== lastRefillTs) loadRefills();
        lastRefillTs = json.ts;
    } catch(e) {}
}

document.addEventListener('DOMContentLoaded', () => {
    loadRefills();
    setInterval(checkRefillSync, 12000);
});
