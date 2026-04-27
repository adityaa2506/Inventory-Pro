<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pageTitle = 'Analytics';
$activePage = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0">Sales Analytics</h6>
            <div>
                <a href="<?= BASE_URL ?>admin/exhibition_analysis.php" class="btn btn-sm btn-outline-primary">Analysis & Expenses</a>
                <a href="<?= BASE_URL ?>admin/packing_list.php" class="btn btn-sm btn-outline-is2 ms-1">Stall Stock List</a>
            </div>
        </div>
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label">Range</label>
                <select id="range" class="form-select">
                    <option value="7">Last 7 days</option>
                    <option value="30">Last 30 days</option>
                    <option value="custom">Custom</option>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">From</label>
                <input type="date" id="fromDate" class="form-control" disabled>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">To</label>
                <input type="date" id="toDate" class="form-control" disabled>
            </div>
            <div class="col-12 col-md-3 d-grid">
                <button class="btn btn-is2" onclick="loadAnalytics()">Apply</button>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card"><div class="card-body"><div class="small text-muted">Revenue</div><div id="revenueBox" class="h4 mb-0">0.00</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="card-body"><div class="small text-muted">Profit</div><div id="profitBox" class="h4 mb-0 text-success">0.00</div></div></div></div>
    <div class="col-12 col-md-6"><div class="card"><div class="card-body"><canvas id="trendChart" height="85"></canvas></div></div></div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-4"><div class="card h-100"><div class="card-body"><h6>Top Products</h6><div id="topProductsWrap" class="small text-muted">Loading...</div></div></div></div>
    <div class="col-12 col-lg-4"><div class="card h-100"><div class="card-body"><h6>Exhibition Performance</h6><div id="exhibitionWrap" class="small text-muted">Loading...</div></div></div></div>
    <div class="col-12 col-lg-4"><div class="card h-100"><div class="card-body"><h6>Dead Stock</h6><div id="deadStockWrap" class="small text-muted">Loading...</div></div></div></div>
    <div class="col-12"><div class="card"><div class="card-body"><h6>Restock Suggestions</h6><div id="restockWrap" class="small text-muted">Loading...</div></div></div></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const rangeEl = document.getElementById('range');
const fromEl = document.getElementById('fromDate');
const toEl = document.getElementById('toDate');
let trendChart;

rangeEl.addEventListener('change', () => {
    const isCustom = rangeEl.value === 'custom';
    fromEl.disabled = !isCustom;
    toEl.disabled = !isCustom;
});

function renderList(targetId, rows, formatter) {
    const el = document.getElementById(targetId);
    if (!rows || rows.length === 0) {
        el.innerHTML = '<div class="text-muted">No data found.</div>';
        return;
    }
    let html = '<ul class="list-group list-group-flush">';
    rows.forEach(row => {
        html += '<li class="list-group-item px-0 d-flex justify-content-between">' + formatter(row) + '</li>';
    });
    html += '</ul>';
    el.innerHTML = html;
}

function money(v) {
    return Number(v || 0).toFixed(2);
}

async function loadAnalytics() {
    const params = new URLSearchParams({ range: rangeEl.value });
    if (rangeEl.value === 'custom') {
        params.append('from', fromEl.value);
        params.append('to', toEl.value);
    }

    const res = await fetch('<?= BASE_URL ?>admin/api/analytics_data.php?' + params.toString());
    const data = await res.json();

    document.getElementById('revenueBox').textContent = money(data.revenue);
    document.getElementById('profitBox').textContent = money(data.profit);

    renderList('topProductsWrap', data.topProducts, r => `<span>${r.product_name}</span><span>${r.qty} | ${money(r.amount)}</span>`);
    renderList('exhibitionWrap', data.exhibitions, r => `<span>${r.name}</span><span>${money(r.revenue)}</span>`);
    renderList('deadStockWrap', data.deadStock, r => `<span>${r.name}</span><span>${r.quantity}</span>`);
    renderList('restockWrap', data.restock, r => `<span>${r.name}</span><span>Stock ${r.quantity} | Sold ${r.sold_30}</span>`);

    const labels = data.series.map(x => x.d);
    const revenueData = data.series.map(x => x.revenue);
    const profitData = data.series.map(x => x.profit);

    const ctx = document.getElementById('trendChart');
    if (trendChart) trendChart.destroy();
    trendChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [
                { label: 'Revenue', data: revenueData, borderColor: '#0d6efd', tension: 0.35 },
                { label: 'Profit', data: profitData, borderColor: '#198754', tension: 0.35 }
            ]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: true } },
            scales: { y: { beginAtZero: true } }
        }
    });
}

loadAnalytics();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
