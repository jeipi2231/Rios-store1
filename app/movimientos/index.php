<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/schema.php';

require_login();
ensure_extended_schema();

$pdo = getPDO();
$isAdmin = (($_SESSION['user']['rol'] ?? '') === 'admin');

function apply_stock_delta(PDO $pdo, int $productoId, string $tipo, int $cantidad, bool $revert = false): void
{
    if ($productoId <= 0 || $cantidad <= 0) {
        return;
    }

    if (!in_array($tipo, ['entrada', 'salida'], true)) {
        return;
    }

    $stmtProd = $pdo->prepare('SELECT id, nombre, stock FROM productos WHERE id = :id FOR UPDATE');
    $stmtProd->execute(['id' => $productoId]);
    $producto = $stmtProd->fetch();

    if (!$producto) {
        throw new RuntimeException('Producto no encontrado.');
    }

    $sign = $tipo === 'entrada' ? 1 : -1;
    if ($revert) {
        $sign *= -1;
    }

    $nuevoStock = (int) $producto['stock'] + ($sign * $cantidad);
    if ($nuevoStock < 0) {
        throw new RuntimeException('Stock insuficiente para aplicar la correccion.');
    }

    $stmtUpd = $pdo->prepare('UPDATE productos SET stock = :stock WHERE id = :id');
    $stmtUpd->execute([
        'stock' => $nuevoStock,
        'id' => $productoId,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $productoId = (int) ($_POST['producto_id'] ?? 0);
        $tipo = $_POST['tipo'] ?? '';
        $cantidad = (int) ($_POST['cantidad'] ?? 0);
        $motivo = trim($_POST['motivo'] ?? '');
        $monto = (float) ($_POST['monto'] ?? 0);
        $usaProducto = $productoId > 0;

        if (!in_array($tipo, ['entrada', 'salida', 'ingreso'], true) || $monto < 0) {
            set_flash('error', 'Datos invalidos para registrar movimiento.');
            redirect('/app/movimientos/index.php');
        }

        if ($tipo === 'ingreso') {
            $usaProducto = false;
            $productoId = 0;
            $cantidad = 0;
            if ($monto <= 0) {
                set_flash('error', 'Para ingreso monetario el monto debe ser mayor a cero.');
                redirect('/app/movimientos/index.php');
            }
        }

        if (in_array($tipo, ['entrada', 'salida'], true) && !$usaProducto) {
            set_flash('error', 'Debes seleccionar un producto para registrar gasto o salida de stock.');
            redirect('/app/movimientos/index.php');
        }

        if (!$usaProducto && $motivo === '') {
            set_flash('error', 'Cuando no seleccionas producto, debes indicar un motivo.');
            redirect('/app/movimientos/index.php');
        }

        if ($usaProducto && $cantidad <= 0) {
            set_flash('error', 'La cantidad debe ser mayor a cero cuando seleccionas un producto.');
            redirect('/app/movimientos/index.php');
        }

        if (!$usaProducto) {
            $cantidad = 0;
        }

        try {
            $pdo->beginTransaction();

            if ($usaProducto) {
                apply_stock_delta($pdo, $productoId, $tipo, $cantidad, false);
            }

            $stmtMov = $pdo->prepare('INSERT INTO movimientos_stock (producto_id, tipo, cantidad, motivo, monto) VALUES (:producto_id, :tipo, :cantidad, :motivo, :monto)');
            $stmtMov->execute([
                'producto_id' => $usaProducto ? $productoId : null,
                'tipo' => $tipo,
                'cantidad' => $cantidad,
                'motivo' => $motivo === '' ? null : $motivo,
                'monto' => $monto,
            ]);

            $pdo->commit();
            set_flash('success', 'Movimiento registrado.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            set_flash('error', 'No se pudo registrar movimiento: ' . $e->getMessage());
        }

        redirect('/app/movimientos/index.php');
    }

    if ($action === 'delete' || $action === 'update') {
        require_role(['admin']);

        $movId = (int) ($_POST['id'] ?? 0);
        if ($movId <= 0) {
            set_flash('error', 'Movimiento invalido.');
            redirect('/app/movimientos/index.php');
        }

        try {
            $pdo->beginTransaction();

            $stmtMov = $pdo->prepare('SELECT id, producto_id, tipo, cantidad FROM movimientos_stock WHERE id = :id FOR UPDATE');
            $stmtMov->execute(['id' => $movId]);
            $mov = $stmtMov->fetch();

            if (!$mov) {
                throw new RuntimeException('Movimiento no encontrado.');
            }

            $oldProductoId = (int) ($mov['producto_id'] ?? 0);
            $oldTipo = (string) $mov['tipo'];
            $oldCantidad = (int) $mov['cantidad'];

            // Revertir efecto anterior en stock
            apply_stock_delta($pdo, $oldProductoId, $oldTipo, $oldCantidad, true);

            if ($action === 'delete') {
                $stmtDel = $pdo->prepare('DELETE FROM movimientos_stock WHERE id = :id');
                $stmtDel->execute(['id' => $movId]);
                $pdo->commit();
                set_flash('success', 'Movimiento eliminado y stock ajustado.');
                redirect('/app/movimientos/index.php');
            }

            $newProductoId = (int) ($_POST['producto_id'] ?? 0);
            $newTipo = $_POST['tipo'] ?? '';
            $newCantidad = (int) ($_POST['cantidad'] ?? 0);
            $newMotivo = trim($_POST['motivo'] ?? '');
            $newMonto = (float) ($_POST['monto'] ?? 0);
            $newUsaProducto = $newProductoId > 0;

            if (!in_array($newTipo, ['entrada', 'salida', 'ingreso'], true) || $newMonto < 0) {
                throw new RuntimeException('Datos invalidos para editar movimiento.');
            }

            if ($newTipo === 'ingreso') {
                $newUsaProducto = false;
                $newProductoId = 0;
                $newCantidad = 0;
                if ($newMonto <= 0) {
                    throw new RuntimeException('Para ingreso monetario el monto debe ser mayor a cero.');
                }
            }

            if (in_array($newTipo, ['entrada', 'salida'], true) && !$newUsaProducto) {
                throw new RuntimeException('Debes seleccionar un producto para registrar gasto o salida de stock.');
            }

            if (!$newUsaProducto && $newMotivo === '') {
                throw new RuntimeException('Cuando no seleccionas producto, debes indicar un motivo.');
            }

            if ($newUsaProducto && $newCantidad <= 0) {
                throw new RuntimeException('La cantidad debe ser mayor a cero cuando seleccionas un producto.');
            }

            if (!$newUsaProducto) {
                $newCantidad = 0;
            }

            // Aplicar efecto nuevo en stock
            apply_stock_delta($pdo, $newProductoId, $newTipo, $newCantidad, false);

            $stmtUpd = $pdo->prepare(
                'UPDATE movimientos_stock
                 SET producto_id = :producto_id,
                     tipo = :tipo,
                     cantidad = :cantidad,
                     motivo = :motivo,
                     monto = :monto
                 WHERE id = :id'
            );
            $stmtUpd->execute([
                'producto_id' => $newUsaProducto ? $newProductoId : null,
                'tipo' => $newTipo,
                'cantidad' => $newCantidad,
                'motivo' => $newMotivo === '' ? null : $newMotivo,
                'monto' => $newMonto,
                'id' => $movId,
            ]);

            $pdo->commit();
            set_flash('success', 'Movimiento actualizado y stock ajustado.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            set_flash('error', 'No se pudo procesar la accion: ' . $e->getMessage());
        }

        redirect('/app/movimientos/index.php');
    }
}

$stmtProductos = $pdo->query('SELECT id, nombre, stock FROM productos WHERE activo = 1 ORDER BY nombre');
$productos = $stmtProductos->fetchAll();

$stmtMovs = $pdo->query(
    "SELECT m.id, m.fecha, m.tipo, m.cantidad, m.motivo, m.monto, m.producto_id, p.nombre AS producto
     FROM movimientos_stock m
     LEFT JOIN productos p ON p.id = m.producto_id
     ORDER BY m.id DESC
     LIMIT 200"
);
$movimientos = $stmtMovs->fetchAll();

render_header('Gastos y salidas');
?>

<div class="row g-4">
    <div class="col-12 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h4">Registrar gasto o salida</h1>
                <form method="post" class="row g-3">
                    <input type="hidden" name="action" value="create">
                    <div class="col-12">
                        <label class="form-label">Producto</label>
                        <select class="form-select" name="producto_id" id="mov_producto_id" required>
                            <option value="0">Sin producto (movimiento financiero)</option>
                            <?php foreach ($productos as $p): ?>
                                <option value="<?php echo (int) $p['id']; ?>"><?php echo e($p['nombre']); ?> (stock: <?php echo (int) $p['stock']; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Tipo</label>
                        <select class="form-select" name="tipo" id="mov_tipo" required>
                            <option value="entrada">Gasto</option>
                            <option value="salida">Salida</option>
                            <option value="ingreso">Ingreso monetario</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Cantidad</label>
                        <input class="form-control" type="number" min="0" name="cantidad" id="mov_cantidad" value="0" required>
                    </div>
                    <div class="col-12 d-grid">
                        <label class="form-label">Motivo</label>
                        <input class="form-control" name="motivo" placeholder="Ej: compra proveedor, merma, ajuste de inventario">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Monto (Gs)</label>
                        <input class="form-control" type="number" min="0" step="500" name="monto" value="0" required>
                    </div>
                    <div class="col-12 d-grid">
                        <button class="btn btn-primary" type="submit">Guardar movimiento</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-7">
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Fecha</th>
                            <th>Producto</th>
                            <th>Tipo</th>
                            <th>Cantidad</th>
                            <th>Motivo</th>
                            <th>Monto</th>
                            <?php if ($isAdmin): ?>
                                <th class="text-end">Acciones</th>
                            <?php endif; ?>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$movimientos): ?>
                            <tr><td colspan="<?php echo $isAdmin ? '8' : '7'; ?>" class="text-muted">Sin movimientos.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($movimientos as $m): ?>
                            <tr>
                                <td><?php echo (int) $m['id']; ?></td>
                                <td><?php echo e(date('d/m/Y H:i', strtotime($m['fecha']))); ?></td>
                                <td><?php echo e($m['producto'] ?? 'Sin producto'); ?></td>
                                <td>
                                    <?php if ($m['tipo'] === 'entrada'): ?>
                                        <span class="badge text-bg-dark">Gasto</span>
                                    <?php elseif ($m['tipo'] === 'ingreso'): ?>
                                        <span class="badge text-bg-secondary">Ingreso</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-danger">Salida</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo (int) $m['cantidad']; ?></td>
                                <td><?php echo e($m['motivo'] ?? '-'); ?></td>
                                <td><?php echo e(format_gs((float) $m['monto'])); ?></td>
                                <?php if ($isAdmin): ?>
                                    <td class="text-end">
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-primary"
                                            onclick='openEditMovimiento(<?php echo json_encode($m, JSON_UNESCAPED_UNICODE); ?>)'>
                                            Editar
                                        </button>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Eliminar movimiento? Se ajustara el stock.');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo (int) $m['id']; ?>">
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Eliminar</button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($isAdmin): ?>
<div class="modal fade" id="editMovModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" class="row g-0">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="em_id" value="0">
                <div class="modal-header">
                    <h2 class="h5 mb-0">Editar movimiento</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label">Producto</label>
                            <select class="form-select" name="producto_id" id="em_producto_id" required>
                                <option value="0">Sin producto (movimiento financiero)</option>
                                <?php foreach ($productos as $p): ?>
                                    <option value="<?php echo (int) $p['id']; ?>"><?php echo e($p['nombre']); ?> (stock: <?php echo (int) $p['stock']; ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label">Tipo</label>
                            <select class="form-select" name="tipo" id="em_tipo" required>
                                <option value="entrada">Gasto</option>
                                <option value="salida">Salida</option>
                                <option value="ingreso">Ingreso monetario</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label">Cantidad</label>
                            <input class="form-control" type="number" min="0" name="cantidad" id="em_cantidad" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Motivo</label>
                            <input class="form-control" name="motivo" id="em_motivo">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Monto (Gs)</label>
                            <input class="form-control" type="number" min="0" step="500" name="monto" id="em_monto" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let editMovModalInstance = null;
function getEditMovModal() {
    if (!editMovModalInstance) {
        editMovModalInstance = new bootstrap.Modal(document.getElementById('editMovModal'));
    }
    return editMovModalInstance;
}

function openEditMovimiento(m) {
    document.getElementById('em_id').value = m.id || 0;
    document.getElementById('em_producto_id').value = m.producto_id || 0;
    document.getElementById('em_tipo').value = m.tipo || 'entrada';
    document.getElementById('em_cantidad').value = m.cantidad || 0;
    document.getElementById('em_motivo').value = m.motivo || '';
    document.getElementById('em_monto').value = m.monto || 0;
    syncMovimientoForm(document.getElementById('em_tipo'), document.getElementById('em_producto_id'), document.getElementById('em_cantidad'));
    getEditMovModal().show();
}

function syncMovimientoForm(tipoField, productoField, cantidadField) {
    const isIngreso = tipoField.value === 'ingreso';

    productoField.required = !isIngreso;
    cantidadField.min = isIngreso ? '0' : '1';

    if (isIngreso) {
        productoField.value = '0';
        cantidadField.value = '0';
    } else if (productoField.value === '0' && Number(cantidadField.value || 0) <= 0) {
        cantidadField.value = '1';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const tipoField = document.getElementById('mov_tipo');
    const productoField = document.getElementById('mov_producto_id');
    const cantidadField = document.getElementById('mov_cantidad');

    if (tipoField && productoField && cantidadField) {
        const updateCreateForm = function () {
            syncMovimientoForm(tipoField, productoField, cantidadField);
        };

        tipoField.addEventListener('change', updateCreateForm);
        updateCreateForm();
    }

    const editTipoField = document.getElementById('em_tipo');
    const editProductoField = document.getElementById('em_producto_id');
    const editCantidadField = document.getElementById('em_cantidad');

    if (editTipoField && editProductoField && editCantidadField) {
        editTipoField.addEventListener('change', function () {
            syncMovimientoForm(editTipoField, editProductoField, editCantidadField);
        });
    }
});
</script>
<?php endif; ?>

<?php render_footer();
