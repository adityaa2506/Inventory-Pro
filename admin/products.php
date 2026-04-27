<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$pageTitle = 'Products';
$activePage = 'products';
$message = '';
$error = '';

if (is_post() && isset($_POST['direct_sale_product_id'])) {
    $productId = (int)$_POST['direct_sale_product_id'];
    $sellQty = max(1, (int)($_POST['sell_qty'] ?? 1));
    $overridePrice = trim($_POST['sale_price'] ?? '');
    $note = trim($_POST['sale_note'] ?? '');
    $useExhibition = isset($_POST['use_exhibition']) ? 1 : 0;
    $exhibitionId = $useExhibition ? (int)($_POST['exhibition_id'] ?? 0) : null;

    $productStmt = $pdo->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
    $productStmt->execute([':id' => $productId]);
    $product = $productStmt->fetch();

    if (!$product) {
        $error = 'Product not found.';
    } else {
        $isValidExhibition = true;
        if ($exhibitionId) {
            $linkStmt = $pdo->prepare('SELECT COUNT(*) FROM exhibition_products WHERE product_id = :pid AND exhibition_id = :eid');
            $linkStmt->execute([':pid' => $productId, ':eid' => $exhibitionId]);
            $isValidExhibition = (int)$linkStmt->fetchColumn() > 0;
        }

        if ((int)$product['quantity'] < $sellQty) {
            $error = 'Insufficient stock for direct sale.';
        } elseif (!$isValidExhibition) {
            $error = 'Invalid exhibition selection for this product.';
        } else {
            $salePrice = $overridePrice === ''
                ? (float)$product['selling_price']
                : per_unit_price_from_total((float)$overridePrice, $sellQty);
            $newQty = (int)$product['quantity'] - $sellQty;

            $update = $pdo->prepare('UPDATE products SET quantity = :qty WHERE id = :id');
            $update->execute([':qty' => $newQty, ':id' => $productId]);

            log_inventory($pdo, [
                'product_id' => $productId,
                'barcode' => $product['barcode'],
                'product_name' => $product['name'],
                'quantity_change' => -$sellQty,
                'action_type' => 'SALE',
                'note' => $note,
                'sale_price' => $salePrice,
                'cost_price' => $product['cost_price'],
                'exhibition_id' => $exhibitionId ?: null,
            ]);

            $message = 'Direct sale completed successfully.';
        }
    }
}

if (is_post() && isset($_POST['stock_product_id'])) {
    $productId = (int)$_POST['stock_product_id'];
    $changeQty = (int)($_POST['change_qty'] ?? 0);
    $note = trim($_POST['note'] ?? 'Stock adjustment');

    $productStmt = $pdo->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
    $productStmt->execute([':id' => $productId]);
    $product = $productStmt->fetch();

    if ($product && $changeQty !== 0) {
        $newQty = (int)$product['quantity'] + $changeQty;
        if ($newQty >= 0) {
            $update = $pdo->prepare('UPDATE products SET quantity = :qty WHERE id = :id');
            $update->execute([':qty' => $newQty, ':id' => $productId]);

            log_inventory($pdo, [
                'product_id' => $productId,
                'barcode' => $product['barcode'],
                'product_name' => $product['name'],
                'quantity_change' => $changeQty,
                'action_type' => 'ADD',
                'note' => $note,
                'sale_price' => null,
                'cost_price' => $product['cost_price'],
                'exhibition_id' => null,
            ]);

            $message = 'Stock updated successfully.';
        } else {
            $message = 'Stock cannot go below zero.';
        }
    }
}

$search = trim($_GET['search'] ?? '');
$sql = 'SELECT p.*, GROUP_CONCAT(e.name SEPARATOR ", ") AS exhibitions
        FROM products p
        LEFT JOIN exhibition_products ep ON ep.product_id = p.id
        LEFT JOIN exhibitions e ON e.id = ep.exhibition_id';
$params = [];
if ($search !== '') {
    $sql .= ' WHERE p.name LIKE :q OR p.barcode LIKE :q OR p.category LIKE :q';
    $params[':q'] = '%' . $search . '%';
}
$sql .= ' GROUP BY p.id ORDER BY p.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$exhibitionMap = [];
$exhibitionsStmt = $pdo->query('SELECT ep.product_id, e.id, e.name
                                FROM exhibition_products ep
                                INNER JOIN exhibitions e ON e.id = ep.exhibition_id
                                ORDER BY e.name ASC');
foreach ($exhibitionsStmt->fetchAll() as $row) {
    $pid = (int)$row['product_id'];
    if (!isset($exhibitionMap[$pid])) {
        $exhibitionMap[$pid] = [];
    }
    $exhibitionMap[$pid][] = [
        'id' => (int)$row['id'],
        'name' => $row['name'],
    ];
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <h5 class="mb-0">Products</h5>
    <div class="d-grid d-sm-flex gap-2">
        <a href="<?= BASE_URL ?>admin/exhibitions.php" class="btn btn-outline-dark btn-sm">Exhibitions</a>
        <button type="button" class="btn btn-outline-success btn-sm" onclick="exportProducts()">Export CSV</button>
        <a href="<?= BASE_URL ?>admin/product_add.php" class="btn btn-is2 btn-sm">Add Product</a>
    </div>
</div>

<script>
function exportProducts() {
    const search = '<?= urlencode($search) ?>';
    const url = `<?= BASE_URL ?>admin/export.php?type=products&search=${search}`;
    window.location.href = url;
}
</script>

<?php if ($message): ?>
    <div class="alert alert-info"><?= e($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2" method="get" id="productSearchForm">
            <div class="col-12 col-md-9">
                <input type="text" id="productSearchInput" name="search" class="form-control" placeholder="Search name, category, barcode" value="<?= e($search) ?>" autocomplete="off">
            </div>
            <div class="col-6 col-md-2 d-grid">
                <button class="btn btn-outline-secondary">Search</button>
            </div>
            <div class="col-6 col-md-1 d-grid">
                <button type="button" id="clearSearchBtn" class="btn btn-outline-dark">Clear</button>
            </div>
            <div class="col-12 mt-2 small text-muted" id="searchStatus"><?= count($products) ?> product(s)</div>
        </form>
    </div>
</div>

<div class="row g-3" id="productsGrid">
<?php foreach ($products as $p): ?>
    <?php
    $searchBlob = strtolower(trim(
        $p['name'] . ' ' .
        ((string)$p['category']) . ' ' .
        $p['barcode'] . ' ' .
        ((string)$p['exhibitions'])
    ));
    ?>
    <div class="col-12 col-md-6 col-lg-4" data-search="<?= e($searchBlob) ?>">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex flex-column flex-sm-row gap-3">
                    <?php $productImageSrc = $p['image'] ? BASE_URL . 'uploads/' . e($p['image']) : 'https://placehold.co/900x900?text=No+Image'; ?>
                    <button
                        type="button"
                        class="p-0 border-0 bg-transparent"
                        data-bs-toggle="modal"
                        data-bs-target="#productImageModal"
                        data-image-src="<?= $productImageSrc ?>"
                        data-image-name="<?= e($p['name']) ?>"
                        title="View image"
                    >
                        <img src="<?= $productImageSrc ?>" width="72" height="72" class="rounded object-fit-cover" alt="<?= e($p['name']) ?>" style="cursor: zoom-in;">
                    </button>
                    <div>
                        <h6 class="mb-1"><?= e($p['name']) ?></h6>
                        <div class="small text-muted"><?= e((string)$p['category']) ?></div>
                        <div class="small"><strong>Qty:</strong> <?= (int)$p['quantity'] ?></div>
                        <div class="small"><strong>SP:</strong> <?= format_money((float)$p['selling_price']) ?></div>
                        <div class="small"><strong>CP:</strong> <?= format_money((float)$p['cost_price']) ?></div>
                    </div>
                </div>
                <hr>
                <div class="small mb-1"><strong>Barcode:</strong> <?= e($p['barcode']) ?></div>
                <img src="https://bwipjs-api.metafloor.com/?bcid=code128&text=<?= urlencode($p['barcode']) ?>&dpi=96" class="img-fluid" alt="barcode">
                <div class="small text-muted mt-2">Exhibitions: <?= e($p['exhibitions'] ?: 'None') ?></div>
                <div class="row g-2 mt-3">
                    <div class="col-6 d-grid">
                        <a href="<?= BASE_URL ?>admin/product_edit.php?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                    </div>
                    <div class="col-6 d-grid">
                        <a href="<?= BASE_URL ?>admin/barcode_labels.php?product_id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-success">Labels</a>
                    </div>
                    <div class="col-12 d-grid">
                        <a href="<?= BASE_URL ?>admin/product_log.php?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-dark">View Log</a>
                    </div>
                </div>
                <form method="post" class="mt-3 row g-2 border rounded p-2 bg-light-subtle">
                    <input type="hidden" name="direct_sale_product_id" value="<?= (int)$p['id'] ?>">
                    <div class="col-6">
                        <label class="form-label small mb-1">Sell Qty</label>
                        <input type="number" min="1" name="sell_qty" class="form-control form-control-sm" value="1" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small mb-1">Total Sale Price Override</label>
                        <input type="number" step="0.01" min="0" name="sale_price" class="form-control form-control-sm" placeholder="Optional">
                    </div>
                    <?php if (!empty($exhibitionMap[(int)$p['id']])): ?>
                    <div class="col-12">
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="checkbox" value="1" id="useEx<?= (int)$p['id'] ?>" name="use_exhibition" onchange="toggleDirectExhibition(<?= (int)$p['id'] ?>)">
                            <label class="form-check-label small" for="useEx<?= (int)$p['id'] ?>">Sold in exhibition</label>
                        </div>
                    </div>
                    <div class="col-12" id="exWrap<?= (int)$p['id'] ?>" style="display:none;">
                        <label class="form-label small mb-1">Select Exhibition</label>
                        <select name="exhibition_id" class="form-select form-select-sm">
                            <option value="">Choose</option>
                            <?php foreach ($exhibitionMap[(int)$p['id']] as $ex): ?>
                                <option value="<?= (int)$ex['id'] ?>"><?= e($ex['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="col-12">
                        <input type="text" name="sale_note" class="form-control form-control-sm" placeholder="Sale note (optional)">
                    </div>
                    <div class="col-12 d-grid">
                        <button class="btn btn-sm btn-danger">Direct Sale</button>
                    </div>
                </form>
                <form method="post" class="mt-3 row g-2">
                    <input type="hidden" name="stock_product_id" value="<?= (int)$p['id'] ?>">
                    <div class="col-4">
                        <input type="number" name="change_qty" class="form-control form-control-sm" required placeholder="+/-">
                    </div>
                    <div class="col-8">
                        <input type="text" name="note" class="form-control form-control-sm" placeholder="Adjustment note">
                    </div>
                    <div class="col-12 d-grid">
                        <button class="btn btn-sm btn-dark">Update Stock</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>
<div id="noResultsState" class="alert alert-warning mt-3 d-none">No products match your search.</div>

<div class="modal fade" id="productImageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="productImageTitle">Product Image</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img id="productImagePreview" src="" class="img-fluid rounded" alt="Product preview">
            </div>
        </div>
    </div>
</div>

<script>
function toggleDirectExhibition(productId) {
    const checkbox = document.getElementById('useEx' + productId);
    const wrap = document.getElementById('exWrap' + productId);
    if (!checkbox || !wrap) return;
    wrap.style.display = checkbox.checked ? 'block' : 'none';
}

const searchInput = document.getElementById('productSearchInput');
const clearSearchBtn = document.getElementById('clearSearchBtn');
const searchStatus = document.getElementById('searchStatus');
const noResultsState = document.getElementById('noResultsState');
const productCards = Array.from(document.querySelectorAll('#productsGrid > [data-search]'));

function applyRealtimeSearch() {
    const query = (searchInput?.value || '').toLowerCase().trim();
    let visibleCount = 0;

    productCards.forEach((card) => {
        const haystack = card.getAttribute('data-search') || '';
        const matches = query === '' || haystack.includes(query);
        card.classList.toggle('d-none', !matches);
        if (matches) {
            visibleCount += 1;
        }
    });

    if (searchStatus) {
        searchStatus.textContent = visibleCount + ' product(s) shown';
    }
    if (noResultsState) {
        noResultsState.classList.toggle('d-none', visibleCount !== 0);
    }
}

if (searchInput) {
    searchInput.addEventListener('input', applyRealtimeSearch);
}

if (clearSearchBtn) {
    clearSearchBtn.addEventListener('click', () => {
        if (!searchInput) return;
        searchInput.value = '';
        searchInput.focus();
        applyRealtimeSearch();
    });
}

applyRealtimeSearch();

const productImageModal = document.getElementById('productImageModal');
if (productImageModal) {
    productImageModal.addEventListener('show.bs.modal', (event) => {
        const trigger = event.relatedTarget;
        if (!trigger) return;

        const imageSrc = trigger.getAttribute('data-image-src') || '';
        const imageName = trigger.getAttribute('data-image-name') || 'Product Image';
        const imageEl = document.getElementById('productImagePreview');
        const titleEl = document.getElementById('productImageTitle');

        if (imageEl) {
            imageEl.src = imageSrc;
            imageEl.alt = imageName;
        }
        if (titleEl) {
            titleEl.textContent = imageName;
        }
    });
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
