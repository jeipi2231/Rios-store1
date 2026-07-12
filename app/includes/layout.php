<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

function render_header(string $title): void
{
    $user = $_SESSION['user'] ?? null;
    $flash = get_flash();
    $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';

    $isActive = static function (string $needle) use ($currentPath): bool {
        return strpos($currentPath, $needle) !== false;
    };
    ?>
    <!doctype html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php echo e($title); ?> | <?php echo e(APP_NAME); ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
        <link href="<?php echo public_url('/app/assets/css/styles.css'); ?>" rel="stylesheet">
    </head>
    <body>
    <?php if ($user): ?>
        <div class="app-shell">
            <aside class="app-sidebar" id="appSidebar">
                <div class="sidebar-brand">
                    <a href="<?php echo BASE_URL; ?>/app/dashboard.php" class="brand-link">
                        <i class="bi bi-shop"></i>
                        <span><?php echo e(APP_NAME); ?></span>
                    </a>
                </div>
                <nav class="sidebar-nav">
                    <a class="sidebar-link <?php echo $isActive('/app/dashboard') && !$isActive('/app/dashboard/reportes.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/app/dashboard.php">
                        <i class="bi bi-speedometer2"></i><span>Dashboard</span>
                    </a>
                    <a class="sidebar-link <?php echo $isActive('/app/ventas') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/app/ventas/index.php">
                        <i class="bi bi-receipt"></i><span>Cajero / Ventas</span>
                    </a>
                    <a class="sidebar-link <?php echo $isActive('/app/movimientos') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/app/movimientos/index.php">
                        <i class="bi bi-arrow-left-right"></i><span>Gastos y salidas</span>
                    </a>
                    <a class="sidebar-link <?php echo $isActive('/app/clientes') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/app/clientes/index.php">
                        <i class="bi bi-people"></i><span>Clientes</span>
                    </a>
                    <a class="sidebar-link <?php echo $isActive('/app/productos') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/app/productos/index.php">
                        <i class="bi bi-box-seam"></i><span>Productos</span>
                    </a>
                    <a class="sidebar-link <?php echo $isActive('/app/categorias') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/app/categorias/index.php">
                        <i class="bi bi-tags"></i><span>Categorias</span>
                    </a>
                    <?php if (($user['rol'] ?? '') === 'admin'): ?>
                    <a class="sidebar-link <?php echo $isActive('/app/proveedores') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/app/proveedores/index.php">
                        <i class="bi bi-truck"></i><span>Proveedores</span>
                    </a>
                    <?php endif; ?>
                    <?php if (($user['rol'] ?? '') === 'admin'): ?>
                    <a class="sidebar-link <?php echo $isActive('/app/usuarios') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/app/usuarios/index.php">
                        <i class="bi bi-person-gear"></i><span>Usuarios</span>
                    </a>
                    <?php endif; ?>
                    <a class="sidebar-link <?php echo $isActive('/app/dashboard/reportes.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/app/dashboard/reportes.php">
                        <i class="bi bi-bar-chart"></i><span>Reportes</span>
                    </a>
                </nav>
                <div class="sidebar-user">
                    <div class="small text-muted">Sesion iniciada</div>
                    <div class="fw-semibold"><?php echo e($user['nombre']); ?></div>
                    <div class="small text-muted text-capitalize mb-2"><?php echo e($user['rol']); ?></div>
                    <a class="btn btn-outline-secondary btn-sm w-100" href="<?php echo BASE_URL; ?>/app/auth/logout.php">Salir</a>
                </div>
            </aside>
            <div class="app-content">
                <header class="app-topbar d-lg-none">
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMobile" aria-controls="sidebarMobile">
                        <i class="bi bi-list"></i> Menu
                    </button>
                </header>
                <main class="container-fluid app-main pb-4">
    <?php else: ?>
        <main class="container pb-5 pt-4">
    <?php endif; ?>
        <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : 'success'; ?>" role="alert">
                <?php echo e($flash['message']); ?>
            </div>
        <?php endif; ?>
    <?php
}

function render_footer(): void
{
    $user = $_SESSION['user'] ?? null;
    ?>
    </main>
    <?php if ($user): ?>
            </div>
        </div>

        <div class="offcanvas offcanvas-start" tabindex="-1" id="sidebarMobile" aria-labelledby="sidebarMobileLabel">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title" id="sidebarMobileLabel">Menu principal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body">
                <a class="d-block mb-2" href="<?php echo BASE_URL; ?>/app/dashboard.php">Dashboard</a>
                <a class="d-block mb-2" href="<?php echo BASE_URL; ?>/app/ventas/index.php">Cajero / Ventas</a>
                <a class="d-block mb-2" href="<?php echo BASE_URL; ?>/app/movimientos/index.php">Gastos y salidas</a>
                <a class="d-block mb-2" href="<?php echo BASE_URL; ?>/app/clientes/index.php">Clientes</a>
                <a class="d-block mb-2" href="<?php echo BASE_URL; ?>/app/productos/index.php">Productos</a>
                <a class="d-block mb-2" href="<?php echo BASE_URL; ?>/app/categorias/index.php">Categorias</a>
                <?php if (($user['rol'] ?? '') === 'admin'): ?>
                <a class="d-block mb-2" href="<?php echo BASE_URL; ?>/app/proveedores/index.php">Proveedores</a>
                <a class="d-block mb-2" href="<?php echo BASE_URL; ?>/app/usuarios/index.php">Usuarios</a>
                <?php endif; ?>
                <a class="d-block mb-2" href="<?php echo BASE_URL; ?>/app/dashboard/reportes.php">Reportes</a>
                <hr>
                <a class="btn btn-outline-secondary btn-sm" href="<?php echo BASE_URL; ?>/app/auth/logout.php">Salir</a>
            </div>
        </div>
    <?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
}
