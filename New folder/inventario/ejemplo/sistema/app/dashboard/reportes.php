<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/layout.php';

require_login();

$pdo = getPDO();

$fecha = $_GET['fecha'] ?? date('Y-m-d');
$inicio = $_GET['inicio'] ?? date('Y-m-01');
$fin = $_GET['fin'] ?? date('Y-m-d');

$stmtDia = $pdo->prepare('SELECT id, fecha, total FROM ventas WHERE DATE(fecha) = :fecha ORDER BY fecha DESC');
$stmtDia->execute(['fecha' => $fecha]);
$ventasDia = $stmtDia->fetchAll();

$stmtSubtotalDia = $pdo->prepare('SELECT COALESCE(SUM(total), 0) AS subtotal FROM ventas WHERE DATE(fecha) = :fecha');
$stmtSubtotalDia->execute(['fecha' => $fecha]);
$subtotalDia = (float) $stmtSubtotalDia->fetchColumn();

$stmtRango = $pdo->prepare('SELECT id, fecha, total FROM ventas WHERE DATE(fecha) BETWEEN :inicio AND :fin ORDER BY fecha DESC');
$stmtRango->execute(['inicio' => $inicio, 'fin' => $fin]);
$ventasRango = $stmtRango->fetchAll();

$stmtSubtotalRango = $pdo->prepare('SELECT COALESCE(SUM(total), 0) AS subtotal FROM ventas WHERE DATE(fecha) BETWEEN :inicio AND :fin');
$stmtSubtotalRango->execute(['inicio' => $inicio, 'fin' => $fin]);
$subtotalRango = (float) $stmtSubtotalRango->fetchColumn();

render_header('Reportes');
?>

<div class="row g-4">
    <div class="col-12 col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h2 class="h5">Ventas por dia</h2>
                <form class="row g-2 mb-3" method="get">
                    <div class="col-8">
                        <input type="date" name="fecha" class="form-control" value="<?php echo e($fecha); ?>">
                    </div>
                    <div class="col-4">
                        <button class="btn btn-primary w-100" type="submit">Filtrar</button>
                    </div>
                </form>
                <ul class="list-group list-group-flush">
                    <?php if (!$ventasDia): ?>
                        <li class="list-group-item text-muted">No hay ventas para esa fecha.</li>
                    <?php endif; ?>
                    <?php foreach ($ventasDia as $venta): ?>
                        <li class="list-group-item d-flex justify-content-between">
                            <span>#<?php echo (int) $venta['id']; ?> - <?php echo e($venta['fecha']); ?></span>
                            <strong><?php echo e(format_gs((float) $venta['total'])); ?></strong>
                        </li>
                    <?php endforeach; ?>
                    <?php if ($ventasDia): ?>
                        <li class="list-group-item d-flex justify-content-between fw-bold">
                            <span>Subtotal del día</span>
                            <span><?php echo e(format_gs($subtotalDia)); ?></span>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h2 class="h5">Ventas por rango de fechas</h2>
                <form class="row g-2 mb-3" method="get">
                    <div class="col-5">
                        <input type="date" name="inicio" class="form-control" value="<?php echo e($inicio); ?>">
                    </div>
                    <div class="col-5">
                        <input type="date" name="fin" class="form-control" value="<?php echo e($fin); ?>">
                    </div>
                    <div class="col-2">
                        <button class="btn btn-primary w-100" type="submit">OK</button>
                    </div>
                </form>
                <ul class="list-group list-group-flush">
                    <?php if (!$ventasRango): ?>
                        <li class="list-group-item text-muted">No hay ventas en el rango.</li>
                    <?php endif; ?>
                    <?php foreach ($ventasRango as $venta): ?>
                        <li class="list-group-item d-flex justify-content-between">
                            <span>#<?php echo (int) $venta['id']; ?> - <?php echo e($venta['fecha']); ?></span>
                            <strong><?php echo e(format_gs((float) $venta['total'])); ?></strong>
                        </li>
                    <?php endforeach; ?>
                    <?php if ($ventasRango): ?>
                        <li class="list-group-item d-flex justify-content-between fw-bold">
                            <span>Subtotal del rango</span>
                            <span><?php echo e(format_gs($subtotalRango)); ?></span>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php render_footer();
