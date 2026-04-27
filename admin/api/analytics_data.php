<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();

header('Content-Type: application/json');

$range = $_GET['range'] ?? '7';
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';

if ($range === 'custom' && $from !== '' && $to !== '') {
    $startDate = $from . ' 00:00:00';
    $endDate = $to . ' 23:59:59';
} else {
    $days = $range === '30' ? 30 : 7;
    $startDate = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));
    $endDate = date('Y-m-d 23:59:59');
}

$params = [':start' => $startDate, ':end' => $endDate];

$revenueStmt = $pdo->prepare("SELECT COALESCE(SUM(sale_price * ABS(quantity_change)),0)
                              FROM inventory_log
                              WHERE quantity_change < 0
                              AND action_type IN ('SALE','SALE_CONTAINER','MANUAL_SALE')
                              AND sold_at BETWEEN :start AND :end");
$revenueStmt->execute($params);
$revenue = (float)$revenueStmt->fetchColumn();

$profitStmt = $pdo->prepare("SELECT COALESCE(SUM(
                            GREATEST(0, (COALESCE(il.sale_price,0) - COALESCE(CASE WHEN il.product_id IS NOT NULL THEN p.cost_price ELSE il.cost_price END,0)))
                            * ABS(il.quantity_change)
                        ),0)
                        FROM inventory_log il
                        LEFT JOIN products p ON p.id = il.product_id
                        WHERE il.quantity_change < 0
                        AND il.action_type IN ('SALE','SALE_CONTAINER','MANUAL_SALE')
                        AND il.sold_at BETWEEN :start AND :end");
$profitStmt->execute($params);
$profit = (float)$profitStmt->fetchColumn();

$topStmt = $pdo->prepare("SELECT product_name, SUM(ABS(quantity_change)) AS qty,
                        SUM(sale_price * ABS(quantity_change)) AS amount
                        FROM inventory_log
                        WHERE quantity_change < 0
                        AND action_type IN ('SALE','SALE_CONTAINER','MANUAL_SALE')
                        AND sold_at BETWEEN :start AND :end
                        GROUP BY product_name
                        ORDER BY qty DESC
                        LIMIT 5");
$topStmt->execute($params);
$topProducts = $topStmt->fetchAll();

$exStmt = $pdo->prepare("SELECT e.name, SUM(sale_price * ABS(il.quantity_change)) AS revenue
                        FROM inventory_log il
                        INNER JOIN exhibitions e ON e.id = il.exhibition_id
                        WHERE il.quantity_change < 0
                        AND il.sold_at BETWEEN :start AND :end
                        GROUP BY e.id
                        ORDER BY revenue DESC");
$exStmt->execute($params);
$exhibitions = $exStmt->fetchAll();

$deadStock = $pdo->query("SELECT p.name, p.quantity
                          FROM products p
                          LEFT JOIN (
                                SELECT product_id, MAX(sold_at) AS last_sold
                                FROM inventory_log
                                WHERE quantity_change < 0 AND product_id IS NOT NULL
                                GROUP BY product_id
                          ) s ON s.product_id = p.id
                          WHERE (s.last_sold IS NULL OR s.last_sold < DATE_SUB(NOW(), INTERVAL 30 DAY))
                          AND p.quantity > 0
                          ORDER BY p.quantity DESC
                          LIMIT 8")->fetchAll();

$restock = $pdo->query("SELECT p.name, p.quantity,
                        COALESCE(SUM(CASE WHEN il.quantity_change < 0 AND il.sold_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN ABS(il.quantity_change) ELSE 0 END),0) AS sold_30
                        FROM products p
                        LEFT JOIN inventory_log il ON il.product_id = p.id
                        GROUP BY p.id
                        HAVING sold_30 >= 5 AND p.quantity <= 5
                        ORDER BY sold_30 DESC
                        LIMIT 8")->fetchAll();

$seriesStmt = $pdo->prepare("SELECT DATE(sold_at) AS d,
                        SUM(sale_price * ABS(quantity_change)) AS revenue,
                        SUM(
                            GREATEST(0, (COALESCE(il.sale_price,0) - COALESCE(CASE WHEN il.product_id IS NOT NULL THEN p.cost_price ELSE il.cost_price END,0)))
                            * ABS(il.quantity_change)
                        ) AS profit
                        FROM inventory_log il
                        LEFT JOIN products p ON p.id = il.product_id
                        WHERE il.quantity_change < 0
                        AND il.action_type IN ('SALE','SALE_CONTAINER','MANUAL_SALE')
                        AND il.sold_at BETWEEN :start AND :end
                        GROUP BY DATE(sold_at)
                        ORDER BY DATE(sold_at)");
$seriesStmt->execute($params);
$series = $seriesStmt->fetchAll();

echo json_encode([
    'range' => ['start' => $startDate, 'end' => $endDate],
    'revenue' => $revenue,
    'profit' => $profit,
    'topProducts' => $topProducts,
    'exhibitions' => $exhibitions,
    'deadStock' => $deadStock,
    'restock' => $restock,
    'series' => $series,
]);
