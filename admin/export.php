<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$type = trim($_GET['type'] ?? '');

if ($type === 'inventory_log') {
    exportInventoryLog();
} elseif ($type === 'products') {
    exportProducts();
} else {
    http_response_code(400);
    die('Invalid export type.');
}

function exportInventoryLog() {
    global $pdo;
    
    $q = trim($_GET['q'] ?? '');
    $fromDate = trim($_GET['from_date'] ?? '');
    $toDate = trim($_GET['to_date'] ?? '');
    $actionType = trim($_GET['action_type'] ?? '');
    $exhibitionFilter = trim((string)($_GET['exhibition_id'] ?? ''));

    $sql = 'SELECT il.id, il.product_id, il.barcode, il.product_name, il.quantity_change, 
                   il.action_type, il.note, il.sale_price, il.cost_price, il.sold_at, 
                   e.name AS exhibition_name
            FROM inventory_log il
            LEFT JOIN exhibitions e ON e.id = il.exhibition_id';
    
    $params = [];
    $conditions = [];

    if ($q !== '') {
        $conditions[] = '(il.product_name LIKE :q_product OR il.barcode LIKE :q_barcode)';
        $like = '%' . $q . '%';
        $params[':q_product'] = $like;
        $params[':q_barcode'] = $like;
    }

    if ($fromDate !== '') {
        $fromDateObj = DateTime::createFromFormat('Y-m-d', $fromDate);
        if ($fromDateObj !== false) {
            $conditions[] = 'il.sold_at >= :from_date';
            $params[':from_date'] = $fromDate . ' 00:00:00';
        }
    }

    if ($toDate !== '') {
        $toDateObj = DateTime::createFromFormat('Y-m-d', $toDate);
        if ($toDateObj !== false) {
            $conditions[] = 'il.sold_at <= :to_date';
            $params[':to_date'] = $toDate . ' 23:59:59';
        }
    }

    if ($actionType !== '') {
        if ($actionType === 'SALE_EXHIBITION') {
            $conditions[] = "il.action_type = 'SALE' AND il.exhibition_id IS NOT NULL";
            if ($exhibitionFilter !== '' && ctype_digit($exhibitionFilter)) {
                $conditions[] = 'il.exhibition_id = :exhibition_id';
                $params[':exhibition_id'] = (int)$exhibitionFilter;
            }
        } else {
            $conditions[] = 'il.action_type = :action_type';
            $params[':action_type'] = $actionType;
        }
    }

    if (!empty($conditions)) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $sql .= ' ORDER BY il.id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    // Generate CSV
    $filename = 'inventory_log_' . date('Y-m-d_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    
    // BOM for UTF-8 (helps Excel recognize the encoding)
    fwrite($output, "\xEF\xBB\xBF");

    // Header row
    fputcsv($output, [
        'ID',
        'Product Name',
        'Barcode',
        'Quantity Change',
        'Action Type',
        'Sale Price',
        'Cost Price',
        'Revenue',
        'Profit',
        'Date',
        'Exhibition',
        'Note'
    ]);

    // Data rows
    foreach ($logs as $log) {
        $revenue = (float)($log['sale_price'] ?? 0) * abs((int)$log['quantity_change']);
        $profit = $revenue - ((float)($log['cost_price'] ?? 0) * abs((int)$log['quantity_change']));
        
        fputcsv($output, [
            $log['id'],
            $log['product_name'],
            $log['barcode'],
            $log['quantity_change'],
            $log['action_type'],
            format_money((float)$log['sale_price']),
            format_money((float)$log['cost_price']),
            format_money($revenue),
            format_money($profit),
            format_indian_datetime((string)$log['sold_at']),
            $log['exhibition_name'] ?? '',
            $log['note'] ?? ''
        ]);
    }

    fclose($output);
    exit;
}

function exportProducts() {
    global $pdo;

    $search = trim($_GET['search'] ?? '');

    $sql = 'SELECT p.id, p.name, p.category, p.barcode, p.cost_price, p.selling_price, 
                   p.quantity, p.description, p.created_at
            FROM products p';
    
    $params = [];

    if ($search !== '') {
        $sql .= ' WHERE p.name LIKE :search OR p.category LIKE :search OR p.barcode LIKE :search';
        $params[':search'] = '%' . $search . '%';
    }

    $sql .= ' ORDER BY p.name ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    // Generate CSV
    $filename = 'inventory_' . date('Y-m-d_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    
    // BOM for UTF-8
    fwrite($output, "\xEF\xBB\xBF");

    // Header row
    fputcsv($output, [
        'ID',
        'Product Name',
        'Category',
        'Barcode',
        'Cost Price',
        'Selling Price',
        'Quantity',
        'Stock Value (Cost)',
        'Stock Value (Selling)',
        'Profit Margin %',
        'Created Date'
    ]);

    // Data rows
    foreach ($products as $product) {
        $costPrice = (float)$product['cost_price'];
        $sellingPrice = (float)$product['selling_price'];
        $quantity = (int)$product['quantity'];
        $stockCostValue = $costPrice * $quantity;
        $stockSellingValue = $sellingPrice * $quantity;
        $profitMargin = $costPrice > 0 ? (($sellingPrice - $costPrice) / $costPrice) * 100 : 0;

        fputcsv($output, [
            $product['id'],
            $product['name'],
            $product['category'],
            $product['barcode'],
            format_money($costPrice),
            format_money($sellingPrice),
            $quantity,
            format_money($stockCostValue),
            format_money($stockSellingValue),
            round($profitMargin, 2) . '%',
            format_indian_datetime((string)$product['created_at'])
        ]);
    }

    fclose($output);
    exit;
}
?>
