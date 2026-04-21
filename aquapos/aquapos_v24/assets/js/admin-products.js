
let products = [];
let categories = [];

async function loadCategories() {
  const res = await fetch('../api/admin.php?action=get_categories');
  const json = await res.json();
  if (!json.success) return;
  categories = json.data;
  const catFilter = document.getElementById('catFilter');
  const addCat = document.getElementById('addCategory');
  categories.forEach(c => {
    catFilter.innerHTML += `<option value="${c.id}">${c.name}</option>`;
    addCat.innerHTML += `<option value="${c.id}">${c.name}</option>`;
  });
}

async function loadProducts() {
  const res = await fetch('../api/admin.php?action=get_products');
  const json = await res.json();
  if (!json.success) return;
  products = json.data;
  renderTable();
}

function renderTable() {
  const q = document.getElementById('searchInput').value.toLowerCase();
  const cat = document.getElementById('catFilter').value;
  const status = document.getElementById('statusFilter').value;

  let filtered = products.filter(p => {
    const mQ = !q || p.name.toLowerCase().includes(q);
    const mC = !cat || p.category_id == cat;
    const mS = !status || p.status === status;
    return mQ && mC && mS;
  });

  const tbody = document.getElementById('productsTbody');
  if (!filtered.length) {
    tbody.innerHTML = '<tr class="empty-row"><td colspan="8">No products found.</td></tr>';
    return;
  }

  tbody.innerHTML = filtered.map((p, i) => {
    const stockClass = p.stock <= 0 ? 'stock-out' : (p.stock <= 10 ? 'stock-low' : 'stock-ok');
    const stockLabel = p.stock <= 0 ? 'Out of stock' : (p.stock <= 10 ? `Low (${p.stock})` : p.stock);
    return `
    <tr id="row-${p.id}">
      <td style="color:var(--muted);font-size:12px">${i+1}</td>
      <td style="font-weight:500">${p.name}</td>
      <td style="font-size:12px;color:var(--muted)">${p.category_name || '—'}</td>
      <td>
        <div class="price-cell">
          <span class="price-display" id="pd-${p.id}" onclick="startEditPrice(${p.id})" title="Click to edit price">
            ₱${parseFloat(p.price).toFixed(2)}
          </span>
          <div class="price-input-wrap" id="piw-${p.id}">
            <input class="price-inp" id="pi-${p.id}" type="number" value="${parseFloat(p.price).toFixed(2)}" min="0" step="0.01"
              onkeydown="handlePriceKey(event,${p.id})" onfocus="this.select()">
            <button class="price-save" onclick="savePrice(${p.id})">✓</button>
            <button class="price-cancel" onclick="cancelEditPrice(${p.id})">✕</button>
          </div>
        </div>
      </td>
      <td><span class="stock-badge ${stockClass}">${stockLabel}</span></td>
      <td style="font-size:12px;color:var(--muted)">${p.unit}</td>
      <td>
        <button class="status-toggle status-${p.status}" id="st-${p.id}" onclick="toggleStatus(${p.id})">
          ${p.status === 'active' ? '● Active' : '○ Inactive'}
        </button>
      </td>
      <td>
        <button class="action-btn" onclick="openEditModal(${p.id})">✏️ Edit</button>
      </td>
    </tr>`;
  }).join('');
}

// ── INLINE PRICE EDIT ──────────────────────────────
function startEditPrice(id) {
  document.getElementById('pd-' + id).classList.add('editing');
  const wrap = document.getElementById('piw-' + id);
  wrap.classList.add('show');
  document.getElementById('pi-' + id).focus();
}

function cancelEditPrice(id) {
  document.getElementById('pd-' + id).classList.remove('editing');
  document.getElementById('piw-' + id).classList.remove('show');
  // Restore original
  const p = products.find(x => x.id == id);
  if (p) document.getElementById('pi-' + id).value = parseFloat(p.price).toFixed(2);
}

function handlePriceKey(e, id) {
  if (e.key === 'Enter') savePrice(id);
  if (e.key === 'Escape') cancelEditPrice(id);
}

async function savePrice(id) {
  const newPrice = parseFloat(document.getElementById('pi-' + id).value);
  if (isNaN(newPrice) || newPrice < 0) { showToast('Invalid price', 'error'); return; }

  const fd = new FormData();
  fd.append('id', id);
  fd.append('price', newPrice);

  const res = await fetch('../api/admin.php?action=update_price', { method:'POST', body:fd });
  const json = await res.json();

  if (json.success) {
    // Update local data
    const p = products.find(x => x.id == id);
    if (p) p.price = newPrice;

    // Update display
    const display = document.getElementById('pd-' + id);
    display.textContent = '₱' + newPrice.toFixed(2);
    display.classList.remove('editing');
    display.classList.add('price-change-flash');
    setTimeout(() => display.classList.remove('price-change-flash'), 700);

    document.getElementById('piw-' + id).classList.remove('show');
    showToast('Price updated — POS will reflect change instantly!', 'success');
  } else {
    showToast(json.message || 'Update failed', 'error');
  }
}

// ── TOGGLE STATUS ──────────────────────────────────
async function toggleStatus(id) {
  const fd = new FormData(); fd.append('id', id);
  const res = await fetch('../api/admin.php?action=toggle_status', { method:'POST', body:fd });
  const json = await res.json();
  if (json.success) {
    const p = products.find(x => x.id == id);
    if (p) p.status = json.status;
    renderTable();
    showToast(`Product ${json.status}`, 'success');
  }
}

// ── ADD PRODUCT ────────────────────────────────────
function openAddModal() { document.getElementById('addModal').classList.add('show'); }

async function submitAdd() {
  const name  = document.getElementById('addName').value.trim();
  const price = parseFloat(document.getElementById('addPrice').value);
  const stock = parseInt(document.getElementById('addStock').value) || 0;
  const cat   = document.getElementById('addCategory').value;
  const unit  = document.getElementById('addUnit').value;

  if (!name || isNaN(price)) { showToast('Name and price are required', 'error'); return; }

  const fd = new FormData();
  fd.append('name', name); fd.append('price', price);
  fd.append('stock', stock); fd.append('category_id', cat);
  fd.append('unit', unit);

  const res = await fetch('../api/admin.php?action=add_product', { method:'POST', body:fd });
  const json = await res.json();
  if (json.success) {
    closeModal('addModal');
    showToast('Product added!', 'success');
    loadProducts();
  } else { showToast(json.message, 'error'); }
}

// ── EDIT PRODUCT ───────────────────────────────────
function openEditModal(id) {
  const p = products.find(x => x.id == id);
  if (!p) return;
  document.getElementById('editId').value    = p.id;
  document.getElementById('editName').value  = p.name;
  document.getElementById('editPrice').value = parseFloat(p.price).toFixed(2);
  document.getElementById('editStock').value = p.stock;
  document.getElementById('editStatus').value = p.status;
  document.getElementById('editModal').classList.add('show');
}

async function submitEdit() {
  const fd = new FormData();
  fd.append('id',     document.getElementById('editId').value);
  fd.append('name',   document.getElementById('editName').value.trim());
  fd.append('price',  document.getElementById('editPrice').value);
  fd.append('stock',  document.getElementById('editStock').value);
  fd.append('status', document.getElementById('editStatus').value);

  const res = await fetch('../api/admin.php?action=update_product', { method:'POST', body:fd });
  const json = await res.json();
  if (json.success) {
    closeModal('editModal');
    showToast('Product updated!', 'success');
    loadProducts();
  } else { showToast(json.message, 'error'); }
}

function closeModal(id) { document.getElementById(id).classList.remove('show'); }

// ── UTILS ──────────────────────────────────────────
function showToast(msg, type='success') {
  const t = document.getElementById('toast');
  t.textContent = (type==='success'?'✓ ':'✗ ') + msg;
  t.className = `toast show ${type}`;
  setTimeout(() => t.classList.remove('show'), 3500);
}

// Filter listeners
document.getElementById('searchInput').addEventListener('input', renderTable);
document.getElementById('catFilter').addEventListener('change', renderTable);
document.getElementById('statusFilter').addEventListener('change', renderTable);

// Close modal on backdrop click
document.querySelectorAll('.modal-overlay').forEach(o => {
  o.addEventListener('click', e => { if (e.target === o) o.classList.remove('show'); });
});

// Init
loadCategories();
loadProducts();

// Auto-refresh every 10s (so admin sees up-to-date data)
setInterval(loadProducts, 10000);
