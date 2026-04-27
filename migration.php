<?php
require __DIR__ . '/includes/config.php';
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN role VARCHAR(30) DEFAULT 'admin' AFTER password");
    echo "Column 'role' added.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
