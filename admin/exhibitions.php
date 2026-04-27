<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$pageTitle = 'Exhibitions';
$activePage = 'products';
$message = '';

if (is_post()) {
    $name = trim($_POST['name'] ?? '');
    if ($name !== '') {
        $ins = $pdo->prepare('INSERT INTO exhibitions (name) VALUES (:name)');
        $ins->execute([':name' => $name]);
        $message = 'Exhibition added.';
    }
}

$rows = $pdo->query('SELECT e.id, e.name, COUNT(ep.id) AS products_count
                     FROM exhibitions e
                     LEFT JOIN exhibition_products ep ON ep.exhibition_id = e.id
                     GROUP BY e.id
                     ORDER BY e.id DESC')->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="row g-3">
    <div class="col-12 col-md-5">
        <div class="card">
            <div class="card-body">
                <h5 class="mb-3">Add Exhibition</h5>
                <?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
                <form method="post" class="d-grid gap-2">
                    <input type="text" name="name" class="form-control" placeholder="Exhibition Name" required>
                    <button class="btn btn-is2">Save Exhibition</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-7">
        <div class="card">
            <div class="card-body">
                <h5 class="mb-3">Exhibitions</h5>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr><th>ID</th><th>Name</th><th>Linked Products</th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?= (int)$r['id'] ?></td>
                                <td><?= e($r['name']) ?></td>
                                <td><?= (int)$r['products_count'] ?></td>
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
