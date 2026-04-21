
document.getElementById('dateNow').textContent = new Date().toLocaleDateString('en-PH',{weekday:'long',year:'numeric',month:'long',day:'numeric'});

async function loadStats(){
  const res=await fetch('../api/admin.php?action=get_stats');
  const json=await res.json();
  if(!json.success) return;
  const d=json.data;
  const g=document.getElementById('statsGrid');
  g.innerHTML=`
    <div class="stat-card stat-cyan"><div class="stat-label">Today's Transactions</div><div class="stat-value">${d.today_sales}</div><div class="stat-sub">sales today</div></div>
    <div class="stat-card stat-green"><div class="stat-label">Today's Revenue</div><div class="stat-value">₱${parseFloat(d.today_revenue).toLocaleString('en-PH',{minimumFractionDigits:2})}</div><div class="stat-sub">collected today</div></div>
    <div class="stat-card stat-yellow"><div class="stat-label">This Month</div><div class="stat-value">₱${parseFloat(d.month_revenue).toLocaleString('en-PH',{minimumFractionDigits:2})}</div><div class="stat-sub">${new Date().toLocaleDateString('en-PH',{month:'long'})}</div></div>
    <div class="stat-card stat-red"><div class="stat-label">Low Stock Items</div><div class="stat-value">${d.low_stock}</div><div class="stat-sub">need restocking</div></div>
  `;
  const tbody=document.getElementById('recentTbody');
  if(!d.recent.length){tbody.innerHTML='<tr><td colspan="7" style="text-align:center;padding:24px;color:var(--muted)">No transactions yet</td></tr>';return;}
  tbody.innerHTML=d.recent.map(t=>`
    <tr>
      <td style="font-family:monospace;color:var(--cyan);font-size:12px">${t.transaction_code}</td>
      <td>${t.customer_name}</td>
      <td>${t.cashier}</td>
      <td style="font-family:'Syne',sans-serif;font-weight:700;color:var(--green)">₱${parseFloat(t.total).toFixed(2)}</td>
      <td style="text-transform:uppercase;font-size:11px">${t.payment_method}</td>
      <td><span class="badge badge-${t.status}">${t.status}</span></td>
      <td style="color:var(--muted);font-size:12px">${new Date(t.created_at).toLocaleTimeString('en-PH')}</td>
    </tr>`).join('');
}
loadStats();
setInterval(loadStats, 30000);
