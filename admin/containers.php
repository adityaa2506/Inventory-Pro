<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$pageTitle = 'Containers';
$activePage = 'products';
$message = '';
$error = '';

if (is_post() && ($_POST['form_type'] ?? '') === 'create_container') {
    $name = trim($_POST['container_name'] ?? '');
    $barcode = trim($_POST['barcode'] ?? generate_barcode());

    if ($name === '' || $barcode === '') {
        $error = 'Container name and barcode are required.';
    } else {
        $check = $pdo->prepare('SELECT id FROM containers WHERE barcode = :barcode LIMIT 1');
        $check->execute([':barcode' => $barcode]);
        if ($check->fetch()) {
            $error = 'Container barcode already exists.';
        } else {
            $ins = $pdo->prepare('INSERT INTO containers (container_name, barcode) VALUES (:name, :barcode)');
            $ins->execute([':name' => $name, ':barcode' => $barcode]);
            $message = 'Container created successfully.';
        }
    }
}

if (is_post() && ($_POST['form_type'] ?? '') === 'add_item') {
    $containerId = (int)($_POST['container_id'] ?? 0);
    $productId = (int)($_POST['product_id'] ?? 0);
    $qty = max(1, (int)($_POST['quantity'] ?? 1));

    if ($containerId > 0 && $productId > 0) {
        $stmt = $pdo->prepare('SELECT id FROM container_items WHERE container_id = :cid AND product_id = :pid LIMIT 1');
        $stmt->execute([':cid' => $containerId, ':pid' => $productId]);
        $exists = $stmt->fetchColumn();

        if ($exists) {
            $upd = $pdo->prepare('UPDATE container_items SET quantity = :qty WHERE id = :id');
            $upd->execute([':qty' => $qty, ':id' => $exists]);
        } else {
            $ins = $pdo->prepare('INSERT INTO container_items (container_id, product_id, quantity) VALUES (:cid, :pid, :qty)');
            $ins->execute([':cid' => $containerId, ':pid' => $productId, ':qty' => $qty]);
        }
        $message = 'Container item saved.';
    }
}

$containers = $pdo->query('SELECT c.*, COUNT(ci.id) AS item_count
                           FROM containers c
                           LEFT JOIN container_items ci ON ci.container_id = c.id
                           GROUP BY c.id
                           ORDER BY c.id DESC')->fetchAll();
$products = $pdo->query('SELECT id, name, barcode FROM products ORDER BY name')->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Container System</h5>
    <a href="<?= BASE_URL ?>admin/scan.php" class="btn btn-outline-primary btn-sm">Scan to Sell</a>
</div>

<?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<div class="row g-3">
    <div class="col-12 col-md-5">
        <div class="card">
            <div class="card-body">
                <h6>Create Container</h6>
                <form method="post" class="row g-2">
                    <input type="hidden" name="form_type" value="create_container">
                    <div class="col-12">
                        <input type="text" name="container_name" class="form-control" placeholder="Container name" required>
                    </div>
                    <div class="col-12">
                        <input type="text" name="barcode" class="form-control" placeholder="Barcode" value="<?= e(generate_barcode()) ?>" required>
                    </div>
                    <div class="col-12 d-grid">
                        <button class="btn btn-is2">Create</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-md-7">
        <div class="card">
            <div class="card-body">
                <h6>Add Products To Container</h6>
                <form method="post" class="row g-2 mb-3">
                    <input type="hidden" name="form_type" value="add_item">
                    <div class="col-12 col-md-4">
                        <select name="container_id" class="form-select" required>
                            <option value="">Container</option>
                            <?php foreach ($containers as $c): ?>
                                <option value="<?= (int)$c['id'] ?>"><?= e($c['container_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-5">
                        <select name="product_id" class="form-select" required>
                            <option value="">Product</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?> (<?= e($p['barcode']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-1">
                        <input type="number" name="quantity" min="1" value="1" class="form-control" required>
                    </div>
                    <div class="col-6 col-md-2 d-grid">
                        <button class="btn btn-dark">Add</button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr><th>Name</th><th>Barcode</th><th>Items</th><th>Thermal</th></tr></thead>
                        <tbody>
                        <?php foreach ($containers as $c): ?>
                            <tr>
                                <td><?= e($c['container_name']) ?></td>
                                <td><?= e($c['barcode']) ?></td>
                                <td><?= (int)$c['item_count'] ?></td>
                                <td><a href="<?= BASE_URL ?>admin/thermal_labels.php?container_id=<?= (int)$c['id'] ?>" class="btn btn-sm btn-outline-secondary">Print</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
