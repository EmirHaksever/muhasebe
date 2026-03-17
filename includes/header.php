<?php
$pageTitle = $pageTitle ?? 'Muhasebe Paneli';
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

function isActivePath($path, $currentPath)
{
    return $currentPath === $path ? 'active' : '';
}

function isOpenMenu($paths, $currentPath)
{
    foreach ($paths as $path) {
        if ($currentPath === $path) {
            return 'show';
        }
    }
    return '';
}
?>
<!doctype html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="/assets/css/admin.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body {
            background-color: #f8f9fa;
        }

        .app-sidebar {
            width: 270px;
            min-height: 100vh;
        }

        .sidebar-link {
            border-radius: 0.75rem;
            padding: 0.7rem 0.9rem;
            color: #cbd5e1;
            text-decoration: none;
            display: block;
        }

        .sidebar-link:hover,
        .sidebar-link.active {
            background: rgba(255, 255, 255, .08);
            color: #fff;
        }

        .sidebar-section-btn {
            width: 100%;
            text-align: left;
            border: 0;
            background: transparent;
            color: #cbd5e1;
            border-radius: 0.75rem;
            padding: 0.7rem 0.9rem;
        }

        .sidebar-section-btn:hover,
        .sidebar-section-btn:not(.collapsed) {
            background: rgba(255, 255, 255, .08);
            color: #fff;
        }

        .sidebar-submenu a {
            display: block;
            padding: 0.55rem 0.9rem 0.55rem 1.75rem;
            border-radius: 0.65rem;
            color: #cbd5e1;
            text-decoration: none;
            font-size: 0.95rem;
        }

        .sidebar-submenu a:hover,
        .sidebar-submenu a.active {
            background: rgba(255, 255, 255, .06);
            color: #fff;
        }

        .app-content {
            min-width: 0;
        }

        .page-card {
            border: 0;
            border-radius: 1rem;
            box-shadow: 0 0.125rem 0.5rem rgba(0, 0, 0, .05);
        }

        @media (max-width: 991.98px) {
            .app-sidebar {
                width: 100%;
                min-height: auto;
            }
        }
    </style>
</head>

<body>

    <div class="d-flex">
        <!-- Desktop Sidebar -->
        <aside class="app-sidebar flex-shrink-0 bg-dark text-white p-3 d-none d-lg-block">
            <div class="fs-5 fw-bold mb-4">Muhasebe Paneli</div>

            <nav class="d-flex flex-column gap-2">
                <a class="sidebar-link <?php echo isActivePath('/dashboard.php', $currentPath); ?>" href="/dashboard.php">Dashboard</a>

                <hr class="border-secondary my-2">

                <div>
                    <button class="sidebar-section-btn collapsed d-flex justify-content-between align-items-center"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#gelirMenuDesktop">
                        <span>Gelir Yönetimi</span>
                        <span>▾</span>
                    </button>
                    <div class="collapse sidebar-submenu <?php echo isOpenMenu([
                                                                '/income/income_add.php',
                                                                '/income/income_list.php',
                                                                '/income_categories/income_category_list.php'
                                                            ], $currentPath); ?>" id="gelirMenuDesktop">
                        <div class="mt-1 d-flex flex-column gap-1">
                            <a class="<?php echo isActivePath('/income/income_add.php', $currentPath); ?>" href="/income/income_add.php">Gelir Ekle</a>
                            <a class="<?php echo isActivePath('/income/income_list.php', $currentPath); ?>" href="/income/income_list.php">Gelir Listesi</a>
                            <a class="<?php echo isActivePath('/income_categories/income_category_list.php', $currentPath); ?>" href="/income_categories/income_category_list.php">Gelir Kategorileri</a>
                        </div>
                    </div>
                </div>

                <div>
                    <button class="sidebar-section-btn collapsed d-flex justify-content-between align-items-center"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#giderMenuDesktop">
                        <span>Gider Yönetimi</span>
                        <span>▾</span>
                    </button>
                    <div class="collapse sidebar-submenu <?php echo isOpenMenu([
                                                                '/expense/expense_add.php',
                                                                '/expense/expense_list.php',
                                                                '/expense_categories/expense_category.php'
                                                            ], $currentPath); ?>" id="giderMenuDesktop">
                        <div class="mt-1 d-flex flex-column gap-1">
                            <a class="<?php echo isActivePath('/expense/expense_add.php', $currentPath); ?>" href="/expense/expense_add.php">Gider Ekle</a>
                            <a class="<?php echo isActivePath('/expense/expense_list.php', $currentPath); ?>" href="/expense/expense_list.php">Gider Listesi</a>
                            <a class="<?php echo isActivePath('/expense_categories/expense_category.php', $currentPath); ?>" href="/expense_categories/expense_category.php">Gider Kategorileri</a>
                        </div>
                    </div>
                </div>

                <div>
                    <button class="sidebar-section-btn collapsed d-flex justify-content-between align-items-center"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#projeMenuDesktop">
                        <span>Proje Yönetimi</span>
                        <span>▾</span>
                    </button>
                    <div class="collapse sidebar-submenu <?php echo isOpenMenu([
                                                                '/projects/project_add.php',
                                                                '/projects/project_list.php'
                                                            ], $currentPath); ?>" id="projeMenuDesktop">
                        <div class="mt-1 d-flex flex-column gap-1">
                            <a class="<?php echo isActivePath('/projects/project_add.php', $currentPath); ?>" href="/projects/project_add.php">Proje Ekle</a>
                            <a class="<?php echo isActivePath('/projects/project_list.php', $currentPath); ?>" href="/projects/project_list.php">Proje Listesi</a>
                        </div>
                    </div>
                </div>

                <div>
                    <button class="sidebar-section-btn collapsed d-flex justify-content-between align-items-center"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#cariMenuDesktop">
                        <span>Müşteri Hesapları</span>
                        <span>▾</span>
                    </button>
                    <div class="collapse sidebar-submenu <?php echo isOpenMenu([
                                                                '/cari/cari_add.php',
                                                                '/cari/cari_list.php'
                                                            ], $currentPath); ?>" id="cariMenuDesktop">
                        <div class="mt-1 d-flex flex-column gap-1">
                            <a class="<?php echo isActivePath('/cari/cari_add.php', $currentPath); ?>" href="/cari/cari_add.php">Müşteri Hesap Ekle</a>
                            <a class="<?php echo isActivePath('/cari/cari_list.php', $currentPath); ?>" href="/cari/cari_list.php">Müşteri Hesapları</a>
                        </div>
                    </div>
                </div>

                <hr class="border-secondary my-2">

                <a class="sidebar-link <?php echo isActivePath('/hareketler.php', $currentPath); ?>" href="/hareketler.php">Hareketler</a>
                <a class="sidebar-link <?php echo isActivePath('/reports.php', $currentPath); ?>" href="/reports.php">Raporlar</a>

                <hr class="border-secondary my-2">

                <a class="sidebar-link <?php echo isActivePath('/settings.php', $currentPath); ?>" href="/settings.php">Ayarlar</a>
            </nav>
        </aside>

        <div class="app-content flex-grow-1">
            <!-- Topbar -->
            <nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top shadow-sm">
                <div class="container-fluid">
                    <button class="btn btn-outline-dark d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar">
                        ☰
                    </button>

                    <a class="navbar-brand fw-bold" href="/dashboard.php">
                        <?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?>
                    </a>

                    <div class="ms-auto d-flex align-items-center gap-3">
                        <span class="text-muted small">
                            Hoş geldiniz, <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin', ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                        <a href="/auth/logout.php" class="btn btn-danger btn-sm">Çıkış Yap</a>
                    </div>
                </div>
            </nav>

            <!-- Mobile Sidebar -->
            <div class="offcanvas offcanvas-start bg-dark text-white" tabindex="-1" id="mobileSidebar">
                <div class="offcanvas-header border-bottom border-secondary">
                    <h5 class="offcanvas-title">Muhasebe Paneli</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
                </div>
                <div class="offcanvas-body">
                    <nav class="d-flex flex-column gap-2">
                        <a class="sidebar-link <?php echo isActivePath('/dashboard.php', $currentPath); ?>" href="/dashboard.php">Dashboard</a>

                        <div>
                            <button class="sidebar-section-btn collapsed d-flex justify-content-between align-items-center"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#gelirMenuMobile">
                                <span>Gelir Yönetimi</span>
                                <span>▾</span>
                            </button>
                            <div class="collapse sidebar-submenu <?php echo isOpenMenu([
                                                                        '/income/income_add.php',
                                                                        '/income/income_list.php',
                                                                        '/income_categories/income_category_list.php'
                                                                    ], $currentPath); ?>" id="gelirMenuMobile">
                                <div class="mt-1 d-flex flex-column gap-1">
                                    <a class="<?php echo isActivePath('/income/income_add.php', $currentPath); ?>" href="/income/income_add.php">Gelir Ekle</a>
                                    <a class="<?php echo isActivePath('/income/income_list.php', $currentPath); ?>" href="/income/income_list.php">Gelir Listesi</a>
                                    <a class="<?php echo isActivePath('/income_categories/income_category_list.php', $currentPath); ?>" href="/income_categories/income_category_list.php">Gelir Kategorileri</a>
                                </div>
                            </div>
                        </div>

                        <div>
                            <button class="sidebar-section-btn collapsed d-flex justify-content-between align-items-center"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#giderMenuMobile">
                                <span>Gider Yönetimi</span>
                                <span>▾</span>
                            </button>
                            <div class="collapse sidebar-submenu <?php echo isOpenMenu([
                                                                        '/expense/expense_add.php',
                                                                        '/expense/expense_list.php',
                                                                        '/expense_categories/expense_category.php'
                                                                    ], $currentPath); ?>" id="giderMenuMobile">
                                <div class="mt-1 d-flex flex-column gap-1">
                                    <a class="<?php echo isActivePath('/expense/expense_add.php', $currentPath); ?>" href="/expense/expense_add.php">Gider Ekle</a>
                                    <a class="<?php echo isActivePath('/expense/expense_list.php', $currentPath); ?>" href="/expense/expense_list.php">Gider Listesi</a>
                                    <a class="<?php echo isActivePath('/expense_categories/expense_category.php', $currentPath); ?>" href="/expense_categories/expense_category.php">Gider Kategorileri</a>
                                </div>
                            </div>
                        </div>

                        <div>
                            <button class="sidebar-section-btn collapsed d-flex justify-content-between align-items-center"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#projeMenuMobile">
                                <span>Proje Yönetimi</span>
                                <span>▾</span>
                            </button>
                            <div class="collapse sidebar-submenu <?php echo isOpenMenu([
                                                                        '/projects/project_add.php',
                                                                        '/projects/project_list.php'
                                                                    ], $currentPath); ?>" id="projeMenuMobile">
                                <div class="mt-1 d-flex flex-column gap-1">
                                    <a class="<?php echo isActivePath('/projects/project_add.php', $currentPath); ?>" href="/projects/project_add.php">Proje Ekle</a>
                                    <a class="<?php echo isActivePath('/projects/project_list.php', $currentPath); ?>" href="/projects/project_list.php">Proje Listesi</a>
                                </div>
                            </div>
                        </div>

                        <div>
                            <button class="sidebar-section-btn collapsed d-flex justify-content-between align-items-center"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#cariMenuMobile">
                                <span>Müşteri Hesapları</span>
                                <span>▾</span>
                            </button>
                            <div class="collapse sidebar-submenu <?php echo isOpenMenu([
                                                                        '/cari/cari_add.php',
                                                                        '/cari/cari_list.php'
                                                                    ], $currentPath); ?>" id="cariMenuMobile">
                                <div class="mt-1 d-flex flex-column gap-1">
                                    <a class="<?php echo isActivePath('/cari/cari_add.php', $currentPath); ?>" href="/cari/cari_add.php">Müşteri Hesap Ekle</a>
                                    <a class="<?php echo isActivePath('/cari/cari_list.php', $currentPath); ?>" href="/cari/cari_list.php">Müşteri Hesapları</a>
                                </div>
                            </div>
                        </div>

                        <a class="sidebar-link <?php echo isActivePath('/hareketler.php', $currentPath); ?>" href="/hareketler.php">Hareketler</a>
                        <a class="sidebar-link <?php echo isActivePath('/reports.php', $currentPath); ?>" href="/reports.php">Raporlar</a>
                        <a class="sidebar-link <?php echo isActivePath('/settings.php', $currentPath); ?>" href="/settings.php">Ayarlar</a>
                    </nav>
                </div>
            </div>

            <main class="container-fluid py-4">
                <div class="page-card bg-white p-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
                    </div>