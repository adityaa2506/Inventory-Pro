<?php
session_start();

date_default_timezone_set('Asia/Kolkata');

if (isset($_SERVER['HTTP_HOST']) && ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1')) {
    // Localhost Settings
    $dbHost = '127.0.0.1';
    $dbName = 'inventory_system_2';
    $dbUser = 'root';
    $dbPass = '';
} else if (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'inventoryprov2.xo.je') !== false) {
    // Production (InfinityFree) Settings
    $dbHost = 'sql107.infinityfree.com';
    $dbName = 'if0_41433879_inventory_system_2';
    $dbUser = 'if0_41433879';
    $dbPass = 'hbdgwlto66kPBD';
} else if (isset($_SERVER['HTTP_HOST'])) {
    // Other servers (still InfinityFree config usually)
    $dbHost = 'sql107.infinityfree.com';
    $dbName = 'if0_41433879_inventory_system_2';
    $dbUser = 'if0_41433879';
    $dbPass = 'hbdgwlto66kPBD';
} else {
    // CLI Environment Defaults
    $dbHost = '127.0.0.1';
    $dbName = 'inventory_system_2';
    $dbUser = 'root';
    $dbPass = '';
}

// Base URL helper
if (isset($_SERVER['DOCUMENT_ROOT'])) {
    $scriptAbsPath = str_replace('\\', '/', __FILE__); // Absolute path to config.php
    $docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
    $relPath = str_replace($docRoot, '', $scriptAbsPath); // e.g. /includes/config.php
    $baseDir = str_replace('includes/config.php', '', $relPath); // e.g. /
    $baseDir = '/' . trim($baseDir, '/') . '/'; 
    if ($baseDir === '//') $baseDir = '/';
} else {
    $baseDir = '/IV AI/'; // Guess for CLI
}

define('BASE_URL', $baseDir);



try {
    $pdo = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    exit('Database connection failed: ' . $e->getMessage());
}
