<?php
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$query = trim($_GET['q'] ?? '');
if ($query === '') {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $pdo->prepare('
        SELECT id, name, barcode, selling_price, cost_price, quantity, image
        FROM products
        WHERE name LIKE :q_name OR barcode LIKE :q_barcode
        ORDER BY name ASC
        LIMIT 20
    ');
    $like = '%' . $query . '%';
    $stmt->execute([
        ':q_name' => $like,
        ':q_barcode' => $like,
    ]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($results);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Search failed', 'message' => $e->getMessage()]);
}
