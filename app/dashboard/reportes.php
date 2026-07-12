<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/schema.php';

require_login();
ensure_extended_schema();

$pdo = getPDO();

$fecha = $_GET['fecha'] ?? date('Y-m-d');
$inicio = $_GET['inicio'] ?? date('Y-m-01');
$fin = $_GET['fin'] ?? date('Y-m-d');

$stmtDia = $pdo->prepare('SELECT id, fecha, total, metodo_pago, banco_pago, tipo_entrega FROM ventas WHERE DATE(fecha) = :fecha ORDER BY fecha DESC');
$stmtDia->execute(['fecha' => $fecha]);
$ventasDia = $stmtDia->fetchAll();

$stmtSubtotalDia = $pdo->prepare('SELECT COALESCE(SUM(total), 0) AS subtotal FROM ventas WHERE DATE(fecha) = :fecha');
$stmtSubtotalDia->execute(['fecha' => $fecha]);
$subtotalDia = (float) $stmtSubtotalDia->fetchColumn();

$stmtGastoDia = $pdo->prepare("SELECT COALESCE(SUM(monto), 0) FROM movimientos_stock WHERE tipo = 'entrada' AND DATE(fecha) = :fecha");
$stmtGastoDia->execute(['fecha' => $fecha]);
$gastoDia = (float) $stmtGastoDia->fetchColumn();

$stmtIngresoDia = $pdo->prepare("SELECT COALESCE(SUM(monto), 0) FROM movimientos_stock WHERE tipo = 'ingreso' AND DATE(fecha) = :fecha");
$stmtIngresoDia->execute(['fecha' => $fecha]);
$ingresoDia = (float) $stmtIngresoDia->fetchColumn();

$gananciaDia = ($subtotalDia + $ingresoDia) - $gastoDia;

$stmtRango = $pdo->prepare('SELECT id, fecha, total, metodo_pago, banco_pago, tipo_entrega FROM ventas WHERE DATE(fecha) BETWEEN :inicio AND :fin ORDER BY fecha DESC');
$stmtRango->execute(['inicio' => $inicio, 'fin' => $fin]);
$ventasRango = $stmtRango->fetchAll();

$stmtSubtotalRango = $pdo->prepare('SELECT COALESCE(SUM(total), 0) AS subtotal FROM ventas WHERE DATE(fecha) BETWEEN :inicio AND :fin');
$stmtSubtotalRango->execute(['inicio' => $inicio, 'fin' => $fin]);
$subtotalRango = (float) $stmtSubtotalRango->fetchColumn();

$stmtGastoRango = $pdo->prepare("SELECT COALESCE(SUM(monto), 0) FROM movimientos_stock WHERE tipo = 'entrada' AND DATE(fecha) BETWEEN :inicio AND :fin");
$stmtGastoRango->execute(['inicio' => $inicio, 'fin' => $fin]);
$gastoRango = (float) $stmtGastoRango->fetchColumn();

$stmtIngresoRango = $pdo->prepare("SELECT COALESCE(SUM(monto), 0) FROM movimientos_stock WHERE tipo = 'ingreso' AND DATE(fecha) BETWEEN :inicio AND :fin");
$stmtIngresoRango->execute(['inicio' => $inicio, 'fin' => $fin]);
$ingresoRango = (float) $stmtIngresoRango->fetchColumn();

$gananciaRango = ($subtotalRango + $ingresoRango) - $gastoRango;

$stmtMovRango = $pdo->prepare(
    "SELECT m.fecha, m.tipo, m.cantidad, m.motivo, m.monto, p.nombre AS producto
     FROM movimientos_stock m
     LEFT JOIN productos p ON p.id = m.producto_id
     WHERE DATE(m.fecha) BETWEEN :inicio AND :fin
     ORDER BY m.fecha DESC
     LIMIT 150"
);
$stmtMovRango->execute(['inicio' => $inicio, 'fin' => $fin]);
$movimientosRango = $stmtMovRango->fetchAll();

render_header('Reportes');
?>

<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted mb-1">Ingresos del dia</p>
                <h3 class="h4 mb-0"><?php echo e(format_gs($subtotalDia + $ingresoDia)); ?></h3>
                <small class="text-muted"><?php echo e($fecha); ?></small>
                <div class="small text-muted mt-1">Ventas: <?php echo e(format_gs($subtotalDia)); ?> | Ingreso monetario: <?php echo e(format_gs($ingresoDia)); ?></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted mb-1">Gasto del dia</p>
                <h3 class="h4 mb-0"><?php echo e(format_gs($gastoDia)); ?></h3>
                <small class="text-muted">Gastos de inventario</small>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted mb-1">Ganancia del dia</p>
                <h3 class="h4 mb-0"><?php echo e(format_gs($gananciaDia)); ?></h3>
                <small class="text-muted">Ingresos - gastos</small>
            </div>
        </div>
    </div>
</div>

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
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Fecha</th>
                            <th>Metodo</th>
                            <th>Banco</th>
                            <th>Entrega</th>
                            <th class="text-end">Total</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$ventasDia): ?>
                            <tr><td colspan="6" class="text-muted">No hay ventas para esa fecha.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($ventasDia as $venta): ?>
                            <tr>
                                <td>#<?php echo (int) $venta['id']; ?></td>
                                <td><?php echo e($venta['fecha']); ?></td>
                                <td><?php echo e(payment_method_label((string) $venta['metodo_pago'])); ?></td>
                                <td><?php echo e($venta['banco_pago'] ?: '-'); ?></td>
                                <td><?php echo e(delivery_type_label((string) $venta['tipo_entrega'])); ?></td>
                                <td class="text-end"><?php echo e(format_gs((float) $venta['total'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <?php if ($ventasDia): ?>
                            <tfoot>
                            <tr class="fw-bold">
                                <td colspan="5">Subtotal del día</td>
                                <td class="text-end"><?php echo e(format_gs($subtotalDia)); ?></td>
                            </tr>
                            </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
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
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Fecha</th>
                            <th>Metodo</th>
                            <th>Banco</th>
                            <th>Entrega</th>
                            <th class="text-end">Total</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$ventasRango): ?>
                            <tr><td colspan="6" class="text-muted">No hay ventas en el rango.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($ventasRango as $venta): ?>
                            <tr>
                                <td>#<?php echo (int) $venta['id']; ?></td>
                                <td><?php echo e($venta['fecha']); ?></td>
                                <td><?php echo e(payment_method_label((string) $venta['metodo_pago'])); ?></td>
                                <td><?php echo e($venta['banco_pago'] ?: '-'); ?></td>
                                <td><?php echo e(delivery_type_label((string) $venta['tipo_entrega'])); ?></td>
                                <td class="text-end"><?php echo e(format_gs((float) $venta['total'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <?php if ($ventasRango): ?>
                            <tfoot>
                            <tr class="fw-bold">
                                <td colspan="5">Subtotal del rango</td>
                                <td class="text-end"><?php echo e(format_gs($subtotalRango)); ?></td>
                            </tr>
                            </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
                <hr>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Ingreso monetario del rango</span>
                    <strong><?php echo e(format_gs($ingresoRango)); ?></strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Gasto del rango</span>
                    <strong><?php echo e(format_gs($gastoRango)); ?></strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Ganancia neta</span>
                    <strong><?php echo e(format_gs($gananciaRango)); ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mt-4">
    <div class="card-body p-0">
        <div class="px-3 py-2 border-bottom">
            <h3 class="h6 mb-0">Detalle de gastos y salidas del rango</h3>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Producto</th>
                    <th>Tipo</th>
                    <th>Cantidad</th>
                    <th>Motivo</th>
                    <th>Monto</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$movimientosRango): ?>
                    <tr><td colspan="6" class="text-muted">No hay movimientos en el rango.</td></tr>
                <?php endif; ?>
                <?php foreach ($movimientosRango as $m): ?>
                    <tr>
                        <td><?php echo e(date('d/m/Y H:i', strtotime($m['fecha']))); ?></td>
                        <td><?php echo e($m['producto'] ?? 'Sin producto'); ?></td>
                        <td>
                            <?php if ($m['tipo'] === 'entrada'): ?>
                                <span class="badge text-bg-dark">Gasto</span>
                            <?php else: ?>
                                <span class="badge <?php echo $m['tipo'] === 'ingreso' ? 'text-bg-secondary' : 'text-bg-danger'; ?>"><?php echo e($m['tipo'] === 'ingreso' ? 'Ingreso' : 'Salida'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo (int) $m['cantidad']; ?></td>
                        <td><?php echo e($m['motivo'] ?? '-'); ?></td>
                        <td><?php echo e(format_gs((float) $m['monto'])); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php render_footer();
