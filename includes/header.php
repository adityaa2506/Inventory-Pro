<?php
if (!isset($pageTitle)) {
    $pageTitle = 'Dashboard';
}
if (!isset($activePage)) {
    $activePage = '';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> - Inventory Pro V2</title>
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
            --ipv2-primary-light: #3b82f6;
            --ipv2-accent: #f97316;
            --ipv2-success: #10b981;
            --ipv2-danger: #ef4444;
            --ipv2-warning: #f59e0b;
            --ipv2-soft-bg: #f8fafc;
            --ipv2-ink: #0f172a;
            --ipv2-text-muted: #64748b;
            --ipv2-page-bg: linear-gradient(135deg, #f8fafc 0%, #e0e7ff 50%, #f0f9ff 100%);
            --ipv2-surface: #ffffff;
            --ipv2-border: #e2e8f0;
            --ipv2-nav-bg: #ffffff;
            --bs-primary: #1e40af;
            --bs-success: #10b981;
            --bs-warning: #f59e0b;
            --bs-danger: #ef4444;
        }
        :root[data-theme='dark'] {
            --ipv2-soft-bg: #0f172a;
            --ipv2-ink: #e2e8f0;
            --ipv2-text-muted: #94a3b8;
            --ipv2-page-bg: linear-gradient(135deg, #0b1220 0%, #111827 45%, #0f172a 100%);
            --ipv2-surface: #111827;
            --ipv2-border: #334155;
            --ipv2-nav-bg: #111827;
            --bs-body-bg: #0f172a;
            --bs-body-color: #e2e8f0;
            --bs-border-color: #334155;
        }
        * {
            transition: all 0.2s ease;
        }
        body {
            background: var(--ipv2-page-bg);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Roboto", "Oxygen", "Ubuntu", "Cantarell", sans-serif;
            color: var(--ipv2-ink);
            padding-bottom: 80px;
            overflow-x: hidden;
            font-weight: 500;
        }
        img,
        svg,
        video,
        canvas {
            max-width: 100%;
            height: auto;
        }
        .card {
            border: 1px solid var(--ipv2-border);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05), 0 10px 15px rgba(0, 0, 0, 0.05);
            border-radius: 16px;
            background: var(--ipv2-surface);
            overflow: hidden;
        }
        .card:hover {
            box-shadow: 0 12px 24px rgba(30, 64, 175, 0.12);
            border-color: var(--ipv2-primary-light);
        }
        .navbar-brand {
            font-weight: 800;
            font-size: 1.35rem;
            background: linear-gradient(135deg, var(--ipv2-primary), var(--ipv2-primary-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .navbar-brand i {
            font-size: 1.8rem;
            background: linear-gradient(135deg, var(--ipv2-primary), var(--ipv2-accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .navbar .nav-link {
            padding-top: 0.65rem;
            padding-bottom: 0.65rem;
            font-weight: 600;
            font-size: 0.95rem;
            color: rgba(255, 255, 255, 0.85) !important;
            position: relative;
        }
        .navbar .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--ipv2-accent);
            transition: width 0.3s ease;
        }
        .navbar .nav-link:hover {
            color: #ffffff !important;
        }
        .navbar .nav-link:hover::after {
            width: 100%;
        }
        .bg-is2 {
            background: linear-gradient(135deg, var(--ipv2-primary-dark), var(--ipv2-primary)) !important;
            box-shadow: 0 4px 12px rgba(30, 40, 175, 0.2);
        }
        .btn-is2 {
            background: linear-gradient(135deg, var(--ipv2-primary), var(--ipv2-primary-light));
            color: #fff;
            border: none;
            font-weight: 600;
            border-radius: 10px;
            padding: 0.6rem 1.5rem;
            font-size: 0.95rem;
            box-shadow: 0 4px 12px rgba(30, 64, 175, 0.3);
        }
        .btn-is2:hover {
            background: linear-gradient(135deg, var(--ipv2-primary-dark), var(--ipv2-primary));
            color: #fff;
            box-shadow: 0 8px 20px rgba(30, 64, 175, 0.4);
            transform: translateY(-2px);
        }
        .btn-is2:active {
            transform: translateY(0);
        }
        .btn-outline-primary {
            border-color: var(--ipv2-primary);
            color: var(--ipv2-primary);
            font-weight: 600;
            border-radius: 10px;
            border-width: 2px;
        }
        .btn-outline-primary:hover {
            background-color: var(--ipv2-primary);
            border-color: var(--ipv2-primary);
            color: #fff;
        }
        .btn-outline-success {
            border-color: var(--ipv2-success);
            color: var(--ipv2-success);
            font-weight: 600;
            border-radius: 10px;
            border-width: 2px;
        }
        .btn-outline-success:hover {
            background-color: var(--ipv2-success);
            border-color: var(--ipv2-success);
            color: #fff;
        }
        .btn {
            font-weight: 600;
            border-radius: 10px;
            font-size: 0.95rem;
        }
        .btn-outline-secondary {
            border-radius: 10px;
            border-width: 2px;
            font-weight: 600;
        }
        .btn-outline-secondary:hover {
            color: #fff;
        }
        .btn-outline-danger {
            border-radius: 10px;
            border-width: 2px;
            font-weight: 600;
        }
        .form-control,
        .form-select,
        .btn {
            min-height: 42px;
        }
        .btn-sm,
        .form-control-sm,
        .form-select-sm {
            min-height: 36px;
        }
        .form-control,
        .form-select {
            border: 2px solid var(--ipv2-border);
            border-radius: 10px;
            font-weight: 500;
            background: var(--ipv2-surface);
            color: var(--ipv2-ink);
        }
        .form-control:focus,
        .form-select:focus {
            border-color: var(--ipv2-primary);
            box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
        }
        .table-responsive {
            -webkit-overflow-scrolling: touch;
        }
        .table {
            font-weight: 500;
            color: var(--ipv2-ink);
        }
        .table th {
            background: var(--ipv2-soft-bg);
            color: var(--ipv2-ink);
            font-weight: 700;
            border-bottom: 2px solid #e2e8f0;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }
        .table td {
            vertical-align: middle;
            border-bottom: 1px solid var(--ipv2-border);
        }
        .table tbody tr:hover {
            background: var(--ipv2-soft-bg);
        }
        .badge {
            font-weight: 700;
            padding: 0.6rem 1rem;
            border-radius: 8px;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--ipv2-nav-bg);
            border-top: 2px solid var(--ipv2-border);
            z-index: 1050;
            padding-bottom: env(safe-area-inset-bottom);
            box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.08);
        }
        .bottom-nav a {
            flex: 1;
            text-align: center;
            min-height: 60px;
            padding: 8px 4px;
            color: var(--ipv2-text-muted);
            text-decoration: none;
            font-size: 12px;
            line-height: 1.15;
            font-weight: 600;
        }
        .bottom-nav a.active {
            color: var(--ipv2-primary);
            font-weight: 700;
        }
        .bottom-nav a.active i {
            color: var(--ipv2-accent);
        }
        .bottom-nav i {
            display: block;
            font-size: 20px;
            margin-bottom: 4px;
        }
        .theme-toggle-btn {
            border-radius: 999px;
            min-height: 34px;
            padding: 0.35rem 0.75rem;
            font-size: 0.85rem;
            font-weight: 700;
            border-width: 1.5px;
        }
        .table > :not(caption) > * > * {
            color: var(--ipv2-ink);
            border-color: var(--ipv2-border);
        }
        .text-muted,
        .small.text-muted,
        small.text-muted {
            color: var(--ipv2-text-muted) !important;
        }
        .modal-content,
        .dropdown-menu,
        .list-group-item,
        .offcanvas {
            background: var(--ipv2-surface);
            color: var(--ipv2-ink);
            border-color: var(--ipv2-border);
        }
        .form-control::placeholder {
            color: var(--ipv2-text-muted);
            opacity: 1;
        }
        hr {
            border-color: var(--ipv2-border);
            opacity: 1;
        }
        @media (min-width: 992px) {
            .bottom-nav {
                display: none !important;
            }
            body {
                padding-bottom: 20px;
            }
        }
        @media (max-width: 991.98px) {
            .container {
                padding-left: 12px;
                padding-right: 12px;
            }
            .navbar-collapse {
                background: rgba(30, 64, 175, 0.98);
                border-radius: 12px;
                margin-top: 0.6rem;
                padding: 0.5rem 0.8rem;
                backdrop-filter: blur(10px);
            }
        }
        @media (max-width: 575.98px) {
            body {
                padding-bottom: 84px;
            }
            .container.py-3 {
                padding-top: 0.65rem !important;
                padding-bottom: 0.65rem !important;
            }
            .card {
                border-radius: 12px;
            }
            .card .card-body {
                padding: 0.85rem;
            }
            .modal-dialog {
                margin: 0.45rem;
            }
            .modal-body {
                max-height: 72vh;
                overflow-y: auto;
            }
            .table {
                font-size: 0.88rem;
            }
            .bottom-nav a {
                font-size: 11px;
            }
            .bottom-nav i {
                font-size: 17px;
            }
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-is2 sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?= BASE_URL ?>admin/dashboard.php">
            <i class="fas fa-cube"></i>
            <span>Inventory Pro <span style="color: var(--ipv2-accent); font-style: italic;">V2</span></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topMenu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="topMenu">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>admin/pos.php"><i class="fas fa-cash-register me-1"></i> POS</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>admin/products.php"><i class="fas fa-boxes-stacked me-1"></i> Products</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>admin/scan.php"><i class="fas fa-barcode me-1"></i> Scan</a></li>
                <?php if (is_admin()): ?>
                <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>admin/inventory_log.php"><i class="fas fa-clipboard-list me-1"></i> Log</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>admin/analytics.php"><i class="fas fa-chart-bar me-1"></i> Analytics</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>admin/exhibition_analysis.php"><i class="fas fa-building me-1"></i> Exhibition</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>admin/users.php"><i class="fas fa-users me-1"></i> Staff</a></li>
                <?php endif; ?>
                <li class="nav-item d-flex align-items-center me-2">
                    <button type="button" id="themeToggleBtn" class="btn btn-sm btn-outline-light theme-toggle-btn" onclick="toggleTheme()">
                        <i class="fas fa-moon me-1"></i><span id="themeToggleText">Dark</span>
                    </button>
                </li>
                <li class="nav-item"><a class="nav-link ms-2" href="<?= BASE_URL ?>logout.php"><i class="fas fa-sign-out-alt me-1"></i> Logout</a></li>
            </ul>
        </div>
    </div>
</nav>
<div class="container py-3">
<script>
function updateThemeToggleUi() {
    const current = document.documentElement.getAttribute('data-theme') || 'light';
    const btn = document.getElementById('themeToggleBtn');
    const txt = document.getElementById('themeToggleText');
    if (!btn || !txt) return;

    if (current === 'dark') {
        btn.innerHTML = '<i class="fas fa-sun me-1"></i><span id="themeToggleText">Light</span>';
    } else {
        btn.innerHTML = '<i class="fas fa-moon me-1"></i><span id="themeToggleText">Dark</span>';
    }
}

function toggleTheme() {
    const current = document.documentElement.getAttribute('data-theme') || 'light';
    const next = current === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('ipv2_theme', next);
    updateThemeToggleUi();
}

updateThemeToggleUi();
</script>
