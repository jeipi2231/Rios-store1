<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$titulo_pagina = 'Reportes';

// Estadísticas generales
$total_productos   = (int)$pdo->query("SELECT COUNT(*) FROM productos")->fetchColumn();
$total_categorias  = (int)$pdo->query("SELECT COUNT(*) FROM categorias")->fetchColumn();
$total_proveedores = (int)$pdo->query("SELECT COUNT(*) FROM proveedores")->fetchColumn();
$stock_total       = (float)$pdo->query("SELECT COALESCE(SUM(stock),0) FROM productos")->fetchColumn();

// Valor de inventario
$valor_compra = (float)$pdo->query("SELECT COALESCE(SUM(stock * precio_compra),0) FROM productos")->fetchColumn();
$valor_venta  = (float)$pdo->query("SELECT COALESCE(SUM(stock * precio_venta),0)  FROM productos")->fetchColumn();
$ganancia_pot = $valor_venta - $valor_compra;

// Productos con stock bajo
$stmt_bajo = $pdo->query("SELECT * FROM productos WHERE stock <= stock_minimo ORDER BY (stock_minimo - stock) DESC LIMIT 5");
$productos_bajo = $stmt_bajo->fetchAll();

$total_bajo = (int)$pdo->query("SELECT COUNT(*) FROM productos WHERE stock <= stock_minimo")->fetchColumn();

// Últimos movimientos
$stmt_mov = $pdo->query("SELECT m.*, p.nombre AS producto, p.codigo
                         FROM movimientos m
                         JOIN productos p ON p.id = m.producto_id
                         ORDER BY m.creado_en DESC LIMIT 8");
$ultimos_mov = $stmt_mov->fetchAll();

// Movimientos del día
$hoy = date('Y-m-d');
$stmt_hoy = $pdo->prepare("SELECT COUNT(*) FROM movimientos WHERE DATE(creado_en) = ?");
$stmt_hoy->execute([$hoy]);
$mov_hoy = (int)$stmt_hoy->fetchColumn();

// Productos más valiosos
$stmt_top = $pdo->query("SELECT nombre, codigo, stock, precio_venta, (stock * precio_venta) AS valor
                         FROM productos ORDER BY valor DESC LIMIT 5");
$top_valor = $stmt_top->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<!-- Alerta de stock bajo -->
<?php if ($total_bajo > 0): ?>
<div class="alert alert-warning alert-top d-flex align-items-center" role="alert">
  <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
  <div>
    <strong><?= $total_bajo ?> producto(s)</strong> tienen stock bajo el mínimo.
    <a href="index.php" class="alert-link ms-2">Ver detalles →</a>
  </div>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h3 class="mb-1">Reportes</h3>
    <p class="text-muted mb-0">Resumen general del inventario</p>
  </div>
  <div class="text-end">
    <span class="badge bg-light text-dark border px-3 py-2">
      <i class="bi bi-calendar3 me-1"></i><?= date('d/m/Y') ?>
    </span>
  </div>
</div>

<!-- Tarjetas de estadísticas -->
<div class="row g-3 mb-4">
  <div class="col-md-3 col-sm-6">
    <div class="card stat-card h-100">
      <div class="card-body d-flex justify-content-between align-items-center">
        <div>
          <div class="text-muted small">Productos</div>
          <div class="h3 mb-0"><?= fmt_int($total_productos) ?></div>
        </div>
        <i class="bi bi-box-seam stat-icon text-primary"></i>
      </div>
    </div>
  </div>
  <div class="col-md-3 col-sm-6">
    <div class="card stat-card success h-100">
      <div class="card-body d-flex justify-content-between align-items-center">
        <div>
          <div class="text-muted small">Stock total (unidades)</div>
          <div class="h3 mb-0"><?= fmt_qty($stock_total) ?></div>
        </div>
        <i class="bi bi-clipboard-check stat-icon text-success"></i>
      </div>
    </div>
  </div>
  <div class="col-md-3 col-sm-6">
    <div class="card stat-card info h-100">
      <div class="card-body d-flex justify-content-between align-items-center">
        <div>
          <div class="text-muted small">Categorías</div>
          <div class="h3 mb-0"><?= fmt_int($total_categorias) ?></div>
        </div>
        <i class="bi bi-tags stat-icon text-info"></i>
      </div>
    </div>
  </div>
  <div class="col-md-3 col-sm-6">
    <div class="card stat-card warning h-100">
      <div class="card-body d-flex justify-content-between align-items-center">
        <div>
          <div class="text-muted small">Proveedores</div>
          <div class="h3 mb-0"><?= fmt_int($total_proveedores) ?></div>
        </div>
        <i class="bi bi-truck stat-icon text-warning"></i>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="card stat-card h-100">
      <div class="card-body">
        <div class="text-muted small">Valor de inventario (compra)</div>
        <div class="h4 mb-0 text-primary"><?= money($valor_compra) ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card stat-card success h-100">
      <div class="card-body">
        <div class="text-muted small">Valor de venta potencial</div>
        <div class="h4 mb-0 text-success"><?= money($valor_venta) ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card stat-card danger h-100">
      <div class="card-body">
        <div class="text-muted small">Ganancia potencial</div>
        <div class="h4 mb-0 text-danger"><?= money($ganancia_pot) ?></div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <!-- Últimos movimientos -->
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clock-history me-2"></i>Últimos movimientos</span>
        <span class="badge bg-secondary"><?= fmt_int($mov_hoy) ?> hoy</span>
      </div>
      <div class="card-body p-0">
        <?php if (empty($ultimos_mov)): ?>
          <div class="empty-state"><i class="bi bi-inbox d-block mb-2"></i>Sin movimientos registrados</div>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr><th>Producto</th><th>Tipo</th><th class="text-end">Cantidad</th><th>Motivo</th><th>Fecha</th></tr>
            </thead>
            <tbody>
              <?php foreach ($ultimos_mov as $m): ?>
              <tr>
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
                <td><small class="text-muted"><?= date('d/m H:i', strtotime($m['creado_en'])) ?></small></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
      <div class="card-footer text-end">
        <a href="movements.php" class="btn btn-sm btn-outline-primary">Ver todos →</a>
      </div>
    </div>
  </div>

  <!-- Productos con stock bajo -->
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-exclamation-triangle me-2"></i>Stock bajo</span>
        <span class="badge bg-warning text-dark"><?= $total_bajo ?></span>
      </div>
      <div class="card-body p-0">
        <?php if (empty($productos_bajo)): ?>
          <div class="empty-state"><i class="bi bi-check-circle d-block mb-2 text-success"></i>Todos los productos tienen stock suficiente</div>
        <?php else: ?>
        <div class="list-group list-group-flush">
          <?php foreach ($productos_bajo as $p): ?>
            <?php
              $clase = $p['stock'] <= 0 ? 'bg-danger' : 'bg-warning';
              $txt   = $p['stock'] <= 0 ? 'text-white' : 'text-dark';
            ?>
            <div class="list-group-item d-flex justify-content-between align-items-center">
              <div>
                <div class="fw-semibold"><?= e($p['nombre']) ?></div>
                <small class="text-muted"><?= e($p['codigo']) ?> · Mínimo: <?= fmt_qty($p['stock_minimo']) ?></small>
              </div>
              <span class="badge <?= $clase . ' ' . $txt ?> fs-6"><?= fmt_qty($p['stock']) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <div class="card-footer text-end">
        <a href="index.php" class="btn btn-sm btn-outline-warning">Ver reportes →</a>
      </div>
    </div>
  </div>
</div>

<!-- Top productos por valor -->
<div class="row mt-3">
  <div class="col-12">
    <div class="card">
      <div class="card-header"><i class="bi bi-trophy me-2"></i>Top 5 productos por valor de inventario</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr><th>#</th><th>Código</th><th>Producto</th><th class="text-end">Stock</th><th class="text-end">Precio venta</th><th class="text-end">Valor total</th></tr>
            </thead>
            <tbody>
              <?php foreach ($top_valor as $i => $p): ?>
              <tr>
                <td><?= $i + 1 ?></td>
                <td><code><?= e($p['codigo']) ?></code></td>
                <td><?= e($p['nombre']) ?></td>
                <td class="text-end"><?= fmt_qty($p['stock']) ?></td>
                <td class="text-end"><?= money($p['precio_venta']) ?></td>
                <td class="text-end fw-bold text-success"><?= money($p['valor']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
