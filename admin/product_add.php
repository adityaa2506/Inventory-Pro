<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$pageTitle = 'Add Product';
$activePage = 'add_product';
$error = '';
$success = '';
$generatedBarcode = generate_barcode();
$containersStmt = $pdo->query('SELECT id, container_name FROM containers ORDER BY container_name ASC');
$containers = $containersStmt->fetchAll();

if (is_post()) {
    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $containerIdRaw = $_POST['container_id'] ?? '';
    $containerId = ctype_digit((string)$containerIdRaw) ? (int)$containerIdRaw : 0;
    $barcode = trim($_POST['barcode'] ?? '');
    $costPrice = (float)($_POST['cost_price'] ?? 0);
    $sellingPrice = (float)($_POST['selling_price'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 0);
    $description = trim($_POST['description'] ?? '');

    if ($containerId > 0) {
        $containerStmt = $pdo->prepare('SELECT container_name FROM containers WHERE id = :id LIMIT 1');
        $containerStmt->execute([':id' => $containerId]);
        $containerName = $containerStmt->fetchColumn();
        if ($containerName === false) {
            $error = 'Selected container was not found.';
        } else {
            $category = trim((string)$containerName);
        }
    }

    if ($error === '') {
        if ($name === '' || $barcode === '') {
            $error = 'Product name and barcode are required.';
        } elseif ($containerId === 0 && $category === '') {
            $error = 'Please enter a category or select a container.';
        } elseif ($quantity < 0) {
            $error = 'Quantity cannot be negative.';
        } else {
            $check = $pdo->prepare('SELECT id FROM products WHERE barcode = :barcode LIMIT 1');
            $check->execute([':barcode' => $barcode]);
            if ($check->fetch()) {
                $error = 'Barcode already exists.';
            } else {
                $imageFilename = upload_product_image($_FILES['image'] ?? []);

                $insert = $pdo->prepare('INSERT INTO products (name, category, barcode, cost_price, selling_price, quantity, description, image)
                                         VALUES (:name, :category, :barcode, :cost_price, :selling_price, :quantity, :description, :image)');
                $insert->execute([
                    ':name' => $name,
                    ':category' => $category,
                    ':barcode' => $barcode,
                    ':cost_price' => $costPrice,
                    ':selling_price' => $sellingPrice,
                    ':quantity' => $quantity,
                    ':description' => $description,
                    ':image' => $imageFilename,
                ]);

                $productId = (int)$pdo->lastInsertId();
                if ($quantity > 0) {
                    log_inventory($pdo, [
                        'product_id' => $productId,
                        'barcode' => $barcode,
                        'product_name' => $name,
                        'quantity_change' => $quantity,
                        'action_type' => 'ADD',
                        'note' => 'Initial stock',
                        'sale_price' => null,
                        'cost_price' => $costPrice,
                        'exhibition_id' => null,
                    ]);
                }

                $success = 'Product added successfully.';
                $generatedBarcode = generate_barcode();
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <div class="card-body">
        <h5 class="mb-3">Add Product</h5>
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

        <form method="post" enctype="multipart/form-data" class="row g-3">
            <div class="col-12 col-md-6">
                <label class="form-label">Product Name</label>
                <input type="text" name="name" class="form-control" required>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label">Container</label>
                <select id="containerSelect" name="container_id" class="form-select" onchange="toggleCategoryInput()">
                    <option value="">None (Use custom category)</option>
                    <?php foreach ($containers as $container): ?>
                        <option value="<?= (int)$container['id'] ?>"><?= e($container['container_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div id="customCategoryWrap" class="col-12 col-md-6">
                <label class="form-label">Category</label>
                <input id="categoryInput" type="text" name="category" class="form-control" placeholder="Enter custom category">
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label">Barcode</label>
                <div class="input-group">
                    <input id="barcodeInput" type="text" name="barcode" class="form-control" value="<?= e($generatedBarcode) ?>" required>
                    <button type="button" class="btn btn-outline-secondary" onclick="regenBarcode()">Generate</button>
                </div>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label">Image</label>
                <input type="file" name="image" class="form-control" accept="image/*">
            </div>
            <div class="col-6 col-md-4">
                <label class="form-label">Cost Price</label>
                <input type="number" step="0.01" name="cost_price" class="form-control" required>
            </div>
            <div class="col-6 col-md-4">
                <label class="form-label">Selling Price</label>
                <input type="number" step="0.01" name="selling_price" class="form-control" required>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label">Quantity</label>
                <input type="number" name="quantity" class="form-control" required>
            </div>
            <div class="col-12">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3"></textarea>
            </div>
            <div class="col-12 d-grid d-md-flex">
                <button class="btn btn-is2">Save Product</button>
            </div>
        </form>
    </div>
</div>
<script>
function regenBarcode() {
    const seed = Date.now().toString().slice(-10);
    document.getElementById('barcodeInput').value = 'IS2' + seed;
}

function toggleCategoryInput() {
    const containerSelect = document.getElementById('containerSelect');
    const categoryInput = document.getElementById('categoryInput');
    const customCategoryWrap = document.getElementById('customCategoryWrap');
    const isCustomCategory = containerSelect.value === '';

    categoryInput.disabled = !isCustomCategory;
    categoryInput.required = isCustomCategory;
    customCategoryWrap.style.opacity = isCustomCategory ? '1' : '0.6';

    if (!isCustomCategory) {
        categoryInput.value = '';
    }
}

document.addEventListener('DOMContentLoaded', toggleCategoryInput);
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
