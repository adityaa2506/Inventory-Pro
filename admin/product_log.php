<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$productId = (int)($_GET['id'] ?? 0);

if ($productId <= 0) {
    http_response_code(400);
    die('Invalid product ID.');
}

$productStmt = $pdo->prepare('SELECT id, name, barcode, category, cost_price, selling_price, quantity FROM products WHERE id = :id LIMIT 1');
$productStmt->execute([':id' => $productId]);
$product = $productStmt->fetch();

if (!$product) {
    http_response_code(404);
    die('Product not found.');
}

$logsStmt = $pdo->prepare('SELECT il.*, e.name AS exhibition_name
                          FROM inventory_log il
                          LEFT JOIN exhibitions e ON e.id = il.exhibition_id
                          WHERE il.product_id = :pid
                          ORDER BY il.id DESC
                          LIMIT 500');
$logsStmt->execute([':pid' => $productId]);
$logs = $logsStmt->fetchAll();

$pageTitle = 'Product Log';
$activePage = 'products';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <div>
        <h5 class="mb-1">Product Log</h5>
        <div class="small text-muted">
            <?= e($product['name']) ?> | Barcode: <?= e($product['barcode']) ?> | SP: <?= format_money((float)$product['selling_price']) ?> | CP: <?= format_money((float)$product['cost_price']) ?>
        </div>
    </div>
    <div class="d-grid d-sm-flex gap-2">
        <a href="<?= BASE_URL ?>admin/products.php" class="btn btn-sm btn-outline-secondary">Back to Products</a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="row g-2">
            <div class="col-6 col-md-3"><strong>Category:</strong> <?= e((string)($product['category'] ?? '-')) ?></div>
            <div class="col-6 col-md-3"><strong>Current Qty:</strong> <?= (int)$product['quantity'] ?></div>
            <div class="col-6 col-md-3"><strong>Selling Price:</strong> <?= format_money((float)$product['selling_price']) ?></div>
            <div class="col-6 col-md-3"><strong>Cost Price:</strong> <?= format_money((float)$product['cost_price']) ?></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (!$logs): ?>
            <div class="alert alert-warning mb-0">No log entries found for this product.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Action</th>
                            <th>Qty</th>
                            <th>Sale Value</th>
                            <th>Cost/Pc</th>
                            <th>Total Cost</th>
                            <th>Exhibition</th>
                            <th>Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <?php
                            $qtyAbs = abs((int)$log['quantity_change']);
                            $saleValue = (float)($log['sale_price'] ?? 0) * $qtyAbs;
                            $costPerPiece = (float)($log['cost_price'] ?? 0);
                            $totalCost = $costPerPiece * $qtyAbs;
                            ?>
                            <tr>
                                <td><?= (int)$log['id'] ?></td>
                                <td><small><?= e(format_indian_datetime((string)$log['sold_at'])) ?></small></td>
                                <td><span class="badge <?= action_badge_class((string)$log['action_type']) ?>"><?= e((string)$log['action_type']) ?></span></td>
                                <td><?= (int)$log['quantity_change'] ?></td>
                                <td><?= format_money($saleValue) ?></td>
                                <td><?= format_money($costPerPiece) ?></td>
                                <td><?= format_money($totalCost) ?></td>
                                <td><?= e((string)($log['exhibition_name'] ?? '')) ?></td>
                                <td><?= e((string)($log['note'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
