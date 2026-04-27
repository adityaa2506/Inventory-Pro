<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$pageTitle = 'Scan Result';
$activePage = 'scan';

$code = trim($_GET['code'] ?? '');
$message = '';
$error = '';

$productStmt = $pdo->prepare('SELECT * FROM products WHERE barcode = :barcode LIMIT 1');
$productStmt->execute([':barcode' => $code]);
$product = $productStmt->fetch();

$containerStmt = $pdo->prepare('SELECT * FROM containers WHERE barcode = :barcode LIMIT 1');
$containerStmt->execute([':barcode' => $code]);
$container = $containerStmt->fetch();

if (is_post() && isset($_POST['sale_type']) && $_POST['sale_type'] === 'product' && $product) {
    $qty = max(1, (int)($_POST['sell_qty'] ?? 1));
    $overridePrice = trim($_POST['sale_price'] ?? '');
    $note = trim($_POST['note'] ?? '');
    $useExhibition = isset($_POST['use_exhibition']) ? 1 : 0;
    $exhibitionId = $useExhibition ? (int)($_POST['exhibition_id'] ?? 0) : null;

    $linkStmt = $pdo->prepare('SELECT COUNT(*) FROM exhibition_products WHERE product_id = :pid AND exhibition_id = :eid');
    $isValidExhibition = true;
    if ($exhibitionId) {
        $linkStmt->execute([':pid' => $product['id'], ':eid' => $exhibitionId]);
        $isValidExhibition = (int)$linkStmt->fetchColumn() > 0;
    }

    if ($product['quantity'] < $qty) {
        $error = 'Insufficient stock.';
    } elseif (!$isValidExhibition) {
        $error = 'Invalid exhibition selection for this product.';
    } else {
        $newQty = (int)$product['quantity'] - $qty;
        $update = $pdo->prepare('UPDATE products SET quantity = :qty WHERE id = :id');
        $update->execute([':qty' => $newQty, ':id' => $product['id']]);

        $salePrice = $overridePrice === ''
            ? (float)$product['selling_price']
            : per_unit_price_from_total((float)$overridePrice, $qty);

        log_inventory($pdo, [
            'product_id' => $product['id'],
            'barcode' => $product['barcode'],
            'product_name' => $product['name'],
            'quantity_change' => -$qty,
            'action_type' => 'SALE',
            'note' => $note,
            'sale_price' => $salePrice,
            'cost_price' => $product['cost_price'],
            'exhibition_id' => $exhibitionId ?: null,
        ]);

        $message = 'Product sold successfully.';
        $productStmt->execute([':barcode' => $code]);
        $product = $productStmt->fetch();
    }
}

if (is_post() && isset($_POST['sale_type']) && $_POST['sale_type'] === 'container' && $container) {
    $note = trim($_POST['note'] ?? 'Container sold');

    $itemsStmt = $pdo->prepare('SELECT ci.quantity, p.id, p.name, p.barcode, p.quantity AS stock, p.cost_price, p.selling_price
                                FROM container_items ci
                                INNER JOIN products p ON p.id = ci.product_id
                                WHERE ci.container_id = :cid');
    $itemsStmt->execute([':cid' => $container['id']]);
    $items = $itemsStmt->fetchAll();

    if (!$items) {
        $error = 'This container has no items.';
    } else {
        foreach ($items as $item) {
            if ((int)$item['stock'] < (int)$item['quantity']) {
                $error = 'Not enough stock for: ' . $item['name'];
                break;
            }
        }

        if ($error === '') {
            $pdo->beginTransaction();
            try {
                foreach ($items as $item) {
                    $newQty = (int)$item['stock'] - (int)$item['quantity'];
                    $upd = $pdo->prepare('UPDATE products SET quantity = :q WHERE id = :id');
                    $upd->execute([':q' => $newQty, ':id' => $item['id']]);

                    log_inventory($pdo, [
                        'product_id' => $item['id'],
                        'barcode' => $item['barcode'],
                        'product_name' => $item['name'],
                        'quantity_change' => -((int)$item['quantity']),
                        'action_type' => 'SALE_CONTAINER',
                        'note' => $note . ' [' . $container['container_name'] . ']',
                        'sale_price' => $item['selling_price'],
                        'cost_price' => $item['cost_price'],
                        'exhibition_id' => null,
                    ]);
                }
                $pdo->commit();
                $message = 'Container sold successfully.';
            } catch (Throwable $th) {
                $pdo->rollBack();
                $error = 'Container sale failed.';
            }
        }
    }
}

$linkedExhibitions = [];
if ($product) {
    $linkedExhibitionStmt = $pdo->prepare('SELECT e.id, e.name
                                          FROM exhibition_products ep
                                          INNER JOIN exhibitions e ON e.id = ep.exhibition_id
                                          WHERE ep.product_id = :pid');
    $linkedExhibitionStmt->execute([':pid' => $product['id']]);
    $linkedExhibitions = $linkedExhibitionStmt->fetchAll();
}

$containerItems = [];
if ($container) {
    $cStmt = $pdo->prepare('SELECT p.name, p.barcode, ci.quantity, p.quantity AS stock
                            FROM container_items ci
                            INNER JOIN products p ON p.id = ci.product_id
                            WHERE ci.container_id = :cid');
    $cStmt->execute([':cid' => $container['id']]);
    $containerItems = $cStmt->fetchAll();
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="row g-3">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h5>Scan Result</h5>
                <div class="small text-muted">Scanned Code: <?= e($code) ?></div>
                <?php if ($message): ?><div class="alert alert-success mt-3"><?= e($message) ?></div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger mt-3"><?= e($error) ?></div><?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($product): ?>
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="fw-bold"><?= e($product['name']) ?></h6>
                <div class="small text-muted mb-2">Category: <?= e((string)$product['category']) ?></div>
                <div class="small mb-1">Stock: <strong><?= (int)$product['quantity'] ?></strong></div>
                <div class="small mb-1">Price: <strong><?= format_money((float)$product['selling_price']) ?></strong></div>
                <img src="https://bwipjs-api.metafloor.com/?bcid=code128&text=<?= urlencode($product['barcode']) ?>" class="img-fluid my-2" alt="barcode">
                <form method="post" class="row g-2 mt-1">
                    <input type="hidden" name="sale_type" value="product">
                    <div class="col-6">
                        <label class="form-label">Sell Qty</label>
                        <input type="number" min="1" name="sell_qty" class="form-control" value="1" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Total Sale Price Override</label>
                        <input type="number" step="0.01" name="sale_price" class="form-control" placeholder="Optional">
                    </div>
                    <?php if (!empty($linkedExhibitions)): ?>
                    <div class="col-12">
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="checkbox" value="1" id="useEx" name="use_exhibition" onchange="toggleExhibition()">
                            <label class="form-check-label" for="useEx">Sold in exhibition</label>
                        </div>
                    </div>
                    <div class="col-12" id="exWrap" style="display:none;">
                        <label class="form-label">Select Exhibition</label>
                        <select name="exhibition_id" class="form-select">
                            <option value="">Choose</option>
                            <?php foreach ($linkedExhibitions as $ex): ?>
                                <option value="<?= (int)$ex['id'] ?>"><?= e($ex['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="col-12">
                        <label class="form-label">Note</label>
                        <textarea name="note" class="form-control" rows="2" placeholder="Optional"></textarea>
                    </div>
                    <div class="col-12 d-grid">
                        <button class="btn btn-danger">Sell Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($container): ?>
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="fw-bold">Container: <?= e($container['container_name']) ?></h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead><tr><th>Product</th><th>Need</th><th>Stock</th></tr></thead>
                        <tbody>
                            <?php foreach ($containerItems as $item): ?>
                                <tr>
                                    <td><?= e($item['name']) ?></td>
                                    <td><?= (int)$item['quantity'] ?></td>
                                    <td><?= (int)$item['stock'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <form method="post" class="mt-2">
                    <input type="hidden" name="sale_type" value="container">
                    <label class="form-label">Note</label>
                    <textarea name="note" class="form-control mb-2" rows="2" placeholder="Optional"></textarea>
                    <button class="btn btn-primary w-100">Sell Entire Container</button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!$product && !$container): ?>
    <div class="col-12">
        <div class="alert alert-warning">No product or container found for this barcode.</div>
    </div>
    <?php endif; ?>
</div>
<script>
function toggleExhibition() {
    const checkbox = document.getElementById('useEx');
    const wrap = document.getElementById('exWrap');
    if (!checkbox || !wrap) return;
    wrap.style.display = checkbox.checked ? 'block' : 'none';
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
