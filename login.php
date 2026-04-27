<?php
require_once __DIR__ . '/includes/auth.php';

if (!empty($_SESSION['user_id'])) {
    redirect_to(BASE_URL . 'admin/dashboard.php');
}

$error = '';

if (is_post()) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Email and password are required.';
    } elseif (!attempt_login($pdo, $email, $password)) {
        $error = 'Invalid login credentials.';
    } else {
        redirect_to(BASE_URL . 'admin/dashboard.php');
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Inventory Pro V2</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <script>
        (function() {
            const savedTheme = localStorage.getItem('ipv2_theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
    <style>
        :root {
            --ipv2-primary: #1e40af;
            --ipv2-primary-dark: #1e3a8a;
            --ipv2-accent: #f97316;
            --ipv2-login-bg: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #3b82f6 100%);
            --ipv2-surface: #ffffff;
            --ipv2-border: #e2e8f0;
            --ipv2-text: #1f2937;
            --ipv2-muted: #6b7280;
            --ipv2-footer-border: #e5e7eb;
        }
        :root[data-theme='dark'] {
            --ipv2-login-bg: linear-gradient(135deg, #0b1220 0%, #111827 45%, #0f172a 100%);
            --ipv2-surface: #111827;
            --ipv2-border: #334155;
            --ipv2-text: #e2e8f0;
            --ipv2-muted: #94a3b8;
            --ipv2-footer-border: #334155;
        }
        body {
            background: var(--ipv2-login-bg);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Roboto", sans-serif;
        }
        * {
            transition: all 0.2s ease;
        }
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            background: var(--ipv2-surface);
            overflow: hidden;
        }
        .login-header {
            background: linear-gradient(135deg, var(--ipv2-primary), var(--ipv2-primary-dark));
            color: #fff;
            padding: 3rem 2rem;
            text-align: center;
        }
        .login-logo {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }
        .login-logo i {
            font-size: 2.8rem;
        }
        .login-subtitle {
            font-size: 0.95rem;
            opacity: 0.9;
            margin-bottom: 0;
        }
        .login-body {
            padding: 2.5rem;
            color: var(--ipv2-text);
        }
        .form-control {
            border: 2px solid var(--ipv2-border);
            border-radius: 12px;
            padding: 0.75rem 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            background: var(--ipv2-surface);
            color: var(--ipv2-text);
        }
        .form-control:focus {
            border-color: var(--ipv2-primary);
            box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
        }
        .form-control::placeholder {
            color: var(--ipv2-muted);
            opacity: 1;
        }
        .form-label {
            font-weight: 600;
            color: var(--ipv2-text);
            margin-bottom: 0.6rem;
        }
        .btn-is2 {
            background: linear-gradient(135deg, var(--ipv2-primary), var(--ipv2-primary-dark));
            border: none;
            color: #fff;
            font-weight: 700;
            padding: 0.9rem 1.5rem;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(30, 64, 175, 0.3);
        }
        .btn-is2:hover {
            background: linear-gradient(135deg, var(--ipv2-primary-dark), #1e3a8a);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(30, 64, 175, 0.4);
        }
        .btn-is2:active {
            transform: translateY(0);
        }
        .alert {
            border-radius: 12px;
            border: none;
        }
        .footer-text {
            font-size: 0.85rem;
            color: var(--ipv2-muted);
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--ipv2-footer-border);
        }
        .text-muted,
        small.text-muted {
            color: var(--ipv2-muted) !important;
        }
        .theme-fab {
            position: fixed;
            top: 14px;
            right: 14px;
            z-index: 1055;
            border-radius: 999px;
            min-height: 36px;
            padding: 0.35rem 0.8rem;
            font-weight: 700;
            box-shadow: 0 8px 18px rgba(0,0,0,0.22);
        }
    </style>
</head>
<body class="login-container">
    <button type="button" id="loginThemeToggleBtn" class="btn btn-sm btn-light theme-fab" onclick="toggleLoginTheme()">
        <i class="fas fa-moon me-1"></i><span id="loginThemeToggleText">Dark</span>
    </button>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-md-4">
                <div class="card login-card">
                    <div class="login-header">
                        <div class="login-logo">
                            <i class="fas fa-cube"></i>
                            <span>Inventory Pro <span style="color: var(--ipv2-accent); font-style: italic;">V2</span></span>
                        </div>
                        <p class="login-subtitle">Professional Inventory Management</p>
                    </div>
                    <div class="login-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i><?= e($error) ?>
                            </div>
                        <?php endif; ?>
                        <form method="post">
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-envelope me-2"></i>Email Address</label>
                                <input type="email" name="email" class="form-control" placeholder="admin@example.com" required autofocus>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-lock me-2"></i>Password</label>
                                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                            </div>
                            <button type="submit" class="btn btn-is2 w-100">
                                <i class="fas fa-sign-in-alt me-2"></i>Login
                            </button>
                        </form>
                        <div class="footer-text">
                            <small><i class="fas fa-info-circle me-1"></i>Run setup once: /setup_admin.php</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<script>
function updateLoginThemeUi() {
    const current = document.documentElement.getAttribute('data-theme') || 'light';
    const btn = document.getElementById('loginThemeToggleBtn');
    if (!btn) return;

    if (current === 'dark') {
        btn.classList.remove('btn-light');
        btn.classList.add('btn-outline-light');
        btn.innerHTML = '<i class="fas fa-sun me-1"></i><span id="loginThemeToggleText">Light</span>';
    } else {
        btn.classList.remove('btn-outline-light');
        btn.classList.add('btn-light');
        btn.innerHTML = '<i class="fas fa-moon me-1"></i><span id="loginThemeToggleText">Dark</span>';
    }
}

function toggleLoginTheme() {
    const current = document.documentElement.getAttribute('data-theme') || 'light';
    const next = current === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('ipv2_theme', next);
    updateLoginThemeUi();
}

updateLoginThemeUi();
</script>
</body>
</html>
