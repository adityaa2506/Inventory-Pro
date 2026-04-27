<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();
header('Content-Type: application/json');

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$exId = (int)($_GET['exhibition_id'] ?? ($_POST['exhibition_id'] ?? 0));

if ($action === 'list' && $exId > 0) {
    // Get all items in packing list for this exhibition, joining with inventory_log to see sold count
    $sql = "SELECT ep.*, p.name AS product_name, p.barcode, p.quantity AS current_stock,
            (SELECT COALESCE(SUM(ABS(il.quantity_change)), 0) 
             FROM inventory_log il 
             WHERE il.product_id = ep.product_id 
             AND il.exhibition_id = ep.exhibition_id
             AND il.quantity_change < 0
             AND il.action_type IN ('SALE', 'MANUAL_SALE')
            ) AS quantity_sold
            FROM exhibition_packing ep
            JOIN products p ON p.id = ep.product_id
            WHERE ep.exhibition_id = ?
            ORDER BY p.name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$exId]);
    echo json_encode($stmt->fetchAll());
} 

elseif ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pId = (int)($_POST['product_id'] ?? 0);
    $qSent = (int)($_POST['quantity_sent'] ?? 0);

    if ($exId > 0 && $pId > 0 && $qSent > 0) {
        // Check if item already in exhibition stall list
        $check = $pdo->prepare("SELECT id FROM exhibition_packing WHERE exhibition_id = ? AND product_id = ?");
        $check->execute([$exId, $pId]);
        $existing = $check->fetch();

        if ($existing) {
            $stmt = $pdo->prepare("UPDATE exhibition_packing SET quantity_sent = quantity_sent + ? WHERE id = ?");
            $stmt->execute([$qSent, $existing['id']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO exhibition_packing (exhibition_id, product_id, quantity_sent) VALUES (?, ?, ?)");
            $stmt->execute([$exId, $pId, $qSent]);
        }
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid data provided.']);
    }
}

elseif ($action === 'update_returned' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $qRet = (int)($_POST['quantity_returned'] ?? 0);
    $stmt = $pdo->prepare("UPDATE exhibition_packing SET quantity_returned = ? WHERE id = ?");
    $stmt->execute([$qRet, $id]);
    echo json_encode(['success' => true]);
}

elseif ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM exhibition_packing WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
}
