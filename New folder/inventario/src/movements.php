<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$titulo_pagina = 'Movimientos';
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $mensaje = '<div class="alert alert-danger">Token CSRF inválido.</div>';
    } else {
        $accion = $_POST['accion'] ?? '';
        if ($accion === 'registrar') {
            $producto_id = (int)($_POST['producto_id'] ?? 0);
            $tipo        = $_POST['tipo'] ?? '';
          $cantidad    = (float)($_POST['cantidad'] ?? 0);
            $motivo      = trim($_POST['motivo'] ?? '');

            if ($producto_id <= 0 || !in_array($tipo, ['entrada','salida'], true) || $cantidad <= 0) {
                $mensaje = '<div class="alert alert-danger">Datos inválidos. Verifica producto, tipo y cantidad.</div>';
            } else {
                try {
                    $pdo->beginTransaction();

                    // Bloquear fila del producto
                    $stmt = $pdo->prepare("SELECT stock, unidad, es_pesable FROM productos WHERE id=? FOR UPDATE");
                    $stmt->execute([$producto_id]);
                    $prod = $stmt->fetch();

                    if (!$prod) {
                        throw new RuntimeException('El producto no existe.');
                    }

                    // Productos normales se mueven en enteros; pesables permiten decimales.
                    if ((int)$prod['es_pesable'] !== 1) {
                      $cantidad = (float)(int)round($cantidad);
                    }
                    if ($cantidad <= 0) {
                      throw new RuntimeException('La cantidad debe ser mayor a cero.');
                    }

                    $unidad_mov = (string)($prod['unidad'] ?: 'unidad');

                    $nuevo_stock = $prod['stock'];
                    if ($tipo === 'entrada') {
                        $nuevo_stock += $cantidad;
                    } else {
                        if ($cantidad > $prod['stock']) {
                            throw new RuntimeException('No hay stock suficiente para registrar la salida. Stock actual: ' . $prod['stock']);
                        }
                        $nuevo_stock -= $cantidad;
                    }

                    // Actualizar stock
                    $pdo->prepare("UPDATE productos SET stock=? WHERE id=?")
                        ->execute([$nuevo_stock, $producto_id]);

                    // Registrar movimiento
                    $pdo->prepare(
                        "INSERT INTO movimientos (producto_id, tipo, cantidad, motivo, usuario_id)
                         VALUES (?,?,?,?,?)"
                    )->execute([
                        $producto_id, $tipo, $cantidad, $motivo, current_user()['id'] ?? null
                    ]);

                    $pdo->commit();
                    $mensaje = '<div class="alert alert-success">
                        <i class="bi bi-check-circle me-1"></i>' . ucfirst($tipo) . ' registrada: '
                      . fmt_qty($cantidad) . ' ' . e($unidad_mov) . '. Nuevo stock: <strong>' . fmt_qty($nuevo_stock) . ' ' . e($unidad_mov) . '</strong>.
                    </div>';
                } catch (Throwable $ex) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $mensaje = '<div class="alert alert-danger">Error: ' . e($ex->getMessage()) . '</div>';
                }
            }
        } elseif ($accion === 'eliminar') {
            $id = (int)($_POST['id'] ?? 0);
            try {
                $pdo->beginTransaction();

                // Obtener datos del movimiento a eliminar
                $stmt = $pdo->prepare("SELECT * FROM movimientos WHERE id=?");
                $stmt->execute([$id]);
                $mov = $stmt->fetch();

                if ($mov) {
                    // Revertir stock
                    $delta = $mov['tipo'] === 'entrada' ? -$mov['cantidad'] : +$mov['cantidad'];
                    $pdo->prepare("UPDATE productos SET stock = stock + ? WHERE id=?")
                        ->execute([$delta, $mov['producto_id']]);
                    // Eliminar movimiento
                    $pdo->prepare("DELETE FROM movimientos WHERE id=?")->execute([$id]);
                }

                $pdo->commit();
                $mensaje = '<div class="alert alert-success">Movimiento eliminado. Stock revertido.</div>';
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $mensaje = '<div class="alert alert-danger">Error: ' . e($ex->getMessage()) . '</div>';
            }
        }
    }
}

// Filtros
$f_tipo   = $_GET['tipo'] ?? '';
$f_prod   = (int)($_GET['prod'] ?? 0);
$f_desde  = $_GET['desde'] ?? '';
$f_hasta  = $_GET['hasta'] ?? '';

$where = [];
$params = [];
if (in_array($f_tipo, ['entrada','salida'], true)) {
    $where[] = "m.tipo = ?";
    $params[] = $f_tipo;
}
if ($f_prod > 0) {
    $where[] = "m.producto_id = ?";
    $params[] = $f_prod;
}
if ($f_desde !== '') {
    $where[] = "DATE(m.creado_en) >= ?";
    $params[] = $f_desde;
}
if ($f_hasta !== '') {
    $where[] = "DATE(m.creado_en) <= ?";
    $params[] = $f_hasta;
}
$sql_where = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "SELECT m.*, p.codigo, p.nombre AS producto, u.nombre AS usuario
        FROM movimientos m
        JOIN productos p ON p.id = m.producto_id
        LEFT JOIN usuarios u ON u.id = m.usuario_id
        $sql_where
        ORDER BY m.creado_en DESC
        LIMIT 500";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$movimientos = $stmt->fetchAll();

// Resumen financiero (respetando filtros de fecha/producto cuando aplica)
$aggSql = "SELECT
       COALESCE(SUM(CASE WHEN m.tipo = 'entrada' THEN m.cantidad ELSE 0 END), 0) AS qty_entrada,
       COALESCE(SUM(CASE WHEN m.tipo = 'salida' THEN m.cantidad ELSE 0 END), 0) AS qty_salida,
       COALESCE(SUM(CASE WHEN m.tipo = 'entrada' THEN m.cantidad * p.precio_compra ELSE 0 END), 0) AS gasto_estimado,
       COALESCE(SUM(CASE WHEN m.tipo = 'salida' THEN m.cantidad * p.precio_venta ELSE 0 END), 0) AS salida_valorizada
       FROM movimientos m
       JOIN productos p ON p.id = m.producto_id
       $sql_where";
$stmtAgg = $pdo->prepare($aggSql);
$stmtAgg->execute($params);
$resumenMov = $stmtAgg->fetch() ?: [
  'qty_entrada' => 0,
  'qty_salida' => 0,
  'gasto_estimado' => 0,
  'salida_valorizada' => 0,
];

$ventasCantidad = 0;
$ventasTotal = 0.0;

if ($f_tipo !== 'entrada') {
  $ventasWhere = [];
  $ventasParams = [];

  if ($f_desde !== '') {
    $ventasWhere[] = "DATE(v.fecha) >= ?";
    $ventasParams[] = $f_desde;
  }
  if ($f_hasta !== '') {
    $ventasWhere[] = "DATE(v.fecha) <= ?";
    $ventasParams[] = $f_hasta;
  }
  $sqlWhereVentas = $ventasWhere ? ('WHERE ' . implode(' AND ', $ventasWhere)) : '';

  if ($f_prod > 0) {
    $sqlVentas = "SELECT
            COALESCE(SUM(d.subtotal), 0) AS total_vendido,
            COUNT(DISTINCT v.id) AS ventas
            FROM ventas v
            JOIN detalle_ventas d ON d.venta_id = v.id
            $sqlWhereVentas" . ($sqlWhereVentas ? " AND d.producto_id = ?" : " WHERE d.producto_id = ?");
    $ventasParams[] = $f_prod;
  } else {
    $sqlVentas = "SELECT
            COALESCE(SUM(v.total), 0) AS total_vendido,
            COUNT(*) AS ventas
            FROM ventas v
            $sqlWhereVentas";
  }

  $stmtVentas = $pdo->prepare($sqlVentas);
  $stmtVentas->execute($ventasParams);
  $ventas = $stmtVentas->fetch();
  $ventasCantidad = (int)($ventas['ventas'] ?? 0);
  $ventasTotal = (float)($ventas['total_vendido'] ?? 0);
}

$gastoTotal = (float)($resumenMov['gasto_estimado'] ?? 0);
$neto = $ventasTotal - $gastoTotal;

$productos_lista = $pdo->query("SELECT id, codigo, nombre, stock, unidad, es_pesable FROM productos ORDER BY nombre")->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h3 class="mb-1"><i class="bi bi-arrow-left-right me-2"></i>Movimientos de inventario</h3>
    <p class="text-muted mb-0">Entradas y salidas de stock · <?= count($movimientos) ?> registro(s)</p>
  </div>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalMov" onclick="abrirCrear()">
    <i class="bi bi-plus-lg me-1"></i>Registrar movimiento
  </button>
</div>

<?= $mensaje ?>

<!-- Filtros -->
<form method="get" class="card mb-3">
  <div class="card-body">
    <div class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label small">Tipo</label>
        <select name="tipo" class="form-select">
          <option value="">Todos</option>
          <option value="entrada" <?= $f_tipo==='entrada'?'selected':'' ?>>Entradas</option>
          <option value="salida"  <?= $f_tipo==='salida'?'selected':'' ?>>Salidas</option>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label small">Producto</label>
        <select name="prod" class="form-select">
          <option value="0">Todos</option>
          <?php foreach ($productos_lista as $p): ?>
            <option value="<?= $p['id'] ?>" <?= $f_prod===$p['id']?'selected':'' ?>><?= e($p['codigo']) ?> · <?= e($p['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small">Desde</label>
        <input type="date" name="desde" class="form-control" value="<?= e($f_desde) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label small">Hasta</label>
        <input type="date" name="hasta" class="form-control" value="<?= e($f_hasta) ?>">
      </div>
      <div class="col-md-1 d-grid">
        <button class="btn btn-outline-primary"><i class="bi bi-funnel"></i></button>
      </div>
    </div>
  </div>
</form>

<div class="row g-3 mb-3">
  <div class="col-12 col-md-4">
    <div class="card h-100">
      <div class="card-body">
        <div class="text-muted small">Vendido en caja (tickets)</div>
        <div class="h4 mb-0 text-success"><?= money($ventasTotal) ?></div>
        <small class="text-muted"><?= fmt_int($ventasCantidad) ?> venta(s)</small>
      </div>
    </div>
  </div>
  <div class="col-12 col-md-4">
    <div class="card h-100">
      <div class="card-body">
        <div class="text-muted small">Gasto estimado por entradas</div>
        <div class="h4 mb-0 text-danger"><?= money($gastoTotal) ?></div>
        <small class="text-muted">Basado en precio de compra actual.</small>
      </div>
    </div>
  </div>
  <div class="col-12 col-md-4">
    <div class="card h-100">
      <div class="card-body">
        <div class="text-muted small">Balance neto</div>
        <div class="h4 mb-0 <?= $neto >= 0 ? 'text-success' : 'text-danger' ?>"><?= money($neto) ?></div>
        <small class="text-muted">Vendido - gasto.</small>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <?php if (empty($movimientos)): ?>
      <div class="empty-state"><i class="bi bi-inbox d-block mb-2"></i>Sin movimientos en este filtro.</div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead>
          <tr><th>Fecha</th><th>Producto</th><th>Tipo</th><th class="text-end">Cantidad</th><th>Motivo</th><th>Usuario</th><th class="text-end">Acción</th></tr>
        </thead>
        <tbody>
          <?php foreach ($movimientos as $m): ?>
          <tr>
            <td><small><?= date('d/m/Y H:i', strtotime($m['creado_en'])) ?></small></td>
            <td>
              <small class="text-muted"><?= e($m['codigo']) ?></small><br>
              <?= e($m['producto']) ?>
            </td>
            <td>
              <?php if ($m['tipo'] === 'entrada'): ?>
                <span class="badge bg-success"><i class="bi bi-arrow-down-left"></i> Entrada</span>
              <?php else: ?>
                <span class="badge bg-danger"><i class="bi bi-arrow-up-right"></i> Salida</span>
              <?php endif; ?>
            </td>
            <td class="text-end fw-bold"><?= fmt_qty($m['cantidad']) ?></td>
            <td><?= e($m['motivo'] ?: '-') ?></td>
            <td><small><?= e($m['usuario'] ?: 'Sistema') ?></small></td>
            <td class="text-end">
              <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar movimiento? El stock se revertirá.')">
                <?= csrf_field() ?>
                <input type="hidden" name="accion" value="eliminar">
                <input type="hidden" name="id" value="<?= $m['id'] ?>">
                <button class="btn btn-sm btn-outline-danger btn-icon"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Modal registrar -->
<div class="modal fade" id="modalMov" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" id="formMov">
        <?= csrf_field() ?>
        <input type="hidden" name="accion" value="registrar">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-arrow-left-right me-2"></i>Registrar movimiento</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Producto *</label>
            <select name="producto_id" id="f_producto" class="form-select" required>
              <option value="">— Selecciona —</option>
              <?php foreach ($productos_lista as $p): ?>
                <option value="<?= $p['id'] ?>" data-stock="<?= $p['stock'] ?? '' ?>" data-unidad="<?= e($p['unidad'] ?? 'unidad') ?>" data-pesable="<?= (int)($p['es_pesable'] ?? 0) ?>">
                  <?= e($p['codigo']) ?> · <?= e($p['nombre']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <small class="text-muted" id="stockActual"></small>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Tipo *</label>
              <select name="tipo" id="f_tipo" class="form-select" required>
                <option value="entrada">Entrada (+)</option>
                <option value="salida">Salida (−)</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Cantidad *</label>
              <input type="number" name="cantidad" id="f_cantidad" class="form-control" min="0.001" step="0.001" value="1" required>
            </div>
          </div>
          <div class="mt-3">
            <label class="form-label">Motivo / Observación</label>
            <input type="text" name="motivo" class="form-control"
                   placeholder="Ej.: Compra a proveedor, venta, merma, ajuste...">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Registrar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function abrirCrear() {
  document.getElementById('formMov').reset();
  document.getElementById('f_cantidad').value = 1;
  document.getElementById('f_cantidad').step = '1';
  document.getElementById('stockActual').textContent = '';
}
document.getElementById('f_producto').addEventListener('change', function () {
  const opt = this.options[this.selectedIndex];
  const stock = opt.dataset.stock;
  const unidad = opt.dataset.unidad || 'unidad';
  const pesable = Number(opt.dataset.pesable || 0) === 1;
  const inputCantidad = document.getElementById('f_cantidad');
  inputCantidad.step = pesable ? '0.001' : '1';
  inputCantidad.value = pesable ? '0.500' : '1';
  document.getElementById('stockActual').textContent = stock !== '' ? 'Stock actual: ' + stock + ' ' + unidad : '';
});
</script>
<script src="assets/js/app.js"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
