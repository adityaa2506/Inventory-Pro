<?php

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function redirect_to(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function generate_barcode(): string
{
    return 'IS2' . date('ymdHis') . random_int(100, 999);
}

function format_money(float $amount): string
{
    return '₹' . number_format($amount, 2);
}

function format_indian_datetime(?string $value): string
{
    if (!$value) {
        return '-';
    }

    try {
        $dt = new DateTime($value);
        return $dt->format('d-m-Y h:i A');
    } catch (Throwable $th) {
        return $value;
    }
}

function format_indian_date(?string $value): string
{
    if (!$value) {
        return '-';
    }

    try {
        $dt = new DateTime($value);
        return $dt->format('d-m-Y');
    } catch (Throwable $th) {
        return $value;
    }
}

function upload_product_image(array $file): ?string
{
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return null;
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];

    $mime = mime_content_type($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        return null;
    }

    if (!is_dir(__DIR__ . '/../uploads')) {
        mkdir(__DIR__ . '/../uploads', 0775, true);
    }

    $filename = 'prod_' . uniqid('', true) . '.' . $allowed[$mime];
    $targetPath = __DIR__ . '/..uploads/' . $filename;

    // Load original image
    $image = null;
    switch ($mime) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($file['tmp_name']);
            break;
        case 'image/png':
            $image = imagecreatefrompng($file['tmp_name']);
            break;
        case 'image/webp':
            $image = imagecreatefromwebp($file['tmp_name']);
            break;
        default:
            return null;
    }

    if (!$image) {
        return null;
    }

    // Get original dimensions
    $origWidth = imagesx($image);
    $origHeight = imagesy($image);
    $maxWidth = 1000;
    $maxHeight = 1000;

    // Calculate new dimensions (maintain aspect ratio)
    $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight, 1);
    $newWidth = (int)($origWidth * $ratio);
    $newHeight = (int)($origHeight * $ratio);

    // Create resized image
    $resized = imagecreatetruecolor($newWidth, $newHeight);

    // Preserve transparency for PNG
    if ($mime === 'image/png') {
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
        imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);
    }

    // Resize image
    imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

    // Save compressed image
    switch ($mime) {
        case 'image/jpeg':
            imagejpeg($resized, $targetPath, 80); // 80% quality
            break;
        case 'image/png':
            imagepng($resized, $targetPath, 8); // Compression level 8
            break;
        case 'image/webp':
            imagewebp($resized, $targetPath, 80); // 80% quality
            break;
    }

    // Free memory
    imagedestroy($image);
    imagedestroy($resized);

    return $filename;
}

function action_badge_class(string $actionType): string
{
    return match ($actionType) {
        'SALE' => 'bg-danger',
        'SALE_EXHIBITION' => 'bg-warning text-dark',
        'SALE_CONTAINER' => 'bg-primary',
        'MANUAL_SALE' => 'bg-secondary',
        'ADD' => 'bg-success',
        default => 'bg-dark'
    };
}

function log_inventory(PDO $pdo, array $payload): void
{
    $sql = "INSERT INTO inventory_log
            (product_id, barcode, product_name, quantity_change, action_type, note, sale_price, cost_price, exhibition_id, sold_at)
            VALUES
            (:product_id, :barcode, :product_name, :quantity_change, :action_type, :note, :sale_price, :cost_price, :exhibition_id, NOW())";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':product_id' => $payload['product_id'] ?? null,
        ':barcode' => $payload['barcode'] ?? null,
        ':product_name' => $payload['product_name'],
        ':quantity_change' => $payload['quantity_change'],
        ':action_type' => $payload['action_type'],
        ':note' => $payload['note'] ?? null,
        ':sale_price' => $payload['sale_price'] ?? null,
        ':cost_price' => $payload['cost_price'] ?? null,
        ':exhibition_id' => $payload['exhibition_id'] ?? null,
    ]);
}

function per_unit_price_from_total(float $totalPrice, int $quantity): float
{
    $quantity = max(1, $quantity);
    return round($totalPrice / $quantity, 2);
}
