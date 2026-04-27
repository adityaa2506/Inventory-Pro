<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$pageTitle = 'Barcode Labels';
$activePage = 'products';

$products = $pdo->query('SELECT id, name, barcode FROM products ORDER BY name')->fetchAll();
$labels = [];

if (is_post()) {
    $selectedProducts = $_POST['selected_products'] ?? [];
    $quantities = $_POST['label_qty'] ?? [];

    if (is_array($selectedProducts)) {
        $inIds = array_map('intval', $selectedProducts);
        $inIds = array_values(array_filter($inIds));

        if (!empty($inIds)) {
            $placeholders = implode(',', array_fill(0, count($inIds), '?'));
            $stmt = $pdo->prepare("SELECT id, name, barcode FROM products WHERE id IN ($placeholders)");
            $stmt->execute($inIds);
            $picked = $stmt->fetchAll();
            $map = [];
            foreach ($picked as $p) {
                $map[(int)$p['id']] = $p;
            }

            foreach ($inIds as $id) {
                $qty = isset($quantities[$id]) ? max(0, (int)$quantities[$id]) : 0;
                if ($qty <= 0 || !isset($map[$id])) {
                    continue;
                }
                for ($i = 0; $i < $qty; $i++) {
                    $labels[] = $map[$id];
                }
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="card mb-3 no-print">
    <div class="card-body">
        <h5 class="mb-3">Barcode Label Generator</h5>
        <form method="post">
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead><tr><th>Select</th><th>Product</th><th>Barcode</th><th>Labels</th></tr></thead>
                    <tbody>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td><input type="checkbox" name="selected_products[]" value="<?= (int)$p['id'] ?>"></td>
                            <td><?= e($p['name']) ?></td>
                            <td><?= e($p['barcode']) ?></td>
                            <td style="max-width:120px;"><input type="number" min="0" name="label_qty[<?= (int)$p['id'] ?>]" class="form-control form-control-sm" value="0"></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-is2">Generate Labels</button>
                <?php if (!empty($labels)): ?><button type="button" onclick="window.print()" class="btn btn-outline-dark">Print</button><?php endif; ?>
                <a href="<?= BASE_URL ?>admin/thermal_labels.php" class="btn btn-outline-secondary">Thermal Mode</a>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($labels)): ?>
<style>
@media print {
    .no-print, .navbar, .bottom-nav { display: none !important; }
    body { padding: 0 !important; background: #fff !important; }
}
.label-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
}
.label-item {
    border: 1px dashed #999;
    border-radius: 6px;
    text-align: center;
    padding: 8px;
    min-height: 150px;
    page-break-inside: avoid;
}
.page-break {
    page-break-after: always;
    break-after: page;
    margin-bottom: 8px;
}
</style>

<div class="card">
    <div class="card-body">
        <?php foreach ($labels as $index => $label): ?>
            <?php if ($index % 15 === 0): ?>
                <?php if ($index > 0): ?></div><div class="page-break"></div><?php endif; ?>
                <div class="label-grid">
            <?php endif; ?>
            <div class="label-item">
                <div class="fw-semibold small"><?= e($label['name']) ?></div>
                <img src="https://bwipjs-api.metafloor.com/?bcid=code128&text=<?= urlencode($label['barcode']) ?>&dpi=96" class="img-fluid" alt="barcode">
                <div class="small mt-1"><?= e($label['barcode']) ?></div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
