<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$pageTitle = 'Edit Product';
$activePage = 'products';

$productId = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $productId]);
$product = $stmt->fetch();

if (!$product) {
    redirect_to(BASE_URL . '');
}

$error = '';
$success = '';

if (is_post()) {
    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $barcode = trim($_POST['barcode'] ?? '');
    $costPrice = (float)($_POST['cost_price'] ?? 0);
    $sellingPrice = (float)($_POST['selling_price'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 0);
    $description = trim($_POST['description'] ?? '');

    if ($name === '' || $barcode === '' || $quantity < 0) {
        $error = 'Please fill required fields and valid quantity.';
    } else {
        $check = $pdo->prepare('SELECT id FROM products WHERE barcode = :barcode AND id != :id LIMIT 1');
        $check->execute([':barcode' => $barcode, ':id' => $productId]);
        if ($check->fetch()) {
            $error = 'Barcode already used by another product.';
        } else {
            $imageFilename = $product['image'];
            $newImage = upload_product_image($_FILES['image'] ?? []);
            if ($newImage) {
                $imageFilename = $newImage;
            }

            $update = $pdo->prepare('UPDATE products
                                     SET name = :name, category = :category, barcode = :barcode, cost_price = :cost_price,
                                         selling_price = :selling_price, quantity = :quantity, description = :description, image = :image
                                     WHERE id = :id');
            $update->execute([
                ':name' => $name,
                ':category' => $category,
                ':barcode' => $barcode,
                ':cost_price' => $costPrice,
                ':selling_price' => $sellingPrice,
                ':quantity' => $quantity,
                ':description' => $description,
                ':image' => $imageFilename,
                ':id' => $productId,
            ]);

            $pdo->prepare('DELETE FROM exhibition_products WHERE product_id = :pid')->execute([':pid' => $productId]);
            $selectedExhibitions = $_POST['exhibitions'] ?? [];
            if (is_array($selectedExhibitions)) {
                $ins = $pdo->prepare('INSERT INTO exhibition_products (exhibition_id, product_id) VALUES (:eid, :pid)');
                foreach ($selectedExhibitions as $eid) {
                    $ins->execute([':eid' => (int)$eid, ':pid' => $productId]);
                }
            }

            $success = 'Product updated.';
            $stmt->execute([':id' => $productId]);
            $product = $stmt->fetch();
        }
    }
}

$exhibitions = $pdo->query('SELECT id, name FROM exhibitions ORDER BY name')->fetchAll();
$linkedStmt = $pdo->prepare('SELECT exhibition_id FROM exhibition_products WHERE product_id = :pid');
$linkedStmt->execute([':pid' => $productId]);
$linked = array_map('intval', array_column($linkedStmt->fetchAll(), 'exhibition_id'));

require_once __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <div class="card-body">
        <h5 class="mb-3">Edit Product</h5>
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

        <form method="post" enctype="multipart/form-data" class="row g-3">
            <div class="col-12 col-md-6">
                <label class="form-label">Product Name</label>
                <input type="text" name="name" class="form-control" value="<?= e($product['name']) ?>" required>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label">Category</label>
                <input type="text" name="category" class="form-control" value="<?= e((string)$product['category']) ?>">
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label">Barcode</label>
                <input type="text" name="barcode" class="form-control" value="<?= e($product['barcode']) ?>" required>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label">Image</label>
                <input type="file" name="image" class="form-control" accept="image/*">
            </div>
            <div class="col-6 col-md-4">
                <label class="form-label">Cost Price</label>
                <input type="number" step="0.01" name="cost_price" class="form-control" value="<?= e((string)$product['cost_price']) ?>" required>
            </div>
            <div class="col-6 col-md-4">
                <label class="form-label">Selling Price</label>
                <input type="number" step="0.01" name="selling_price" class="form-control" value="<?= e((string)$product['selling_price']) ?>" required>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label">Quantity</label>
                <input type="number" name="quantity" class="form-control" value="<?= (int)$product['quantity'] ?>" required>
            </div>
            <div class="col-12">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3"><?= e((string)$product['description']) ?></textarea>
            </div>
            <div class="col-12">
                <label class="form-label">Link To Exhibitions</label>
                <div class="row g-2">
                    <?php foreach ($exhibitions as $ex): ?>
                        <div class="col-12 col-md-6">
                            <div class="form-check border rounded p-2">
                                <input class="form-check-input" type="checkbox" name="exhibitions[]" value="<?= (int)$ex['id'] ?>" id="ex<?= (int)$ex['id'] ?>" <?= in_array((int)$ex['id'], $linked, true) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="ex<?= (int)$ex['id'] ?>"><?= e($ex['name']) ?></label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-12 d-grid d-md-flex gap-2">
                <button class="btn btn-is2">Update Product</button>
                <a href="<?= BASE_URL ?>admin/products.php" class="btn btn-outline-secondary">Back</a>
            </div>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
