<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pageTitle = 'Exhibition Packing List';
$activePage = 'packing';
require_once __DIR__ . '/../includes/header.php';

$exhibitions = $pdo->query("SELECT id, name FROM exhibitions ORDER BY id DESC")->fetchAll();
$products = $pdo->query("SELECT id, name, barcode, quantity AS stock FROM products ORDER BY name ASC")->fetchAll();
?>

<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Exhibition Packing List</h5>
        <button class="btn btn-sm btn-is2" data-bs-toggle="modal" data-bs-target="#addItemModal">
            <i class="fa-solid fa-plus me-1"></i>Add Item to Stall
        </button>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row align-items-end">
                <div class="col-12 col-md-4">
                    <label class="form-label">Manage Exhibition Stall</label>
                    <select id="packingExhibitionId" class="form-select" onchange="loadPackingList()">
                        <option value="">-- Select Exhibition --</option>
                        <?php foreach ($exhibitions as $ex): ?>
                            <option value="<?= $ex['id'] ?>"><?= htmlspecialchars($ex['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-8 text-md-end mt-2 mt-md-0">
                    <small class="text-muted">Track items specifically allocated to an exhibition stall vs. total warehouse stock.</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3 text-nowrap">Product Details</th>
                            <th class="text-center text-nowrap">Warehouse Stock</th>
                            <th class="text-center text-nowrap">Sent to Stall</th>
                            <th class="text-center text-nowrap">Total Sold 🛒</th>
                            <th class="text-center text-nowrap">Returned</th>
                            <th class="text-center text-nowrap">Stall Current Stock</th>
                            <th class="pe-3 text-end text-nowrap">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="packingListTbody">
                        <tr><td colspan="7" class="text-muted text-center py-5">Please select an exhibition to manage stall stock.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal to add items to the packing list -->
<div class="modal fade" id="addItemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Allocate Stock to Stall</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addItemForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Select Product</label>
                        <select name="product_id" class="form-select" required>
                            <option value="">Search product...</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= $p['id'] ?>">
                                    <?= htmlspecialchars($p['name']) ?> 
                                    (Code: <?= $p['barcode'] ?> | WH: <?= $p['stock'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quantity to Send</label>
                        <input type="number" name="quantity_sent" class="form-control" min="1" required placeholder="e.g. 50">
                        <small class="text-muted">This does NOT deduct from warehouse stock; it only tracks what's physically at the stall.</small>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-is2">Save Assignment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
async function loadPackingList() {
    const exId = document.getElementById('packingExhibitionId').value;
    const tbody = document.getElementById('packingListTbody');
    if (!exId) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-muted text-center py-5">Please select an exhibition.</td></tr>';
        return;
    }

    const resp = await fetch(`<?= BASE_URL ?>admin/api/packing_data.php?action=list&exhibition_id=${exId}`);
    const data = await resp.json();
    
    let html = '';
    if (data.length === 0) {
        html = '<tr><td colspan="7" class="text-muted text-center py-5">No items specifically assigned to this stall yet.</td></tr>';
    } else {
        data.forEach(item => {
            const sold = item.quantity_sold || 0;
            const net = item.quantity_sent - sold - item.quantity_returned;
            html += `<tr>
                <td class="ps-3">
                    <div class="fw-bold">${item.product_name}</div>
                    <small class="text-muted">${item.barcode}</small>
                </td>
                <td class="text-center"><span class="badge bg-secondary">${item.current_stock}</span></td>
                <td class="text-center"><span class="fw-bold">${item.quantity_sent}</span></td>
                <td class="text-center text-primary fw-bold">${sold}</td>
                <td class="text-center">
                    <input type="number" class="form-control form-control-sm text-center mx-auto" 
                    value="${item.quantity_returned}" 
                    style="width: 70px" 
                    onchange="updateReturned(${item.id}, this.value)">
                </td>
                <td class="text-center">
                    <span class="badge ${net > 0 ? 'bg-success' : 'bg-warning'} fs-6">${net}</span>
                </td>
                <td class="pe-3 text-end">
                    <button class="btn btn-link text-danger p-0" onclick="deletePackingItem(${item.id})">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                </td>
            </tr>`;
        });
    }
    tbody.innerHTML = html;
}

async function updateReturned(id, val) {
    await fetch('<?= BASE_URL ?>admin/api/packing_data.php?action=update_returned', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ id: id, quantity_returned: val })
    });
    // No reload for smooth experience, but could update total
    const exId = document.getElementById('packingExhibitionId').value;
    setTimeout(() => loadPackingList(), 500); 
}

async function deletePackingItem(id) {
    if (!confirm('Remove this product from the stall tracking list?')) return;
    await fetch('<?= BASE_URL ?>admin/api/packing_data.php?action=delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ id: id })
    });
    loadPackingList();
}

document.getElementById('addItemForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const exId = document.getElementById('packingExhibitionId').value;
    if (!exId) { alert('Select an exhibition first.'); return; }

    const formData = new FormData(e.target);
    const bodyObj = {
        action: 'add',
        exhibition_id: exId,
        product_id: formData.get('product_id'),
        quantity_sent: formData.get('quantity_sent')
    };

    const resp = await fetch('<?= BASE_URL ?>admin/api/packing_data.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(bodyObj)
    });
    
    const result = await resp.json();
    if (result.success) {
        bootstrap.Modal.getInstance(document.getElementById('addItemModal')).hide();
        e.target.reset();
        loadPackingList();
    } else {
        alert(result.error);
    }
});

// Auto-select exhibition if ID provided in URL
window.addEventListener('load', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const exId = urlParams.get('exhibition_id');
    if (exId) {
        document.getElementById('packingExhibitionId').value = exId;
        loadPackingList();
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
