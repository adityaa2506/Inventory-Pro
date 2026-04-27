<?php
// Enable error reporting to find what is causing the 500 error
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/config.php';

try {
    echo "Connection check: Attempting to connect...<br>";
    if (!$pdo) {
        die("PDO connection failed inside config.php.");
    }
    echo "Connected successfully to: " . $dbName . "<br>";
    // 1. Exhibition Expenses Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS exhibition_expenses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        exhibition_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        category ENUM('RENT', 'TRAVEL', 'STAFF', 'MARKETING', 'OTHER') DEFAULT 'OTHER',
        expense_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_exp_exhibition FOREIGN KEY (exhibition_id) REFERENCES exhibitions(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 2. Exhibition Packing List (Allocated stock for events)
    $pdo->exec("CREATE TABLE IF NOT EXISTS exhibition_packing (
        id INT AUTO_INCREMENT PRIMARY KEY,
        exhibition_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity_sent INT NOT NULL DEFAULT 0,
        quantity_returned INT NOT NULL DEFAULT 0,
        status ENUM('PENDING', 'SENT', 'RETURNED') DEFAULT 'PENDING',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_pack_exhibition FOREIGN KEY (exhibition_id) REFERENCES exhibitions(id) ON DELETE CASCADE,
        CONSTRAINT fk_pack_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    echo "Migration successful - Tables created if they didn't exist.\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
