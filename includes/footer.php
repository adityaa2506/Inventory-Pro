</div>
<nav class="d-flex bottom-nav">
    <?php if (is_admin()): ?>
    <a href="<?= BASE_URL ?>admin/dashboard.php" class="<?= $activePage === 'dashboard' ? 'active' : '' ?>">
        <i class="fa-solid fa-chart-line"></i>Dashboard
    </a>
    <?php endif; ?>
    <a href="<?= BASE_URL ?>admin/pos.php" class="<?= $activePage === 'pos' ? 'active' : '' ?>">
        <i class="fa-solid fa-cash-register"></i>POS
    </a>
    <a href="<?= BASE_URL ?>admin/products.php" class="<?= $activePage === 'products' ? 'active' : '' ?>">
        <i class="fa-solid fa-boxes-stacked"></i>Products
    </a>
    <a href="<?= BASE_URL ?>admin/scan.php" class="<?= $activePage === 'scan' ? 'active' : '' ?>">
        <i class="fa-solid fa-barcode"></i>Scan
    </a>
    <a href="<?= BASE_URL ?>admin/product_add.php" class="<?= $activePage === 'add_product' ? 'active' : '' ?>">
        <i class="fa-solid fa-plus"></i>Add Product
    </a>
</nav>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
