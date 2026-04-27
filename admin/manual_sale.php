<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$pageTitle = 'Manual Sale';
$activePage = 'log';
$error = '';
$success = '';

$exhibitions = $pdo->query('SELECT id, name FROM exhibitions ORDER BY name')->fetchAll();

if (is_post()) {
    $productName = trim($_POST['product_name'] ?? '');
    $costPrice = (float)($_POST['cost_price'] ?? 0);
    $salePrice = (float)($_POST['sale_price'] ?? 0);
    $qty = max(1, (int)($_POST['quantity'] ?? 1));
    $note = trim($_POST['note'] ?? '');
    $exhibitionId = (int)($_POST['exhibition_id'] ?? 0);

    if ($productName === '' || $salePrice < 0 || $costPrice < 0) {
        $error = 'Please enter valid manual sale details.';
    } else {
        log_inventory($pdo, [
            'product_id' => null,
            'barcode' => null,
            'product_name' => $productName,
            'quantity_change' => -$qty,
            'action_type' => 'MANUAL_SALE',
            'note' => $note,
            'sale_price' => per_unit_price_from_total($salePrice, $qty),
            'cost_price' => per_unit_price_from_total($costPrice, $qty),
            'exhibition_id' => $exhibitionId ?: null,
        ]);
        $success = 'Manual sale saved.';
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <div class="card-body">
        <h5 class="mb-3">Manual Sale Entry</h5>
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

        <form method="post" class="row g-3">
            <div class="col-12 col-md-6">
                <label class="form-label">Product Name</label>
                <input type="text" name="product_name" class="form-control" required>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">Total Cost Price</label>
                <input type="number" step="0.01" name="cost_price" class="form-control" required>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">Total Sale Price</label>
                <input type="number" step="0.01" name="sale_price" class="form-control" required>
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label">Quantity</label>
                <input type="number" name="quantity" min="1" value="1" class="form-control" required>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label">Exhibition (Optional)</label>
                <select name="exhibition_id" class="form-select">
                    <option value="">None</option>
                    <?php foreach ($exhibitions as $e): ?>
                        <option value="<?= (int)$e['id'] ?>"><?= e($e['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label">Note</label>
                <textarea name="note" class="form-control" rows="3"></textarea>
            </div>
            <div class="col-12 d-grid d-md-flex gap-2">
                <button class="btn btn-is2">Save Manual Sale</button>
                <a href="<?= BASE_URL ?>admin/inventory_log.php" class="btn btn-outline-secondary">View Log</a>
            </div>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
