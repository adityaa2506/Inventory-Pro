<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$pageTitle = 'Thermal Labels';
$activePage = 'products';

$products = $pdo->query('SELECT id, name, barcode FROM products ORDER BY name')->fetchAll();
$containers = $pdo->query('SELECT id, container_name, barcode FROM containers ORDER BY container_name')->fetchAll();

$labels = [];
if (is_post()) {
    $mode = $_POST['mode'] ?? 'product';
    $qty = max(1, (int)($_POST['qty'] ?? 1));
    $barcode = '';
    $name = '';

    if ($mode === 'container') {
        $id = (int)($_POST['container_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT container_name AS name, barcode FROM containers WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
    } else {
        $id = (int)($_POST['product_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT name, barcode FROM products WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
    }

    if (!empty($row)) {
        $barcode = $row['barcode'];
        $name = $row['name'];
        for ($i = 0; $i < $qty; $i++) {
            $labels[] = ['name' => $name, 'barcode' => $barcode];
        }
    }
}

if (isset($_GET['container_id'])) {
    $id = (int)$_GET['container_id'];
    $stmt = $pdo->prepare('SELECT container_name AS name, barcode FROM containers WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if ($row) {
        $labels[] = ['name' => $row['name'], 'barcode' => $row['barcode']];
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="card mb-3 no-print">
    <div class="card-body">
        <h5 class="mb-3">Thermal Printer Mode</h5>
        <form method="post" class="row g-2 align-items-end">
            <div class="col-12 col-md-2">
                <label class="form-label">Mode</label>
                <select name="mode" id="mode" class="form-select" onchange="toggleMode()">
                    <option value="product">Product</option>
                    <option value="container">Container</option>
                </select>
            </div>
            <div class="col-12 col-md-4" id="productWrap">
                <label class="form-label">Product</label>
                <select name="product_id" class="form-select">
                    <?php foreach ($products as $p): ?>
                        <option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?> (<?= e($p['barcode']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-4 d-none" id="containerWrap">
                <label class="form-label">Container</label>
                <select name="container_id" class="form-select">
                    <?php foreach ($containers as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"><?= e($c['container_name']) ?> (<?= e($c['barcode']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">Qty</label>
                <input type="number" min="1" name="qty" class="form-control" value="1">
            </div>
            <div class="col-6 col-md-2 d-grid">
                <button class="btn btn-is2">Generate</button>
            </div>
            <?php if (!empty($labels)): ?><div class="col-12 col-md-2 d-grid"><button type="button" onclick="window.print()" class="btn btn-outline-dark">Print</button></div><?php endif; ?>
        </form>
    </div>
</div>

<style>
.thermal-sheet {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}
.thermal-label {
    width: 260px;
    border: 1px solid #000;
    padding: 8px;
    text-align: center;
    page-break-inside: avoid;
}
.thermal-label .name {
    font-size: 13px;
    font-weight: 700;
    margin-bottom: 4px;
}
@media print {
    .no-print, .navbar, .bottom-nav { display: none !important; }
    body { background: #fff !important; padding: 0 !important; }
    .thermal-label { margin-bottom: 8px; }
}
</style>

<?php if (!empty($labels)): ?>
<div class="card">
    <div class="card-body">
        <div class="thermal-sheet">
            <?php foreach ($labels as $label): ?>
                <div class="thermal-label">
                    <div class="name"><?= e($label['name']) ?></div>
                    <img src="https://bwipjs-api.metafloor.com/?bcid=code128&text=<?= urlencode($label['barcode']) ?>&dpi=200" class="img-fluid" alt="barcode">
                    <div class="small"><?= e($label['barcode']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function toggleMode() {
    const mode = document.getElementById('mode').value;
    document.getElementById('productWrap').classList.toggle('d-none', mode !== 'product');
    document.getElementById('containerWrap').classList.toggle('d-none', mode !== 'container');
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
