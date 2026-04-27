<?php
require_once __DIR__ . '/includes/config.php';

if (!empty($_GET['run']) && $_GET['run'] === '1') {
    $email = 'admin@inventory.local';
    $password = password_hash('admin123', PASSWORD_DEFAULT);

    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $exists = $stmt->fetchColumn();

    if (!$exists) {
        $insert = $pdo->prepare('INSERT INTO users (email, password) VALUES (:email, :password)');
        $insert->execute([':email' => $email, ':password' => $password]);
        $message = 'Admin created: admin@inventory.local / admin123';
    } else {
        $message = 'Admin user already exists.';
    }
} else {
    $message = 'Add ?run=1 to the URL to create default admin account.';
}

echo '<h3>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</h3>';
