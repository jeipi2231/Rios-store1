<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/layout.php';

require_login();

$pdo = getPDO();

$stmt = $pdo->query('SELECT COALESCE(SUM(total), 0) AS total_hoy, COUNT(*) AS cantidad_hoy FROM ventas WHERE DATE(fecha) = CURDATE()');
$resumen = $stmt->fetch();

$stmtStock = $pdo->query('SELECT id, nombre, stock, stock_minimo FROM productos WHERE activo = 1 AND stock < stock_minimo ORDER BY stock ASC');
$bajoStock = $stmtStock->fetchAll();

render_header('Dashboard');
?>

<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted mb-1">Ventas del dia (Gs)</p>
                <h2 class="h3 mb-0"><?php echo e(format_gs((float) $resumen['total_hoy'])); ?></h2>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted mb-1">Cantidad de ventas hoy</p>
                <h2 class="h3 mb-0"><?php echo (int) $resumen['cantidad_hoy']; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
                <p class="text-muted mb-1">Productos con bajo stock</p>
                <h2 class="h3 mb-2"><?php echo count($bajoStock); ?></h2>
                <a href="<?php echo BASE_URL; ?>/app/productos/index.php" class="btn btn-outline-primary btn-sm">Ver inventario</a>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <h3 class="h5">Alertas de stock</h3>
        <?php if (!$bajoStock): ?>
            <p class="mb-0 text-success">Todo el inventario esta por encima del minimo.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Stock</th>
                        <th>Stock minimo</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($bajoStock as $item): ?>
                        <tr>
                            <td><?php echo e($item['nombre']); ?></td>
                            <td><span class="badge text-bg-danger"><?php echo (int) $item['stock']; ?></span></td>
                            <td><?php echo (int) $item['stock_minimo']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php render_footer();
