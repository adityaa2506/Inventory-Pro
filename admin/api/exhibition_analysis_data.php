<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();

header('Content-Type: application/json');

$range = $_GET['range'] ?? '30';
$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');
$exhibitionId = (int)($_GET['exhibition_id'] ?? 0);
$txPage = max(1, (int)($_GET['tx_page'] ?? 1));
$txSearch = trim($_GET['tx_search'] ?? '');
$txLimit = 10;
$txOffset = ($txPage - 1) * $txLimit;

if ($range === 'custom' && $from !== '' && $to !== '') {
    $startDate = $from . ' 00:00:00';
    $endDate = $to . ' 23:59:59';
} else {
    $days = $range === '7' ? 7 : 30;
    $startDate = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));
    $endDate = date('Y-m-d 23:59:59');
}

$filters = ' WHERE il.quantity_change < 0
             AND il.exhibition_id IS NOT NULL
             AND il.action_type IN (\'SALE\', \'MANUAL_SALE\')
             AND il.sold_at BETWEEN :start AND :end';
$params = [':start' => $startDate, ':end' => $endDate];

if ($exhibitionId > 0) {
    $filters .= ' AND il.exhibition_id = :exhibition_id';
    $params[':exhibition_id'] = $exhibitionId;
}

$summaryStmt = $pdo->prepare("SELECT
    COALESCE(SUM(il.sale_price * ABS(il.quantity_change)), 0) AS revenue,
    COALESCE(SUM(
        GREATEST(
            0,
            (COALESCE(il.sale_price,0) - COALESCE(CASE WHEN il.product_id IS NOT NULL THEN p.cost_price ELSE il.cost_price END,0))
        ) * ABS(il.quantity_change)
    ), 0) AS profit,
    COALESCE(SUM(ABS(il.quantity_change)), 0) AS units_sold,
    COALESCE(COUNT(*), 0) AS transaction_count
    FROM inventory_log il
    LEFT JOIN products p ON p.id = il.product_id
" . $filters);
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch() ?: ['revenue' => 0, 'profit' => 0, 'units_sold' => 0, 'transaction_count' => 0];

$avgTicket = ((float)$summary['transaction_count'] > 0)
    ? ((float)$summary['revenue'] / (float)$summary['transaction_count'])
    : 0;

$byExhibitionStmt = $pdo->prepare("SELECT
    e.id,
    e.name,
    COALESCE(SUM(il.sale_price * ABS(il.quantity_change)), 0) AS revenue,
    COALESCE(SUM(
        GREATEST(
            0,
            (COALESCE(il.sale_price,0) - COALESCE(CASE WHEN il.product_id IS NOT NULL THEN p.cost_price ELSE il.cost_price END,0))
        ) * ABS(il.quantity_change)
    ), 0) AS profit,
    COALESCE(SUM(ABS(il.quantity_change)), 0) AS units_sold,
    COUNT(*) AS transactions
    FROM inventory_log il
    INNER JOIN exhibitions e ON e.id = il.exhibition_id
    LEFT JOIN products p ON p.id = il.product_id
" . $filters . "
    GROUP BY e.id
    ORDER BY revenue DESC");
$byExhibitionStmt->execute($params);
$byExhibition = $byExhibitionStmt->fetchAll();

$topProductsStmt = $pdo->prepare("SELECT
    il.product_name,
    COALESCE(SUM(ABS(il.quantity_change)), 0) AS qty,
    COALESCE(SUM(il.sale_price * ABS(il.quantity_change)), 0) AS revenue
    FROM inventory_log il
" . $filters . "
    GROUP BY il.product_name
    ORDER BY qty DESC
    LIMIT 10");
$topProductsStmt->execute($params);
$topProducts = $topProductsStmt->fetchAll();

$trendStmt = $pdo->prepare("SELECT
    DATE(il.sold_at) AS d,
    COALESCE(SUM(il.sale_price * ABS(il.quantity_change)), 0) AS revenue,
    COALESCE(SUM(
        GREATEST(
            0,
            (COALESCE(il.sale_price,0) - COALESCE(CASE WHEN il.product_id IS NOT NULL THEN p.cost_price ELSE il.cost_price END,0))
        ) * ABS(il.quantity_change)
    ), 0) AS profit
    FROM inventory_log il
    LEFT JOIN products p ON p.id = il.product_id
" . $filters . "
    GROUP BY DATE(il.sold_at)
    ORDER BY DATE(il.sold_at)");
$trendStmt->execute($params);
$trend = $trendStmt->fetchAll();

$txFilters = $filters;
$txParams = $params;

if ($txSearch !== '') {
    $txFilters .= ' AND il.product_name LIKE :tx_search';
    $txParams[':tx_search'] = '%' . $txSearch . '%';
}

$txCountStmt = $pdo->prepare("SELECT COUNT(*)
    FROM inventory_log il
" . $txFilters);
$txCountStmt->execute($txParams);
$txTotal = (int)$txCountStmt->fetchColumn();
$txTotalPages = max(1, (int)ceil($txTotal / $txLimit));
if ($txPage > $txTotalPages) {
    $txPage = $txTotalPages;
}
$txOffset = ($txPage - 1) * $txLimit;

$txSql = "SELECT
    il.id,
    il.sold_at,
    il.product_name,
    il.action_type,
    il.quantity_change,
    il.sale_price,
    il.cost_price,
    il.note,
    e.name AS exhibition_name
    FROM inventory_log il
    INNER JOIN exhibitions e ON e.id = il.exhibition_id
" . $txFilters . "
    ORDER BY il.id DESC
    LIMIT :tx_limit OFFSET :tx_offset";

$txParams[':tx_limit'] = $txLimit;
$txParams[':tx_offset'] = $txOffset;

$txStmt = $pdo->prepare($txSql);
foreach ($txParams as $key => $val) {
    if ($key === ':tx_limit' || $key === ':tx_offset' || $key === ':exhibition_id') {
        $txStmt->bindValue($key, (int)$val, PDO::PARAM_INT);
    } else {
        $txStmt->bindValue($key, $val, PDO::PARAM_STR);
    }
}
$txStmt->execute();
$transactions = $txStmt->fetchAll();

$exhibitions = $pdo->query('SELECT id, name FROM exhibitions ORDER BY name ASC')->fetchAll();

echo json_encode([
    'range' => ['start' => $startDate, 'end' => $endDate],
    'exhibitions' => $exhibitions,
    'selectedExhibitionId' => $exhibitionId,
    'summary' => [
        'revenue' => (float)$summary['revenue'],
        'profit' => (float)$summary['profit'],
        'units_sold' => (int)$summary['units_sold'],
        'transaction_count' => (int)$summary['transaction_count'],
        'avg_ticket' => $avgTicket,
    ],
    'byExhibition' => $byExhibition,
    'topProducts' => $topProducts,
    'trend' => $trend,
    'transactions' => $transactions,
    'transactionsPagination' => [
        'page' => $txPage,
        'limit' => $txLimit,
        'total' => $txTotal,
        'total_pages' => $txTotalPages,
    ],
]);
