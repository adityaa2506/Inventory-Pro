<?php
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '{}', true);
$codes = $payload['codes'] ?? [];

if (!is_array($codes)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request', 'message' => 'codes must be an array']);
    exit;
}

$codes = array_values(array_unique(array_filter(array_map(static function ($v) {
    return trim((string)$v);
}, $codes), static function ($v) {
    return $v !== '';
})));

if (count($codes) === 0) {
    echo json_encode(['found' => [], 'missing' => []]);
    exit;
}

try {
    $placeholders = implode(',', array_fill(0, count($codes), '?'));
    $sql = "SELECT id, name, barcode, selling_price, cost_price, quantity, image FROM products WHERE barcode IN ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($codes);
    $found = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $foundCodes = [];
    foreach ($found as $item) {
        $foundCodes[] = (string)$item['barcode'];
    }

    $missing = [];
    foreach ($codes as $code) {
        if (!in_array($code, $foundCodes, true)) {
            $missing[] = $code;
        }
    }

    echo json_encode([
        'found' => $found,
        'missing' => $missing,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Multi scan failed', 'message' => $e->getMessage()]);
}
