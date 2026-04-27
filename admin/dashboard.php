<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pageTitle = 'Dashboard';
$activePage = 'dashboard';

$totalProducts = (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
$lowStock = (int)$pdo->query('SELECT COUNT(*) FROM products WHERE quantity <= 5')->fetchColumn();

$totalUnitsSold = (int)$pdo->query("SELECT COALESCE(SUM(ABS(quantity_change)),0) FROM inventory_log WHERE quantity_change < 0 AND action_type IN ('SALE','SALE_CONTAINER','MANUAL_SALE')")->fetchColumn();

$totalTransactions = (int)$pdo->query("SELECT COUNT(*) FROM inventory_log WHERE quantity_change < 0 AND action_type IN ('SALE','SALE_CONTAINER','MANUAL_SALE')")->fetchColumn();

$salesToday = (float)$pdo->query("SELECT COALESCE(SUM(sale_price * ABS(quantity_change)),0) FROM inventory_log WHERE quantity_change < 0 AND DATE(sold_at)=CURDATE() AND action_type IN ('SALE','SALE_CONTAINER','MANUAL_SALE')")->fetchColumn();

$totalRevenue = (float)$pdo->query("SELECT COALESCE(SUM(sale_price * ABS(il.quantity_change)),0)
                                    FROM inventory_log il
                                    WHERE il.quantity_change < 0 AND il.action_type IN ('SALE','SALE_CONTAINER','MANUAL_SALE')")->fetchColumn();

$profitStmt = $pdo->query("SELECT COALESCE(SUM(
                        GREATEST(0,
                            (COALESCE(il.sale_price,0) - COALESCE(CASE WHEN il.product_id IS NOT NULL THEN p.cost_price ELSE il.cost_price END,0))
                        ) * ABS(il.quantity_change)
                    ),0) AS profit
                    FROM inventory_log il
                    LEFT JOIN products p ON p.id = il.product_id
                    WHERE il.quantity_change < 0 AND il.action_type IN ('SALE','SALE_CONTAINER','MANUAL_SALE')");
$totalProfit = (float)$profitStmt->fetchColumn();

$avgTicket = $totalTransactions > 0 ? ($totalRevenue / $totalTransactions) : 0;
$profitMarginPct = $totalRevenue > 0 ? (($totalProfit / $totalRevenue) * 100) : 0;

$inventoryValueCost = (float)$pdo->query('SELECT COALESCE(SUM(cost_price * quantity),0) FROM products')->fetchColumn();
$inventoryValueRetail = (float)$pdo->query('SELECT COALESCE(SUM(selling_price * quantity),0) FROM products')->fetchColumn();
$inventoryPotentialProfit = max(0, $inventoryValueRetail - $inventoryValueCost);

$recentLogs = $pdo->query('SELECT product_name, action_type, quantity_change, sold_at FROM inventory_log ORDER BY id DESC LIMIT 8')->fetchAll();

$recentSales = $pdo->query("SELECT product_name, action_type, quantity_change, sale_price, sold_at
                           FROM inventory_log
                           WHERE quantity_change < 0 AND action_type IN ('SALE','SALE_CONTAINER','MANUAL_SALE')
                           ORDER BY id DESC
                           LIMIT 8")->fetchAll();

$topProducts = $pdo->query("SELECT product_name,
                                   SUM(ABS(quantity_change)) AS units,
                                   SUM(COALESCE(sale_price,0) * ABS(quantity_change)) AS revenue
                            FROM inventory_log
                            WHERE quantity_change < 0 AND action_type IN ('SALE','SALE_CONTAINER','MANUAL_SALE')
                            GROUP BY product_name
                            ORDER BY units DESC, revenue DESC
                            LIMIT 6")->fetchAll();

$topExhibitions = $pdo->query("SELECT e.name,
                                      SUM(ABS(il.quantity_change)) AS units,
                                      SUM(COALESCE(il.sale_price,0) * ABS(il.quantity_change)) AS revenue
                               FROM inventory_log il
                               INNER JOIN exhibitions e ON e.id = il.exhibition_id
                               WHERE il.quantity_change < 0 AND il.action_type IN ('SALE','SALE_CONTAINER','MANUAL_SALE')
                               GROUP BY e.id, e.name
                               ORDER BY revenue DESC, units DESC
                               LIMIT 6")->fetchAll();

$trendRows = $pdo->query("SELECT DATE(sold_at) AS d,
                                 SUM(COALESCE(sale_price,0) * ABS(quantity_change)) AS revenue,
                                 SUM(ABS(quantity_change)) AS units
                          FROM inventory_log
                          WHERE quantity_change < 0
                            AND action_type IN ('SALE','SALE_CONTAINER','MANUAL_SALE')
                            AND sold_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                          GROUP BY DATE(sold_at)
                          ORDER BY DATE(sold_at)")->fetchAll();

$trendMap = [];
foreach ($trendRows as $row) {
    $trendMap[$row['d']] = [
        'revenue' => (float)$row['revenue'],
        'units' => (int)$row['units'],
    ];
}

$trendLabels = [];
$trendRevenue = [];
$trendUnits = [];
for ($i = 6; $i >= 0; $i--) {
    $dateKey = date('Y-m-d', strtotime('-' . $i . ' day'));
    $trendLabels[] = date('d-m', strtotime($dateKey));
    $trendRevenue[] = isset($trendMap[$dateKey]) ? (float)$trendMap[$dateKey]['revenue'] : 0;
    $trendUnits[] = isset($trendMap[$dateKey]) ? (int)$trendMap[$dateKey]['units'] : 0;
}

$trendLabelsJson = json_encode($trendLabels, JSON_UNESCAPED_UNICODE);
$trendRevenueJson = json_encode($trendRevenue);
$trendUnitsJson = json_encode($trendUnits);

require_once __DIR__ . '/../includes/header.php';
?>
<style>
.kpi-card {
    background: linear-gradient(135deg, #ffffff, #f8fafc);
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 1.5rem;
    text-align: center;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}
.kpi-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--ipv2-primary), var(--ipv2-accent));
}
.kpi-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(30, 64, 175, 0.15);
}
.kpi-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--ipv2-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.75rem;
}
.kpi-value {
    font-size: 1.75rem;
    font-weight: 800;
    color: var(--ipv2-ink);
}
.kpi-card.low-stock .kpi-value {
    color: var(--ipv2-danger);
}
.kpi-card.success .kpi-value {
    color: var(--ipv2-success);
}
.sales-trend-wrap {
    position: relative;
    height: 320px;
}
@media (max-width: 767.98px) {
    .sales-trend-wrap {
        height: 260px;
    }
}
</style>
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-lg-2">
        <div class="kpi-card">
            <div class="kpi-label">Total Products</div>
            <div class="kpi-value"><?= $totalProducts ?></div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="kpi-card low-stock">
            <div class="kpi-label">Low Stock</div>
            <div class="kpi-value"><?= $lowStock ?></div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="kpi-card">
            <div class="kpi-label">Units Sold</div>
            <div class="kpi-value"><?= $totalUnitsSold ?></div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="kpi-card">
            <div class="kpi-label">Sales Today</div>
            <div class="kpi-value" style="font-size: 1.4rem;"><?= format_money($salesToday) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="kpi-card">
            <div class="kpi-label">Revenue <i class="fas fa-eye-slash toggle-val text-muted" data-target="kpi-revenue" style="cursor:pointer; margin-left:4px;" title="Toggle Visibility"></i></div>
            <div class="kpi-value" id="kpi-revenue" data-val="<?= format_money($totalRevenue) ?>" style="font-size: 1.4rem;">***</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="kpi-card success">
            <div class="kpi-label">Profit <i class="fas fa-eye-slash toggle-val text-muted" data-target="kpi-profit" style="cursor:pointer; margin-left:4px;" title="Toggle Visibility"></i></div>
            <div class="kpi-value" id="kpi-profit" data-val="<?= format_money($totalProfit) ?>" style="font-size: 1.4rem;">***</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="kpi-card">
            <div class="kpi-label">Transactions</div>
            <div class="kpi-value"><?= $totalTransactions ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card">
            <div class="kpi-label">Avg. Ticket</div>
            <div class="kpi-value" style="font-size: 1.4rem;"><?= format_money($avgTicket) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card">
            <div class="kpi-label">Inventory Cost <i class="fas fa-eye-slash toggle-val text-muted" data-target="kpi-inv-cost" style="cursor:pointer; margin-left:4px;" title="Toggle Visibility"></i></div>
            <div class="kpi-value" id="kpi-inv-cost" data-val="<?= format_money($inventoryValueCost) ?>" style="font-size: 1.4rem;">***</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card success">
            <div class="kpi-label">Potential Profit <i class="fas fa-eye-slash toggle-val text-muted" data-target="kpi-pot-profit" style="cursor:pointer; margin-left:4px;" title="Toggle Visibility"></i></div>
            <div class="kpi-value" id="kpi-pot-profit" data-val="<?= format_money($inventoryPotentialProfit) ?>" style="font-size: 1.4rem;">***</div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-8">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="mb-3">Quick Actions</h6>
                <div class="row g-2">
                    <div class="col-6 col-md-4 d-grid"><a href="<?= BASE_URL ?>admin/product_add.php" class="btn btn-is2"><i class="fas fa-plus me-2"></i>Add Product</a></div>
                    <div class="col-6 col-md-4 d-grid"><a href="<?= BASE_URL ?>admin/scan.php" class="btn btn-danger"><i class="fas fa-barcode me-2"></i>Scan</a></div>
                    <div class="col-6 col-md-4 d-grid"><a href="<?= BASE_URL ?>admin/manual_sale.php" class="btn btn-outline-secondary"><i class="fas fa-receipt me-2"></i>Manual Sale</a></div>
                    <div class="col-6 col-md-4 d-grid"><a href="<?= BASE_URL ?>admin/containers.php" class="btn btn-outline-primary"><i class="fas fa-box me-2"></i>Containers</a></div>
                    <div class="col-6 col-md-4 d-grid"><a href="<?= BASE_URL ?>admin/barcode_labels.php" class="btn btn-outline-success"><i class="fas fa-tags me-2"></i>Labels</a></div>
                    <div class="col-6 col-md-4 d-grid"><a href="<?= BASE_URL ?>admin/analytics.php" class="btn btn-outline-secondary"><i class="fas fa-chart-bar me-2"></i>Analytics</a></div>
                </div>
                <hr>
                <h6 class="mb-2">Recent Activity</h6>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Product</th><th>Action</th><th>Qty</th><th>Time</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentLogs as $log): ?>
                            <tr>
                                <td><?= e($log['product_name']) ?></td>
                                <td><span class="badge <?= action_badge_class($log['action_type']) ?>"><?= e($log['action_type']) ?></span></td>
                                <td><?= (int)$log['quantity_change'] ?></td>
                                <td><small><?= e(format_indian_datetime((string)$log['sold_at'])) ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="mb-3">Low Stock Items</h6>
                <?php
                $lowItems = $pdo->query('SELECT name, quantity FROM products WHERE quantity <= 5 ORDER BY quantity ASC LIMIT 10')->fetchAll();
                ?>
                <?php if (!$lowItems): ?>
                    <div class="text-muted">No low stock items.</div>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                    <?php foreach ($lowItems as $i): ?>
                        <li class="list-group-item d-flex justify-content-between px-0"><span><?= e($i['name']) ?></span><span class="badge bg-danger"><?= (int)$i['quantity'] ?></span></li>
                    <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-12 col-lg-8">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Sales Trend (Last 7 Days)</h6>
                    <small class="text-muted">Revenue + Units</small>
                </div>
                <div class="sales-trend-wrap">
                    <canvas id="salesTrendChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="mb-3">Business Health</h6>
                <div class="mb-3">
                    <div class="d-flex justify-content-between small mb-1">
                        <span>Profit Margin</span>
                        <span><?= format_money($profitMarginPct) ?>%</span>
                    </div>
                    <div class="progress" role="progressbar" aria-label="Profit margin">
                        <div class="progress-bar bg-success" style="width: <?= max(0, min(100, $profitMarginPct)) ?>%"></div>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between small mb-1">
                        <span>Stock At Retail Value</span>
                        <span><?= format_money($inventoryValueRetail) ?></span>
                    </div>
                    <div class="progress" role="progressbar" aria-label="Stock at retail value">
                        <?php $retailFill = $inventoryValueRetail > 0 ? min(100, ($inventoryValueCost / $inventoryValueRetail) * 100) : 0; ?>
                        <div class="progress-bar bg-primary" style="width: <?= $retailFill ?>%"></div>
                    </div>
                </div>
                <div class="small text-muted">
                    Current stock at selling value: <strong><?= format_money($inventoryValueRetail) ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-12 col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="mb-3">Top Selling Products</h6>
                <?php if (!$topProducts): ?>
                    <div class="text-muted">No sales data yet.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Product</th><th class="text-end">Units</th><th class="text-end">Revenue</th></tr></thead>
                            <tbody>
                                <?php foreach ($topProducts as $item): ?>
                                    <tr>
                                        <td><?= e($item['product_name']) ?></td>
                                        <td class="text-end"><?= (int)$item['units'] ?></td>
                                        <td class="text-end"><?= format_money((float)$item['revenue']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="mb-3">Exhibition Performance</h6>
                <?php if (!$topExhibitions): ?>
                    <div class="text-muted">No exhibition sales recorded.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Exhibition</th><th class="text-end">Units</th><th class="text-end">Revenue</th></tr></thead>
                            <tbody>
                                <?php foreach ($topExhibitions as $ex): ?>
                                    <tr>
                                        <td><?= e($ex['name']) ?></td>
                                        <td class="text-end"><?= (int)$ex['units'] ?></td>
                                        <td class="text-end"><?= format_money((float)$ex['revenue']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="mb-3">Recent Sales</h6>
                <?php if (!$recentSales): ?>
                    <div class="text-muted">No recent sales.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Product</th><th class="text-end">Qty</th><th class="text-end">Amount</th></tr></thead>
                            <tbody>
                                <?php foreach ($recentSales as $sale): ?>
                                    <tr>
                                        <td>
                                            <div><?= e($sale['product_name']) ?></div>
                                            <small class="text-muted"><?= e(format_indian_datetime((string)$sale['sold_at'])) ?></small>
                                        </td>
                                        <td class="text-end"><?= abs((int)$sale['quantity_change']) ?></td>
                                        <td class="text-end"><?= format_money((float)$sale['sale_price'] * abs((int)$sale['quantity_change'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
const trendLabels = <?= $trendLabelsJson ?>;
const trendRevenue = <?= $trendRevenueJson ?>;
const trendUnits = <?= $trendUnitsJson ?>;

const trendCanvas = document.getElementById('salesTrendChart');
if (trendCanvas && window.Chart) {
    if (window.salesTrendChartInstance) {
        window.salesTrendChartInstance.destroy();
    }

    window.salesTrendChartInstance = new Chart(trendCanvas, {
        type: 'line',
        data: {
            labels: trendLabels,
            datasets: [
                {
                    label: 'Revenue',
                    data: trendRevenue,
                    borderColor: '#0f4c5c',
                    backgroundColor: 'rgba(15, 76, 92, 0.10)',
                    fill: true,
                    tension: 0.35,
                    yAxisID: 'yRevenue'
                },
                {
                    label: 'Units',
                    data: trendUnits,
                    borderColor: '#e76f51',
                    backgroundColor: 'rgba(231, 111, 81, 0.10)',
                    fill: false,
                    tension: 0.35,
                    yAxisID: 'yUnits'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            normalized: true,
            animation: {
                duration: 0
            },
            plugins: {
                legend: { position: 'top' }
            },
            scales: {
                yRevenue: {
                    type: 'linear',
                    position: 'left',
                    beginAtZero: true,
                    grid: { drawOnChartArea: true }
                },
                yUnits: {
                    type: 'linear',
                    position: 'right',
                    beginAtZero: true,
                    grid: { drawOnChartArea: false }
                }
            }
        }
    });
}
document.addEventListener('DOMContentLoaded', function() {
    const toggles = document.querySelectorAll('.toggle-val');
    toggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const targetEl = document.getElementById(targetId);
            if (!targetEl) return;
            
            if (this.classList.contains('fa-eye-slash')) {
                // Show value
                this.classList.remove('fa-eye-slash', 'text-muted');
                this.classList.add('fa-eye', 'text-primary');
                targetEl.textContent = targetEl.getAttribute('data-val');
            } else {
                // Hide value
                this.classList.remove('fa-eye', 'text-primary');
                this.classList.add('fa-eye-slash', 'text-muted');
                targetEl.textContent = '***';
            }
        });
    });
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
