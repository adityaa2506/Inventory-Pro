<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        redirect_to(BASE_URL . 'login.php');
    }
}

function require_admin(): void
{
    require_login();
    if (($_SESSION['user_role'] ?? 'staff') !== 'admin') {
        echo "<div style='padding:20px; font-family:sans-serif; text-align:center;'>
                <h2>Access Denied</h2>
                <p>You do not have permission to view this page. Admin access is required.</p>
                <a href='" . BASE_URL . "admin/dashboard.php'>Return to Dashboard</a>
              </div>";
        exit;
    }
}

function is_admin(): bool
{
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function attempt_login(PDO $pdo, string $email, string $password): bool
{
    $stmt = $pdo->prepare('SELECT id, password, role FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        return false;
    }

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_email'] = $email;
    $_SESSION['user_role'] = $user['role'] ?? 'admin';
    return true;
}
