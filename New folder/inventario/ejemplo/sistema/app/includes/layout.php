<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

function render_header(string $title): void
{
    $user = $_SESSION['user'] ?? null;
    $flash = get_flash();
    ?>
    <!doctype html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php echo e($title); ?> | Sistema de Gestion</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="<?php echo BASE_URL; ?>/assets/css/styles.css" rel="stylesheet">
    </head>
    <body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo BASE_URL; ?>/app/dashboard.php">Sistema de Gestion</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <?php if ($user): ?>
                        <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>/app/dashboard.php">Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>/app/ventas/index.php">Ventas</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>/app/productos/index.php">Productos</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>/app/dashboard/reportes.php">Reportes</a></li>
                    <?php endif; ?>
                </ul>
                <?php if ($user): ?>
                    <span class="text-white me-3"><?php echo e($user['nombre']); ?> (<?php echo e($user['rol']); ?>)</span>
                    <a class="btn btn-outline-light btn-sm" href="<?php echo BASE_URL; ?>/app/auth/logout.php">Salir</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <main class="container pb-5">
        <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : 'success'; ?>" role="alert">
                <?php echo e($flash['message']); ?>
            </div>
        <?php endif; ?>
    <?php
}

function render_footer(): void
{
    ?>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
}
