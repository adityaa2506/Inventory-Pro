<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pageTitle = 'Exhibition Analysis';
$activePage = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Exhibition Performance</h5>
    <a href="<?= BASE_URL ?>admin/analytics.php" class="btn btn-sm btn-outline-secondary">Back to Analytics</a>
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label">Exhibition</label>
                <select id="exhibitionId" class="form-select">
                    <option value="0">All Exhibitions</option>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Range</label>
                <select id="range" class="form-select">
                    <option value="7">Last 7 days</option>
                    <option value="30" selected>Last 30 days</option>
                    <option value="custom">Custom</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">From</label>
                <input type="date" id="fromDate" class="form-control" disabled>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">To</label>
                <input type="date" id="toDate" class="form-control" disabled>
            </div>
            <div class="col-12 col-md-2 d-grid">
                <button class="btn btn-is2" onclick="loadExhibitionAnalysis()">Apply</button>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="card">
            <div class="card-body">
                <div class="small text-muted">Revenue</div>
                <div class="h4 mb-0" id="revenueBox">0.00</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card">
            <div class="card-body">
                <div class="small text-muted">Profit</div>
                <div class="h4 mb-0 text-success" id="profitBox">0.00</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card">
            <div class="card-body">
                <div class="small text-muted">Units Sold</div>
                <div class="h4 mb-0" id="unitsBox">0</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card">
            <div class="card-body">
                <div class="small text-muted">Average Price</div>
                <div class="h4 mb-0" id="avgTicketBox">0.00</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-7">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="mb-3">Revenue and Profit Trend</h6>
                <canvas id="trendChart" height="95"></canvas>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-5">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="mb-3">Top Selling Products (Exhibition)</h6>
                <div id="topProductsWrap" class="small text-muted">Loading...</div>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h6 class="mb-3">Exhibition Comparison</h6>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Exhibition</th>
                                <th class="text-end">Revenue</th>
                                <th class="text-end">Profit</th>
                                <th class="text-end">Units Sold</th>
                                <th class="text-end">Transactions</th>
                            </tr>
                        </thead>
                        <tbody id="comparisonTbody">
                            <tr><td colspan="5" class="text-muted">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Exhibition Transactions</h6>
                    <div class="w-50">
                        <input type="text" id="transactionSearch" class="form-control form-control-sm" placeholder="Filter by product name...">
                    </div>
                    <small class="text-muted" id="txCountInfo">0 entries</small>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date & Time</th>
                                <th>Exhibition</th>
                                <th>Product</th>
                                <th>Action</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">Sale Value</th>
                                <th class="text-end">Cost/Pc</th>
                                <th class="text-end">Total Cost</th>
                                <th class="text-end">Profit</th>
                            </tr>
                        </thead>
                        <tbody id="transactionsTbody">
                            <tr><td colspan="10" class="text-muted">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-2">
                    <button class="btn btn-sm btn-outline-secondary" id="txPrevBtn" type="button">Previous</button>
                    <small class="text-muted" id="txPageInfo">Page 1 of 1</small>
                    <button class="btn btn-sm btn-outline-secondary" id="txNextBtn" type="button">Next</button>
                </div>
            </div>
        </div>
    </div>

    <!-- NEW SECTION: EXPENSES -->
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Exhibition Expenses</h6>
                    <button class="btn btn-sm btn-is2" data-bs-toggle="modal" data-bs-target="#addExpenseModal">Add Expense</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Title</th>
                                <th>Category</th>
                                <th class="text-end">Amount</th>
                                <th class="text-end">#</th>
                            </tr>
                        </thead>
                        <tbody id="expensesTbody">
                            <tr><td colspan="5" class="text-muted">Select an exhibition to view expenses.</td></tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-end text-muted small">Total Expenses</th>
                                <th id="totalExpenses" class="text-end">₹0.00</th>
                                <th></th>
                            </tr>
                            <tr>
                                <th colspan="3" class="text-end text-muted small">Net Profit (Profit - Expenses)</th>
                                <th id="netProfit" class="text-end fw-bold">₹0.00</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL FOR ADDING EXPENSE -->
    <div class="modal fade" id="addExpenseModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Exhibition Expense</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addExpenseForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Expense Title</label>
                            <input type="text" name="title" class="form-control" placeholder="e.g. Stall Rent" required>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">Amount (₹)</label>
                                <input type="number" step="0.01" name="amount" class="form-control" required>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">Category</label>
                                <select name="category" class="form-select">
                                    <option value="RENT">Rent</option>
                                    <option value="TRAVEL">Travel</option>
                                    <option value="STAFF">Staff</option>
                                    <option value="MARKETING">Marketing</option>
                                    <option value="OTHER">Other</option>
                                </select>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Date</label>
                                <input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-is2 w-100">Save Expense</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const rangeEl = document.getElementById('range');
const fromEl = document.getElementById('fromDate');
const toEl = document.getElementById('toDate');
const exhibitionEl = document.getElementById('exhibitionId');
const expensesTbody = document.getElementById('expensesTbody');
const addExpenseForm = document.getElementById('addExpenseForm');
let trendChart;
let txPage = 1;
let txTotalPages = 1;
let txSearchTerm = '';
let txSearchTimeout;

document.getElementById('transactionSearch').addEventListener('input', (e) => {
    clearTimeout(txSearchTimeout);
    txSearchTimeout = setTimeout(() => {
        txSearchTerm = e.target.value;
        loadExhibitionAnalysis(1); // Reset to page 1 on new search
    }, 300); // 300ms debounce
});

rangeEl.addEventListener('change', () => {
    const isCustom = rangeEl.value === 'custom';
    fromEl.disabled = !isCustom;
    toEl.disabled = !isCustom;
});

function money(v) {
    return '₹' + Number(v || 0).toFixed(2);
}

async function loadExhibitionExpenses(exId, grossProfit = 0) {
    if (!exId || exId === '0') {
        expensesTbody.innerHTML = '<tr><td colspan="5" class="text-muted text-center">Select a specific exhibition to view expenses.</td></tr>';
        document.getElementById('totalExpenses').textContent = money(0);
        document.getElementById('netProfit').textContent = money(grossProfit);
        return;
    }

    const resp = await fetch(`<?= BASE_URL ?>admin/api/exhibition_expenses_data.php?action=list&exhibition_id=${exId}`);
    const expenses = await resp.json();
    
    let total = 0;
    let html = '';
    
    if (expenses.length === 0) {
        html = '<tr><td colspan="5" class="text-muted text-center">No expenses recorded for this exhibition.</td></tr>';
    } else {
        expenses.forEach(e => {
            total += Number(e.amount);
            html += `<tr>
                <td class="small">${e.expense_date}</td>
                <td>${e.title}</td>
                <td class="small text-muted">${e.category}</td>
                <td class="text-end">${money(e.amount)}</td>
                <td class="text-end">
                    <button class="btn btn-sm text-danger p-0" onclick="deleteExpense(${e.id})"><i class="fa-solid fa-trash-can"></i></button>
                </td>
            </tr>`;
        });
    }
    
    expensesTbody.innerHTML = html;
    document.getElementById('totalExpenses').textContent = money(total);
    const net = grossProfit - total;
    const netEl = document.getElementById('netProfit');
    netEl.textContent = money(net);
    netEl.className = net >= 0 ? 'text-end fw-bold text-success' : 'text-end fw-bold text-danger';
}

async function deleteExpense(id) {
    if (!confirm('Are you sure you want to delete this expense?')) return;
    const body = new URLSearchParams({ id: id });
    await fetch('<?= BASE_URL ?>admin/api/exhibition_expenses_data.php?action=delete', {
        method: 'POST',
        body: body
    });
    loadExhibitionAnalysis();
}

addExpenseForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const exId = exhibitionEl.value;
    if (!exId || exId === '0') {
        alert('Please select an exhibition first.');
        return;
    }

    const formData = new FormData(addExpenseForm);
    await fetch(`<?= BASE_URL ?>admin/api/exhibition_expenses_data.php?action=add&exhibition_id=${exId}`, {
        method: 'POST',
        body: formData
    });

    const modal = bootstrap.Modal.getInstance(document.getElementById('addExpenseModal'));
    modal.hide();
    addExpenseForm.reset();
    loadExhibitionAnalysis();
});

function formatDateTimeIN(value) {
    if (!value) return '-';
    const normalized = String(value).replace(' ', 'T');
    const dt = new Date(normalized);
    if (Number.isNaN(dt.getTime())) {
        return value;
    }
    return new Intl.DateTimeFormat('en-IN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: true
    }).format(dt);
}

function renderTopProducts(rows) {
    const wrap = document.getElementById('topProductsWrap');
    if (!rows || rows.length === 0) {
        wrap.innerHTML = '<div class="text-muted">No exhibition sales for selected filters.</div>';
        return;
    }

    let html = '<ul class="list-group list-group-flush">';
    rows.forEach((row) => {
        html += `<li class="list-group-item px-0 d-flex justify-content-between"><span>${row.product_name}</span><span>${row.qty} | ${money(row.revenue)}</span></li>`;
    });
    html += '</ul>';
    wrap.innerHTML = html;
}

function renderComparison(rows) {
    const tbody = document.getElementById('comparisonTbody');
    if (!rows || rows.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-muted">No exhibition activity found.</td></tr>';
        return;
    }

    let html = '';
    rows.forEach((row) => {
        html += `<tr>
            <td>${row.name}</td>
            <td class="text-end">${money(row.revenue)}</td>
            <td class="text-end">${money(row.profit)}</td>
            <td class="text-end">${Number(row.units_sold || 0)}</td>
            <td class="text-end">${Number(row.transactions || 0)}</td>
        </tr>`;
    });
    tbody.innerHTML = html;
}

function renderTransactions(rows, pagination) {
    const tbody = document.getElementById('transactionsTbody');
    const pageInfo = document.getElementById('txPageInfo');
    const countInfo = document.getElementById('txCountInfo');
    const prevBtn = document.getElementById('txPrevBtn');
    const nextBtn = document.getElementById('txNextBtn');

    const page = Number(pagination?.page || 1);
    const totalPages = Number(pagination?.total_pages || 1);
    const total = Number(pagination?.total || 0);

    txPage = page;
    txTotalPages = totalPages;

    pageInfo.textContent = `Page ${page} of ${totalPages}`;
    countInfo.textContent = `${total} entries`;
    prevBtn.disabled = page <= 1;
    nextBtn.disabled = page >= totalPages;

    if (!rows || rows.length === 0) {
        tbody.innerHTML = '<tr><td colspan="10" class="text-muted">No transactions found for selected filters.</td></tr>';
        return;
    }

    let html = '';
    rows.forEach((row) => {
        const qty = Math.abs(Number(row.quantity_change || 0));
        const salePrice = Number(row.sale_price || 0);
        const costPrice = Number(row.cost_price || 0);
        const saleValue = salePrice * qty;
        const totalCost = costPrice * qty;
        const profit = saleValue - totalCost;

        html += `<tr>
            <td>${Number(row.id || 0)}</td>
            <td>${formatDateTimeIN(row.sold_at)}</td>
            <td>${row.exhibition_name || ''}</td>
            <td>${row.product_name || ''}</td>
            <td>${row.action_type || ''}</td>
            <td class="text-end">${qty}</td>
            <td class="text-end">${money(saleValue)}</td>
            <td class="text-end">${money(costPrice)}</td>
            <td class="text-end">${money(totalCost)}</td>
            <td class="text-end">${money(profit)}</td>
        </tr>`;
    });
    tbody.innerHTML = html;
}

function populateExhibitions(exhibitions, selectedId) {
    const previous = exhibitionEl.value || String(selectedId || 0);
    exhibitionEl.innerHTML = '<option value="0">All Exhibitions</option>';
    exhibitions.forEach((ex) => {
        const option = document.createElement('option');
        option.value = String(ex.id);
        option.textContent = ex.name;
        exhibitionEl.appendChild(option);
    });

    if ([...exhibitionEl.options].some(o => o.value === previous)) {
        exhibitionEl.value = previous;
    }
}

async function loadExhibitionAnalysis(page = 1) {
    const params = new URLSearchParams({
        range: rangeEl.value,
        exhibition_id: exhibitionEl.value || '0',
        tx_page: String(page),
        tx_search: txSearchTerm
    });

    if (rangeEl.value === 'custom') {
        params.append('from', fromEl.value);
        params.append('to', toEl.value);
    }

    const response = await fetch('<?= BASE_URL ?>admin/api/exhibition_analysis_data.php?' + params.toString());
    const data = await response.json();

    populateExhibitions(data.exhibitions || [], data.selectedExhibitionId || 0);

    const grossProfit = Number(data.summary?.profit || 0);
    document.getElementById('revenueBox').textContent = money(data.summary?.revenue);
    document.getElementById('profitBox').textContent = money(grossProfit);
    document.getElementById('unitsBox').textContent = Number(data.summary?.units_sold || 0);
    document.getElementById('avgTicketBox').textContent = money(data.summary?.avg_ticket);

    renderTopProducts(data.topProducts || []);
    renderComparison(data.byExhibition || []);
    renderTransactions(data.transactions || [], data.transactionsPagination || {});

    // Sync expenses
    loadExhibitionExpenses(exhibitionEl.value, grossProfit);

    const labels = (data.trend || []).map((r) => r.d);
    const revenueSeries = (data.trend || []).map((r) => Number(r.revenue || 0));
    const profitSeries = (data.trend || []).map((r) => Number(r.profit || 0));

    const ctx = document.getElementById('trendChart');
    if (trendChart) {
        trendChart.destroy();
    }

    trendChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'Revenue',
                    data: revenueSeries,
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13,110,253,0.1)',
                    fill: false,
                    tension: 0.35
                },
                {
                    label: 'Profit',
                    data: profitSeries,
                    borderColor: '#198754',
                    backgroundColor: 'rgba(25,135,84,0.1)',
                    fill: false,
                    tension: 0.35
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: true }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
}

exhibitionEl.addEventListener('change', () => loadExhibitionAnalysis(1));
rangeEl.addEventListener('change', () => loadExhibitionAnalysis(1));
fromEl.addEventListener('change', () => {
    if (rangeEl.value === 'custom') {
        loadExhibitionAnalysis(1);
    }
});
toEl.addEventListener('change', () => {
    if (rangeEl.value === 'custom') {
        loadExhibitionAnalysis(1);
    }
});

document.getElementById('txPrevBtn').addEventListener('click', () => {
    if (txPage > 1) {
        loadExhibitionAnalysis(txPage - 1);
    }
});

document.getElementById('txNextBtn').addEventListener('click', () => {
    if (txPage < txTotalPages) {
        loadExhibitionAnalysis(txPage + 1);
    }
});

loadExhibitionAnalysis(1);
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
