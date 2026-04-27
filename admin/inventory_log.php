<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pageTitle = 'Inventory Log';
$activePage = 'log';

$error = '';
$success = '';

$allExhibitions = $pdo->query('SELECT id, name FROM exhibitions ORDER BY name')->fetchAll();

if (is_post() && ($_POST['action'] ?? '') === 'update_sale_entry') {
    $logId = (int)($_POST['log_id'] ?? 0);
    $newQty = max(1, (int)($_POST['quantity'] ?? 1));
    $newQtyChange = -$newQty;
    $salePrice = (float)($_POST['sale_price'] ?? 0);
    $note = trim($_POST['note'] ?? '');
    $exhibitionIdRaw = trim((string)($_POST['exhibition_id'] ?? ''));
    $exhibitionId = $exhibitionIdRaw === '' ? null : (int)$exhibitionIdRaw;
    $productName = trim($_POST['product_name'] ?? '');
    $costPriceRaw = trim((string)($_POST['cost_price'] ?? ''));
    $costPrice = $costPriceRaw === '' ? null : (float)$costPriceRaw;

    if ($logId <= 0 || $salePrice < 0) {
        $error = 'Invalid sale entry data.';
    } else {
        $logStmt = $pdo->prepare('SELECT * FROM inventory_log WHERE id = :id LIMIT 1');
        $logStmt->execute([':id' => $logId]);
        $log = $logStmt->fetch();

        if (!$log) {
            $error = 'Sale entry not found.';
        } elseif (!in_array($log['action_type'], ['SALE', 'SALE_CONTAINER', 'MANUAL_SALE'], true)) {
            $error = 'Only sale entries can be modified.';
        } else {
            $isManualSale = $log['action_type'] === 'MANUAL_SALE';
            if ($isManualSale && $productName === '') {
                $error = 'Product name is required for manual sale.';
            }
        }

        if ($error === '') {
            try {
                $pdo->beginTransaction();

                $isProductLinkedSale = !empty($log['product_id']);

                if ($isProductLinkedSale) {
                    $productStmt = $pdo->prepare('SELECT id, quantity FROM products WHERE id = :id LIMIT 1');
                    $productStmt->execute([':id' => (int)$log['product_id']]);
                    $product = $productStmt->fetch();

                    if (!$product) {
                        throw new RuntimeException('Linked product not found for this sale entry.');
                    }

                    $stockAdjustment = $newQtyChange - (int)$log['quantity_change'];
                    $newStock = (int)$product['quantity'] + $stockAdjustment;
                    if ($newStock < 0) {
                        throw new RuntimeException('Insufficient stock for requested correction.');
                    }

                    if ($log['action_type'] === 'SALE' && $exhibitionId !== null) {
                        $linkStmt = $pdo->prepare('SELECT COUNT(*) FROM exhibition_products WHERE product_id = :pid AND exhibition_id = :eid');
                        $linkStmt->execute([
                            ':pid' => (int)$log['product_id'],
                            ':eid' => $exhibitionId,
                        ]);
                        if ((int)$linkStmt->fetchColumn() === 0) {
                            throw new RuntimeException('Selected exhibition is not linked to this product.');
                        }
                    }

                    $updateProduct = $pdo->prepare('UPDATE products SET quantity = :quantity WHERE id = :id');
                    $updateProduct->execute([
                        ':quantity' => $newStock,
                        ':id' => (int)$log['product_id'],
                    ]);
                }

                $updateSql = 'UPDATE inventory_log SET
                                quantity_change = :quantity_change,
                                sale_price = :sale_price,
                                note = :note,
                                exhibition_id = :exhibition_id';

                $updateParams = [
                    ':quantity_change' => $newQtyChange,
                    ':sale_price' => $salePrice,
                    ':note' => $note,
                    ':exhibition_id' => in_array($log['action_type'], ['SALE', 'MANUAL_SALE'], true) ? $exhibitionId : null,
                    ':id' => $logId,
                ];

                if ($log['action_type'] === 'MANUAL_SALE') {
                    $updateSql .= ', product_name = :product_name, cost_price = :cost_price';
                    $updateParams[':product_name'] = $productName;
                    $updateParams[':cost_price'] = $costPrice;
                }

                $updateSql .= ' WHERE id = :id';
                $updateLog = $pdo->prepare($updateSql);
                $updateLog->execute($updateParams);

                $pdo->commit();
                $success = 'Sale entry updated successfully.';
            } catch (Throwable $th) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = $th->getMessage();
            }
        }
    }
}

if (is_post() && ($_POST['action'] ?? '') === 'delete_sale_entry') {
    $logId = (int)($_POST['log_id'] ?? 0);

    if ($logId <= 0) {
        $error = 'Invalid sale entry selected.';
    } else {
        $logStmt = $pdo->prepare('SELECT * FROM inventory_log WHERE id = :id LIMIT 1');
        $logStmt->execute([':id' => $logId]);
        $log = $logStmt->fetch();

        if (!$log) {
            $error = 'Sale entry not found.';
        } elseif (!in_array($log['action_type'], ['SALE', 'SALE_CONTAINER', 'MANUAL_SALE'], true)) {
            $error = 'Only sale entries can be deleted.';
        } else {
            try {
                $pdo->beginTransaction();

                if (!empty($log['product_id'])) {
                    $productStmt = $pdo->prepare('SELECT id, quantity FROM products WHERE id = :id LIMIT 1');
                    $productStmt->execute([':id' => (int)$log['product_id']]);
                    $product = $productStmt->fetch();

                    if (!$product) {
                        throw new RuntimeException('Linked product not found for this sale entry.');
                    }

                    $reversedStock = (int)$product['quantity'] - (int)$log['quantity_change'];
                    if ($reversedStock < 0) {
                        throw new RuntimeException('Cannot delete sale because stock reversal would be invalid.');
                    }

                    $updateProduct = $pdo->prepare('UPDATE products SET quantity = :quantity WHERE id = :id');
                    $updateProduct->execute([
                        ':quantity' => $reversedStock,
                        ':id' => (int)$log['product_id'],
                    ]);
                }

                $deleteLog = $pdo->prepare('DELETE FROM inventory_log WHERE id = :id');
                $deleteLog->execute([':id' => $logId]);

                $pdo->commit();
                $success = 'Sale entry deleted and quantity reversed.';
            } catch (Throwable $th) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = $th->getMessage();
            }
        }
    }
}

$q = trim($_GET['q'] ?? '');
$fromDate = trim($_GET['from_date'] ?? '');
$toDate = trim($_GET['to_date'] ?? '');
$actionType = trim($_GET['action_type'] ?? '');
$exhibitionFilter = trim((string)($_GET['exhibition_id'] ?? ''));

$sql = 'SELECT il.*, e.name AS exhibition_name
        FROM inventory_log il
        LEFT JOIN exhibitions e ON e.id = il.exhibition_id';
$params = [];
$conditions = [];

if ($q !== '') {
    $conditions[] = '(il.product_name LIKE :q_product OR il.action_type LIKE :q_action OR il.barcode LIKE :q_barcode)';
    $like = '%' . $q . '%';
    $params[':q_product'] = $like;
    $params[':q_action'] = $like;
    $params[':q_barcode'] = $like;
}

if ($fromDate !== '') {
    $fromDateObj = DateTime::createFromFormat('Y-m-d', $fromDate);
    if ($fromDateObj !== false) {
        $conditions[] = 'il.sold_at >= :from_date';
        $params[':from_date'] = $fromDate . ' 00:00:00';
    }
}

if ($toDate !== '') {
    $toDateObj = DateTime::createFromFormat('Y-m-d', $toDate);
    if ($toDateObj !== false) {
        $conditions[] = 'il.sold_at <= :to_date';
        $params[':to_date'] = $toDate . ' 23:59:59';
    }
}

if ($actionType !== '') {
    if ($actionType === 'SALE_EXHIBITION') {
        $conditions[] = "il.action_type = 'SALE' AND il.exhibition_id IS NOT NULL";
        if ($exhibitionFilter !== '' && ctype_digit($exhibitionFilter)) {
            $conditions[] = 'il.exhibition_id = :exhibition_id';
            $params[':exhibition_id'] = (int)$exhibitionFilter;
        }
    } else {
        $conditions[] = 'il.action_type = :action_type';
        $params[':action_type'] = $actionType;
    }
}

if (!empty($conditions)) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}

$sql .= ' ORDER BY il.id DESC LIMIT 1000';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

function is_sale_action_type(string $actionType): bool
{
    return in_array($actionType, ['SALE', 'SALE_CONTAINER', 'MANUAL_SALE'], true);
}

function calculate_log_profit(array $log): ?float
{
    if (!is_sale_action_type((string)($log['action_type'] ?? ''))) {
        return null;
    }

    $qty = abs((int)($log['quantity_change'] ?? 0));
    $salePrice = (float)($log['sale_price'] ?? 0);
    $costPrice = (float)($log['cost_price'] ?? 0);

    return ($salePrice - $costPrice) * $qty;
}

require_once __DIR__ . '/../includes/header.php';
?>
<style>
@media (max-width: 767.98px) {
    .log-card {
        border: 1px solid #e5edf2;
        border-radius: 12px;
        padding: 0.8rem;
        background: #fff;
    }
    .log-meta {
        font-size: 0.82rem;
        color: #5a6a76;
    }
    .log-value {
        font-size: 0.9rem;
        color: #1f2937;
    }
}
</style>
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0">Inventory Log</h5>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>admin/manual_sale.php" class="btn btn-sm btn-dark">Manual Sale</a>
        <button class="btn btn-sm btn-outline-success" onclick="exportToCSV()">Export CSV</button>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2">
            <div class="col-12 col-md-3">
                <input type="text" name="q" class="form-control" value="<?= e($q) ?>" placeholder="Product name or barcode">
            </div>
            <div class="col-6 col-md-2">
                <input type="date" name="from_date" class="form-control" value="<?= e($fromDate) ?>">
                <small class="text-muted">From Date</small>
            </div>
            <div class="col-6 col-md-2">
                <input type="date" name="to_date" class="form-control" value="<?= e($toDate) ?>">
                <small class="text-muted">To Date</small>
            </div>
            <div class="col-12 col-md-3">
                <select name="action_type" class="form-select">
                    <option value="">All Action Types</option>
                    <option value="SALE" <?= ($actionType === 'SALE') ? 'selected' : '' ?>>Sale</option>
                    <option value="SALE_EXHIBITION" <?= ($actionType === 'SALE_EXHIBITION') ? 'selected' : '' ?>>Sold in Exhibition</option>
                    <option value="MANUAL_SALE" <?= ($actionType === 'MANUAL_SALE') ? 'selected' : '' ?>>Manual Sale</option>
                    <option value="SALE_CONTAINER" <?= ($actionType === 'SALE_CONTAINER') ? 'selected' : '' ?>>Container Sale</option>
                    <option value="PRODUCT_CREATED" <?= ($actionType === 'PRODUCT_CREATED') ? 'selected' : '' ?>>Product Created</option>
                    <option value="STOCK_UPDATE" <?= ($actionType === 'STOCK_UPDATE') ? 'selected' : '' ?>>Stock Update</option>
                    <option value="PRICE_UPDATE" <?= ($actionType === 'PRICE_UPDATE') ? 'selected' : '' ?>>Price Update</option>
                    <option value="ADD" <?= ($actionType === 'ADD') ? 'selected' : '' ?>>Add</option>
                </select>
            </div>
            <div class="col-12 col-md-3" id="exhibitionFilterWrap" style="display: <?= ($actionType === 'SALE_EXHIBITION') ? 'block' : 'none' ?>;">
                <select name="exhibition_id" class="form-select" id="exhibitionFilterSelect">
                    <option value="">All Exhibitions</option>
                    <?php foreach ($allExhibitions as $ex): ?>
                        <option value="<?= (int)$ex['id'] ?>" <?= ((string)(int)$ex['id'] === $exhibitionFilter) ? 'selected' : '' ?>><?= e($ex['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-2 d-grid">
                <button class="btn btn-outline-secondary">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($logs)): ?>
            <div class="alert alert-warning mb-0">No log entries found for your filter.</div>
        <?php endif; ?>

        <div class="d-grid gap-2 d-md-none">
            <?php foreach ($logs as $log): ?>
                <?php $canEditSale = in_array($log['action_type'], ['SALE', 'SALE_CONTAINER', 'MANUAL_SALE'], true); ?>
                <?php $logProfit = calculate_log_profit($log); ?>
                <?php $logQtyAbs = abs((int)($log['quantity_change'] ?? 0)); ?>
                <?php $logSaleValue = (float)($log['sale_price'] ?? 0) * abs((int)($log['quantity_change'] ?? 0)); ?>
                <?php $logCostPerPiece = (float)($log['cost_price'] ?? 0); ?>
                <?php $logTotalCost = $logCostPerPiece * $logQtyAbs; ?>
                <div class="log-card">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                        <div>
                            <div class="fw-semibold"><?= e($log['product_name']) ?></div>
                            <div class="log-meta">#<?= (int)$log['id'] ?> | <?= e((string)$log['barcode']) ?></div>
                        </div>
                        <div class="text-end">
                            <div class="log-meta">Qty</div>
                            <div class="fw-semibold"><?= (int)$log['quantity_change'] ?></div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <?php if ($log['action_type'] === 'SALE_CONTAINER'): ?>
                            <span class="badge bg-primary">Container Sale</span>
                        <?php elseif ($log['action_type'] === 'MANUAL_SALE'): ?>
                            <span class="badge bg-secondary">Manual Sale</span>
                        <?php elseif ($log['action_type'] === 'SALE' && !empty($log['exhibition_id'])): ?>
                            <span class="badge bg-warning text-dark">Sold in Exhibition</span>
                        <?php else: ?>
                            <span class="badge <?= action_badge_class($log['action_type']) ?>"><?= e($log['action_type']) ?></span>
                        <?php endif; ?>
                        <span class="badge text-bg-light border">Sale Value: <?= format_money($logSaleValue) ?></span>
                        <span class="badge text-bg-light border">Cost/Pc: <?= format_money($logCostPerPiece) ?></span>
                        <span class="badge text-bg-light border">Total Cost: <?= format_money($logTotalCost) ?></span>
                        <?php if ($logProfit !== null): ?>
                            <span class="badge text-bg-light border">Profit: <?= format_money($logProfit) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($log['exhibition_name'])): ?>
                            <span class="badge text-bg-light border"><?= e((string)$log['exhibition_name']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="log-meta mb-2">Date: <?= e(format_indian_datetime((string)$log['sold_at'])) ?></div>

                    <div class="d-grid gap-2">
                        <?php if (!empty($log['note'])): ?>
                            <button class="btn btn-sm btn-outline-dark" data-bs-toggle="modal" data-bs-target="#noteModal<?= (int)$log['id'] ?>">View Note</button>
                        <?php endif; ?>

                        <?php if ($canEditSale): ?>
                            <div class="row g-2">
                                <div class="col-6 d-grid">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editSaleModal<?= (int)$log['id'] ?>">Edit Sale</button>
                                </div>
                                <div class="col-6 d-grid">
                                    <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteSaleModal<?= (int)$log['id'] ?>">Delete</button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="table-responsive d-none d-md-block">
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Product</th>
                        <th>Qty</th>
                        <th>Badge</th>
                        <th>Sale Value</th>
                        <th>Cost/Pc</th>
                        <th>Total Cost</th>
                        <th>Profit</th>
                        <th>Exhibition</th>
                        <th>Date</th>
                        <th>Note</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $log): ?>
                    <?php $logProfit = calculate_log_profit($log); ?>
                    <?php $logQtyAbs = abs((int)($log['quantity_change'] ?? 0)); ?>
                    <?php $logSaleValue = (float)($log['sale_price'] ?? 0) * $logQtyAbs; ?>
                    <?php $logCostPerPiece = (float)($log['cost_price'] ?? 0); ?>
                    <?php $logTotalCost = $logCostPerPiece * $logQtyAbs; ?>
                    <tr>
                        <td><?= (int)$log['id'] ?></td>
                        <td>
                            <div><?= e($log['product_name']) ?></div>
                            <small class="text-muted"><?= e((string)$log['barcode']) ?></small>
                        </td>
                        <td><?= (int)$log['quantity_change'] ?></td>
                        <td>
                            <?php if ($log['action_type'] === 'SALE_CONTAINER'): ?>
                                <span class="badge bg-primary">Container Sale</span>
                            <?php elseif ($log['action_type'] === 'MANUAL_SALE'): ?>
                                <span class="badge bg-secondary">Manual Sale</span>
                            <?php elseif ($log['action_type'] === 'SALE' && !empty($log['exhibition_id'])): ?>
                                <span class="badge bg-warning text-dark">Sold in Exhibition</span>
                            <?php else: ?>
                                <span class="badge <?= action_badge_class($log['action_type']) ?>"><?= e($log['action_type']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= format_money($logSaleValue) ?></td>
                        <td><?= format_money($logCostPerPiece) ?></td>
                        <td><?= format_money($logTotalCost) ?></td>
                        <td>
                            <?php if ($logProfit !== null): ?>
                                <?= format_money($logProfit) ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e((string)$log['exhibition_name']) ?></td>
                        <td><small><?= e(format_indian_datetime((string)$log['sold_at'])) ?></small></td>
                        <td>
                            <?php if (!empty($log['note'])): ?>
                                <button class="btn btn-sm btn-outline-dark" data-bs-toggle="modal" data-bs-target="#noteModal<?= (int)$log['id'] ?>">View</button>
                                <div class="modal fade" id="noteModal<?= (int)$log['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h6 class="modal-title">Note #<?= (int)$log['id'] ?></h6>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body"><?= nl2br(e((string)$log['note'])) ?></div>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php $canEditSale = in_array($log['action_type'], ['SALE', 'SALE_CONTAINER', 'MANUAL_SALE'], true); ?>
                            <?php if ($canEditSale): ?>
                                <div class="d-grid gap-1">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editSaleModal<?= (int)$log['id'] ?>">Edit Sale</button>
                                    <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteSaleModal<?= (int)$log['id'] ?>">Delete Sale</button>
                                </div>

                                <div class="modal fade" id="deleteSaleModal<?= (int)$log['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <form method="post">
                                                <div class="modal-header">
                                                    <h6 class="modal-title">Delete Sale #<?= (int)$log['id'] ?></h6>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="action" value="delete_sale_entry">
                                                    <input type="hidden" name="log_id" value="<?= (int)$log['id'] ?>">
                                                    <p class="mb-2">This will permanently delete the sale entry.</p>
                                                    <?php if (!empty($log['product_id'])): ?>
                                                        <p class="mb-0 text-muted">Product quantity will be reversed by <?= abs((int)$log['quantity_change']) ?>.</p>
                                                    <?php else: ?>
                                                        <p class="mb-0 text-muted">No product stock update is required for this manual sale.</p>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button class="btn btn-danger">Delete and Reverse</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <div class="modal fade" id="editSaleModal<?= (int)$log['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <form method="post">
                                                <div class="modal-header">
                                                    <h6 class="modal-title">Edit Sale #<?= (int)$log['id'] ?></h6>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="action" value="update_sale_entry">
                                                    <input type="hidden" name="log_id" value="<?= (int)$log['id'] ?>">

                                                    <?php if ($log['action_type'] === 'MANUAL_SALE'): ?>
                                                        <div class="mb-2">
                                                            <label class="form-label">Product Name</label>
                                                            <input type="text" name="product_name" class="form-control" value="<?= e((string)$log['product_name']) ?>" required>
                                                        </div>
                                                        <div class="mb-2">
                                                            <label class="form-label">Cost Price</label>
                                                            <input type="number" step="0.01" name="cost_price" class="form-control" value="<?= e((string)$log['cost_price']) ?>">
                                                        </div>
                                                    <?php endif; ?>

                                                    <div class="row g-2">
                                                        <div class="col-6">
                                                            <label class="form-label">Quantity</label>
                                                            <input type="number" name="quantity" min="1" class="form-control" value="<?= abs((int)$log['quantity_change']) ?>" required>
                                                        </div>
                                                        <div class="col-6">
                                                            <label class="form-label">Sale Price</label>
                                                            <input type="number" step="0.01" name="sale_price" class="form-control" value="<?= e((string)$log['sale_price']) ?>" required>
                                                        </div>
                                                    </div>

                                                        <?php if (in_array($log['action_type'], ['SALE', 'MANUAL_SALE'], true)): ?>
                                                        <div class="mt-2">
                                                            <label class="form-label">Exhibition (optional)</label>
                                                            <select name="exhibition_id" class="form-select">
                                                                <option value="">None</option>
                                                                <?php foreach ($allExhibitions as $ex): ?>
                                                                    <option value="<?= (int)$ex['id'] ?>" <?= ((int)$log['exhibition_id'] === (int)$ex['id']) ? 'selected' : '' ?>><?= e($ex['name']) ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                            <?php if ($log['action_type'] === 'SALE' && !empty($log['product_id'])): ?>
                                                                <small class="text-muted">Only exhibitions linked to this product are accepted.</small>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>

                                                    <div class="mt-2">
                                                        <label class="form-label">Note</label>
                                                        <textarea name="note" class="form-control" rows="3"><?= e((string)$log['note']) ?></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button class="btn btn-is2">Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
function exportToCSV() {
    const q = '<?= urlencode($q) ?>';
    const fromDate = '<?= urlencode($fromDate) ?>';
    const toDate = '<?= urlencode($toDate) ?>';
    const actionType = '<?= urlencode($actionType) ?>';
    const exhibitionId = '<?= urlencode($exhibitionFilter) ?>';
    const url = `<?= BASE_URL ?>admin/export.php?type=inventory_log&q=${q}&from_date=${fromDate}&to_date=${toDate}&action_type=${actionType}&exhibition_id=${exhibitionId}`;
    window.location.href = url;
}

document.addEventListener('DOMContentLoaded', function() {
    const actionSelect = document.querySelector('select[name="action_type"]');
    const exhibitionWrap = document.getElementById('exhibitionFilterWrap');
    const exhibitionSelect = document.getElementById('exhibitionFilterSelect');

    if (!actionSelect || !exhibitionWrap) {
        return;
    }

    const syncExhibitionVisibility = () => {
        const isSoldInExhibition = actionSelect.value === 'SALE_EXHIBITION';
        exhibitionWrap.style.display = isSoldInExhibition ? 'block' : 'none';
        if (!isSoldInExhibition && exhibitionSelect) {
            exhibitionSelect.value = '';
        }
    };

    actionSelect.addEventListener('change', syncExhibitionVisibility);
    syncExhibitionVisibility();
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
