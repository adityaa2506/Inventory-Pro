<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$pageTitle = 'Point of Sale';
$activePage = 'pos';
$error = '';
$success = '';

// Process Checkout
if (is_post() && isset($_POST['cart_data'])) {
    $cartData = json_decode($_POST['cart_data'], true);
    $exhibitionId = !empty($_POST['exhibition_id']) ? (int)$_POST['exhibition_id'] : null;
    $customerName = trim($_POST['customer_name'] ?? '');
    $customerNumber = trim($_POST['customer_number'] ?? '');
    $noteParts = ['Sold via POS'];
    if ($customerName !== '') {
        $noteParts[] = 'Customer: ' . $customerName;
    }
    if ($customerNumber !== '') {
        $noteParts[] = 'Phone: ' . $customerNumber;
    }
    $baseNote = implode(' | ', $noteParts);
    
    if (is_array($cartData) && count($cartData) > 0) {
        $pdo->beginTransaction();
        try {
            $totalSale = 0;
            $itemsProcessed = 0;
            
            foreach ($cartData as $item) {
                $itemType = $item['item_type'] ?? 'product';
                $qty = (int)$item['qty'];
                $price = (float)$item['price'];
                
                if ($qty <= 0) continue;

                $note = $baseNote;

                if ($itemType === 'custom') {
                    $customName = trim((string)($item['name'] ?? 'Custom Item'));
                    $customCost = (float)($item['cost_price'] ?? 0);

                    if ($customName === '' || $price < 0 || $customCost < 0) {
                        throw new Exception('Invalid custom item in cart.');
                    }

                    log_inventory($pdo, [
                        'product_id' => null,
                        'barcode' => null,
                        'product_name' => $customName,
                        'quantity_change' => -$qty,
                        'action_type' => 'MANUAL_SALE',
                        'note' => $note,
                        'sale_price' => per_unit_price_from_total($price, $qty),
                        'cost_price' => per_unit_price_from_total($customCost, $qty),
                        'exhibition_id' => $exhibitionId
                    ]);

                    $totalSale += ($price * $qty);
                    $itemsProcessed++;
                    continue;
                }

                $productId = (int)$item['id'];

                // get product to double check
                $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ? FOR UPDATE');
                $stmt->execute([$productId]);
                $product = $stmt->fetch();
                
                if (!$product) {
                    throw new Exception("Product ID $productId not found.");
                }

                if ((int)$product['quantity'] < $qty) {
                    throw new Exception('Insufficient stock for ' . $product['name'] . '.');
                }

                $newQty = $product['quantity'] - $qty;
                
                $update = $pdo->prepare('UPDATE products SET quantity = ? WHERE id = ?');
                $update->execute([$newQty, $productId]);

                log_inventory($pdo, [
                    'product_id' => $productId,
                    'barcode' => $product['barcode'],
                    'product_name' => $product['name'],
                    'quantity_change' => -$qty,
                    'action_type' => 'SALE',
                    'note' => $note,
                    'sale_price' => $price,
                    'cost_price' => $product['cost_price'],
                    'exhibition_id' => $exhibitionId
                ]);
                
                $totalSale += ($price * $qty);
                $itemsProcessed++;
            }
            
            $pdo->commit();
            if ($itemsProcessed > 0) {
                $success = "Checkout successful! $itemsProcessed items processed. Total: " . format_money($totalSale);
            } else {
                $error = "No valid items in cart.";
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Checkout failed: " . $e->getMessage();
        }
    } else {
        $error = "Cart is empty.";
    }
}

$exhibitions = $pdo->query('SELECT id, name FROM exhibitions ORDER BY name')->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.pos-container {
    height: calc(100vh - 120px);
    display: flex;
    gap: 1rem;
}
.pos-products {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.pos-cart-panel {
    width: 380px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid #e2e8f0;
}
.search-results {
    flex: 1;
    overflow-y: auto;
    padding-right: 0.5rem;
}
.product-card {
    cursor: pointer;
    transition: all 0.2s;
    border: 1px solid #e2e8f0;
}
.product-card:hover {
    border-color: var(--ipv2-primary);
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(30, 64, 175, 0.1);
}
.cart-items {
    flex: 1;
    overflow-y: auto;
    padding: 1rem;
}
.cart-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-bottom: 0.75rem;
    margin-bottom: 0.75rem;
    border-bottom: 1px solid #e2e8f0;
}
.qty-btn {
    width: 28px;
    height: 28px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}
.cart-footer {
    background: #f8fafc;
    padding: 1.25rem;
    border-top: 1px solid #e2e8f0;
}
@media (max-width: 991.98px) {
    .pos-container {
        flex-direction: column;
        height: auto;
    }
    .pos-cart-panel {
        width: 100%;
        height: 500px;
    }
}
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><i class="fas fa-cash-register me-2"></i>Point of Sale</h3>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

<div class="pos-container">
    <!-- Left Side: Products & Search -->
    <div class="pos-products">
        <div class="card mb-3">
            <div class="card-body p-2">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" id="posSearch" class="form-control border-start-0 ps-0" placeholder="Search by name or scan barcode..." autocomplete="off" autofocus>
                </div>
                <div class="d-grid mt-2">
                    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#multiScanModal">
                        <i class="fas fa-barcode me-1"></i>Multi Scan Barcodes
                    </button>
                </div>
            </div>
        </div>
        <div class="search-results custom-scrollbar">
            <div class="row g-2" id="productsGrid">
                <!-- Products injected via JS -->
            </div>
            <div id="searchMessage" class="text-center text-muted mt-4">Start typing to search products.</div>
        </div>
    </div>

    <!-- Right Side: Cart -->
    <form class="pos-cart-panel" method="post" id="checkoutForm">
        <div class="bg-is2 text-white p-3 text-center fw-bold fs-5">
            Current Order
        </div>
        <div class="cart-items custom-scrollbar" id="cartContainer">
            <div class="text-center text-muted mt-4" id="emptyCartMsg">Cart is empty</div>
            <!-- Cart items injected via JS -->
        </div>
        
        <div class="cart-footer">
            <button type="button" class="btn btn-outline-primary btn-sm w-100 mb-2" data-bs-toggle="modal" data-bs-target="#customItemModal">
                <i class="fas fa-pen me-1"></i>Add Custom Sale Item
            </button>
            <div class="mb-3">
                <input type="text" name="customer_name" class="form-control form-control-sm mb-2" placeholder="Customer Name (Optional)">
                <input type="text" name="customer_number" class="form-control form-control-sm mb-2" placeholder="Customer Number (Optional)">
                <select name="exhibition_id" class="form-select form-select-sm">
                    <option value="">Standard Sale (No Exhibition)</option>
                    <?php foreach ($exhibitions as $ex): ?>
                        <option value="<?= $ex['id'] ?>"><?= e($ex['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="d-flex justify-content-between mb-3 fs-5 fw-bold">
                <span>Total:</span>
                <span id="cartTotalText">₹0.00</span>
            </div>
            <input type="hidden" name="cart_data" id="cartDataInput" value="[]">
            <button type="submit" class="btn btn-is2 w-100 py-2 fs-5" id="checkoutBtn" disabled>
                <i class="fas fa-check-circle me-2"></i>Checkout
            </button>
        </div>
    </form>
</div>

<div class="modal fade" id="multiScanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Multi Scan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="btn-group w-100 mb-3" role="group" aria-label="Multi scan mode">
                    <button type="button" id="modePasteBtn" class="btn btn-is2" onclick="setMultiScanMode('paste')">
                        <i class="fas fa-keyboard me-1"></i>Paste Barcode
                    </button>
                    <button type="button" id="modeCameraBtn" class="btn btn-outline-primary" onclick="setMultiScanMode('camera')">
                        <i class="fas fa-camera me-1"></i>Camera Scan
                    </button>
                </div>

                <div id="multiScanPasteWrap">
                    <label class="form-label">Paste barcodes (one per line, comma, or space separated)</label>
                    <textarea id="multiScanInput" class="form-control" rows="8" placeholder="ABC123&#10;ABC124&#10;ABC125"></textarea>
                    <div class="form-text">Duplicate codes will increase quantity in cart.</div>
                </div>

                <div id="multiScanCameraWrap" class="d-none">
                    <label class="form-label">Scan with camera</label>
                    <div id="posMultiScanner" class="border rounded p-2 bg-light"></div>
                    <div class="small text-muted mt-2">Scanned codes are auto-added to the barcode list below.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-is2" onclick="processMultiScan()">Add All to Cart</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="customItemModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Custom Sale</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label">Item Name</label>
                    <input type="text" id="customName" class="form-control" placeholder="e.g. Packaging Charge">
                </div>
                <div class="row g-2">
                    <div class="col-4">
                        <label class="form-label">Qty</label>
                        <input type="number" id="customQty" class="form-control" min="1" value="1">
                    </div>
                    <div class="col-4">
                        <label class="form-label">Total Sale Price</label>
                        <input type="number" id="customPrice" class="form-control" min="0" step="0.01" value="0.00">
                    </div>
                    <div class="col-4">
                        <label class="form-label">Total Cost Price</label>
                        <input type="number" id="customCost" class="form-control" min="0" step="0.01" value="0.00">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-is2" onclick="addCustomItem()">Add to Cart</button>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
<script>
const formatMoney = (amount) => {
    return '₹' + parseFloat(amount).toFixed(2);
};

let searchTimeout;
const searchInput = document.getElementById('posSearch');
const grid = document.getElementById('productsGrid');
const searchMsg = document.getElementById('searchMessage');

let cart = {};
let customItemSeq = -1;
let multiScanMode = 'paste';
let posMultiScanner = null;
let posMultiScannerStarted = false;
let lastScannedCode = '';
let lastScannedAt = 0;

function setMultiScanMode(mode) {
    multiScanMode = mode;
    const pasteWrap = document.getElementById('multiScanPasteWrap');
    const cameraWrap = document.getElementById('multiScanCameraWrap');
    const pasteBtn = document.getElementById('modePasteBtn');
    const cameraBtn = document.getElementById('modeCameraBtn');

    if (mode === 'camera') {
        pasteWrap.classList.add('d-none');
        cameraWrap.classList.remove('d-none');
        pasteBtn.classList.remove('btn-is2');
        pasteBtn.classList.add('btn-outline-primary');
        cameraBtn.classList.remove('btn-outline-primary');
        cameraBtn.classList.add('btn-is2');
        startPosMultiScanner();
    } else {
        cameraWrap.classList.add('d-none');
        pasteWrap.classList.remove('d-none');
        cameraBtn.classList.remove('btn-is2');
        cameraBtn.classList.add('btn-outline-primary');
        pasteBtn.classList.remove('btn-outline-primary');
        pasteBtn.classList.add('btn-is2');
        stopPosMultiScanner();
    }
}

function appendScannedCode(code) {
    const value = (code || '').trim();
    if (!value) return;

    const now = Date.now();
    if (value === lastScannedCode && (now - lastScannedAt) < 900) {
        return;
    }
    lastScannedCode = value;
    lastScannedAt = now;

    const input = document.getElementById('multiScanInput');
    input.value = (input.value ? input.value + '\n' : '') + value;
}

function startPosMultiScanner() {
    if (posMultiScannerStarted) return;
    const scannerEl = document.getElementById('posMultiScanner');
    if (!scannerEl) return;

    posMultiScanner = new Html5Qrcode('posMultiScanner');
    posMultiScanner
        .start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: { width: 220, height: 120 } },
            (decodedText) => appendScannedCode(decodedText),
            () => {}
        )
        .then(() => {
            posMultiScannerStarted = true;
        })
        .catch(() => {
            // Keep modal usable in paste mode even if camera cannot start.
            setMultiScanMode('paste');
            alert('Unable to start camera. Please use paste barcode mode.');
        });
}

function stopPosMultiScanner() {
    if (!posMultiScanner || !posMultiScannerStarted) return;
    posMultiScanner.stop()
        .then(() => {
            posMultiScannerStarted = false;
            return posMultiScanner.clear();
        })
        .catch(() => {
            posMultiScannerStarted = false;
        });
}

document.addEventListener('DOMContentLoaded', function() {
    const multiScanModalEl = document.getElementById('multiScanModal');
    if (!multiScanModalEl) return;

    multiScanModalEl.addEventListener('shown.bs.modal', function() {
        setMultiScanMode('paste');
    });

    multiScanModalEl.addEventListener('hidden.bs.modal', function() {
        stopPosMultiScanner();
        setMultiScanMode('paste');
    });
});

searchInput.addEventListener('input', (e) => {
    clearTimeout(searchTimeout);
    const q = e.target.value.trim();
    if (q.length < 2) {
        grid.innerHTML = '';
        searchMsg.style.display = 'block';
        if (q.length === 0) searchMsg.textContent = 'Start typing to search products.';
        else searchMsg.textContent = 'Keep typing...';
        return;
    }
    
    searchTimeout = setTimeout(() => {
        searchProducts(q);
    }, 300);
});

async function searchProducts(q) {
    searchMsg.style.display = 'block';
    searchMsg.textContent = 'Searching...';
    grid.innerHTML = '';
    
    try {
        const res = await fetch('api/search_products.php?q=' + encodeURIComponent(q), {
            headers: { 'Accept': 'application/json' }
        });
        const data = await res.json();

        if (!res.ok) {
            throw new Error(data && data.message ? data.message : 'Search request failed');
        }

        if (!Array.isArray(data)) {
            throw new Error('Invalid response format');
        }
        
        if (data.length === 0) {
            searchMsg.textContent = 'No products found.';
            return;
        }
        
        searchMsg.style.display = 'none';
        
        data.forEach(p => {
            const isOutOfStock = p.quantity <= 0;
            const card = document.createElement('div');
            card.className = 'col-6 col-md-4 col-xl-3';
            card.innerHTML = `
                <div class="card h-100 product-card ${isOutOfStock ? 'opacity-50' : ''}" onclick="addToCart(${p.id}, '${p.name.replace(/'/g, "\\'")}', ${p.selling_price}, ${p.quantity})">
                    <div class="card-body p-2 text-center d-flex flex-column">
                        <div class="fw-bold text-truncate mb-1" style="font-size: 0.9rem;" title="${p.name}">${p.name}</div>
                        <div class="text-muted small mb-2">${p.barcode}</div>
                        <div class="mt-auto">
                            <span class="badge ${isOutOfStock ? 'bg-danger' : 'bg-success'} mb-1">${p.quantity} in stock</span>
                            <div class="fw-bold text-primary">${formatMoney(p.selling_price)}</div>
                        </div>
                    </div>
                </div>
            `;
            grid.appendChild(card);
        });
        
        // If there's exactly 1 exact barcode match, auto-add it and clear search (scanner behavior)
        if (data.length === 1 && data[0].barcode === q && data[0].quantity > 0) {
            addToCart(data[0].id, data[0].name, data[0].selling_price, data[0].quantity);
            searchInput.value = '';
            grid.innerHTML = '';
            searchMsg.style.display = 'block';
            searchMsg.textContent = 'Start typing to search products.';
        }
        
    } catch (e) {
        searchMsg.textContent = 'Error searching. ' + (e.message || 'Please try again.');
    }
}

function addToCart(id, name, price, maxQty) {
    if (maxQty <= 0) {
        alert('Out of stock!');
        return;
    }
    
    if (cart[id]) {
        if (cart[id].qty < maxQty) {
            cart[id].qty++;
        } else {
            alert('Cannot exceed available stock.');
        }
    } else {
        cart[id] = { item_type: 'product', name, price: parseFloat(price), qty: 1, maxQty, cost_price: 0 };
    }
    renderCart();
}

function addCustomItem() {
    const nameEl = document.getElementById('customName');
    const qtyEl = document.getElementById('customQty');
    const priceEl = document.getElementById('customPrice');
    const costEl = document.getElementById('customCost');

    const name = (nameEl.value || '').trim();
    const qty = parseInt(qtyEl.value || '0', 10);
    const price = parseFloat(priceEl.value || '0');
    const costPrice = parseFloat(costEl.value || '0');

    if (!name) {
        alert('Please enter custom item name.');
        return;
    }
    if (!Number.isFinite(qty) || qty < 1) {
        alert('Please enter a valid quantity.');
        return;
    }
    if (!Number.isFinite(price) || price < 0 || !Number.isFinite(costPrice) || costPrice < 0) {
        alert('Please enter valid prices.');
        return;
    }

    const id = customItemSeq;
    customItemSeq--;
    cart[id] = {
        item_type: 'custom',
        name,
        qty,
        price,
        cost_price: costPrice,
        maxQty: 99999
    };

    nameEl.value = '';
    qtyEl.value = '1';
    priceEl.value = '0.00';
    costEl.value = '0.00';

    const modalEl = document.getElementById('customItemModal');
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();

    renderCart();
}

async function processMultiScan() {
    const input = document.getElementById('multiScanInput');
    const raw = (input.value || '').trim();
    if (!raw) {
        alert('Please paste at least one barcode.');
        return;
    }

    const codes = raw
        .split(/[\s,]+/)
        .map(v => v.trim())
        .filter(Boolean);

    if (codes.length === 0) {
        alert('No valid barcodes found.');
        return;
    }

    const counts = {};
    for (const code of codes) {
        counts[code] = (counts[code] || 0) + 1;
    }

    try {
        const res = await fetch('api/multi_scan_products.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ codes: Object.keys(counts) })
        });
        const data = await res.json();

        if (!res.ok) {
            throw new Error(data && data.message ? data.message : 'Multi scan failed');
        }

        const found = data.found || [];
        const foundMap = {};
        found.forEach(item => {
            foundMap[item.barcode] = item;
        });

        let addedLines = 0;
        Object.keys(counts).forEach(code => {
            const product = foundMap[code];
            if (!product) {
                return;
            }
            const repeat = counts[code];
            for (let i = 0; i < repeat; i++) {
                addToCart(product.id, product.name, product.selling_price, product.quantity);
            }
            addedLines += repeat;
        });

        const missing = data.missing || [];
        const modalEl = document.getElementById('multiScanModal');
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) {
            modal.hide();
        }

        input.value = '';
        searchMsg.style.display = 'block';
        if (missing.length > 0) {
            searchMsg.textContent = 'Added ' + addedLines + ' scans. Missing: ' + missing.join(', ');
        } else {
            searchMsg.textContent = 'Added ' + addedLines + ' scans to cart.';
        }
    } catch (e) {
        alert('Multi scan error: ' + (e.message || 'Please try again.'));
    }
}

function updateCartQty(id, change) {
    if (!cart[id]) return;
    const newQty = cart[id].qty + change;
    
    if (newQty <= 0) {
        delete cart[id];
    } else if (newQty > cart[id].maxQty) {
        alert('Cannot exceed available stock.');
    } else {
        cart[id].qty = newQty;
    }
    renderCart();
}

function updateCartPrice(id, newPrice) {
    if (!cart[id]) return;
    const p = parseFloat(newPrice);
    if (!isNaN(p) && p >= 0) {
        cart[id].price = p;
    }
    renderCart(false); // don't redraw everything, just update total and data
}

function renderCart(remount = true) {
    const container = document.getElementById('cartContainer');
    const emptyMsg = document.getElementById('emptyCartMsg');
    const totalText = document.getElementById('cartTotalText');
    const inputData = document.getElementById('cartDataInput');
    const checkoutBtn = document.getElementById('checkoutBtn');
    
    const keys = Object.keys(cart);
    let total = 0;
    
    if (keys.length === 0) {
        emptyMsg.style.display = 'block';
        container.querySelectorAll('.cart-item').forEach(e => e.remove());
        totalText.textContent = formatMoney(0);
        checkoutBtn.disabled = true;
        inputData.value = '[]';
        return;
    }
    
    emptyMsg.style.display = 'none';
    
    if (remount) {
        container.querySelectorAll('.cart-item').forEach(e => e.remove());
        keys.forEach(k => {
            const item = cart[k];
            const div = document.createElement('div');
            div.className = 'cart-item';
            div.innerHTML = `
                <div class="flex-grow-1 me-2" style="min-width:0;">
                    <div class="fw-bold text-truncate" style="font-size:0.9rem;" title="${item.name}">${item.name} ${item.item_type === 'custom' ? '<span class="badge bg-secondary ms-1">Custom</span>' : ''}</div>
                    <div class="d-flex align-items-center mt-1">
                        <span class="text-muted small me-1">@</span>
                        <input type="number" class="form-control form-control-sm px-1 py-0 border-0 bg-light" style="width: 70px; height: 24px" step="0.01" value="${item.price.toFixed(2)}" onchange="updateCartPrice(${k}, this.value)">
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <div class="d-flex align-items-center bg-light rounded-pill px-1">
                        <button type="button" class="btn btn-sm btn-light qty-btn" onclick="updateCartQty(${k}, -1)"><i class="fas fa-minus fs-6"></i></button>
                        <span class="fw-bold mx-2" style="min-width: 20px; text-align:center;">${item.qty}</span>
                        <button type="button" class="btn btn-sm btn-light qty-btn" onclick="updateCartQty(${k}, 1)"><i class="fas fa-plus fs-6"></i></button>
                    </div>
                    <div class="fw-bold ms-2 text-end" style="width: 60px;">${formatMoney(item.price * item.qty)}</div>
                </div>
            `;
            container.appendChild(div);
        });
    }

    const payload = [];
    keys.forEach(k => {
        const item = cart[k];
        total += item.price * item.qty;
        payload.push({
            id: Number(k),
            item_type: item.item_type || 'product',
            name: item.name,
            qty: item.qty,
            price: item.price,
            cost_price: item.cost_price || 0
        });
    });
    
    totalText.textContent = formatMoney(total);
    inputData.value = JSON.stringify(payload);
    checkoutBtn.disabled = payload.length === 0;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>