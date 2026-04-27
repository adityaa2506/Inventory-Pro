<?php
require_once __DIR__ . '/includes/config.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'admin/dashboard.php');
    exit;
}

header('Location: ' . BASE_URL . 'login.php');
exit;
