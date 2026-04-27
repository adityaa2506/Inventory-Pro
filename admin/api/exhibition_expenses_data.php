<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$exId = (int)($_GET['exhibition_id'] ?? 0);

if ($action === 'list') {
    $stmt = $pdo->prepare("SELECT * FROM exhibition_expenses WHERE exhibition_id = ? ORDER BY expense_date DESC");
    $stmt->execute([$exId]);
    echo json_encode($stmt->fetchAll());
} 

elseif ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $category = $_POST['category'] ?? 'OTHER';
    $date = $_POST['expense_date'] ?? date('Y-m-d');

    if ($exId > 0 && !empty($title)) {
        $stmt = $pdo->prepare("INSERT INTO exhibition_expenses (exhibition_id, title, amount, category, expense_date) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$exId, $title, $amount, $category, $date]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Missing data']);
    }
}

elseif ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM exhibition_expenses WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
}
