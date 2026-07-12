<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$titulo_pagina = 'Productos';

$mensaje = '';

// Acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $mensaje = '<div class="alert alert-danger">Token CSRF inválido.</div>';
    } else {
        $accion = $_POST['accion'] ?? '';
        if ($accion === 'guardar') {
            $id           = (int)($_POST['id'] ?? 0);
            $codigo       = trim($_POST['codigo'] ?? '');
          $codigo_barras = trim($_POST['codigo_barras'] ?? '');
            $nombre       = trim($_POST['nombre'] ?? '');
            $descripcion  = trim($_POST['descripcion'] ?? '');
            $categoria_id = (int)($_POST['categoria_id'] ?? 0) ?: null;
            $proveedor_id = (int)($_POST['proveedor_id'] ?? 0) ?: null;
            $precio_compra = (float)($_POST['precio_compra'] ?? 0);
            $precio_venta  = (float)($_POST['precio_venta'] ?? 0);
            $stock         = (float)($_POST['stock'] ?? 0);
            $stock_minimo  = (float)($_POST['stock_minimo'] ?? 5);
          $es_pesable    = isset($_POST['es_pesable']) ? 1 : 0;
          $prefijo_balanza = trim($_POST['prefijo_balanza'] ?? '');
            $unidad        = trim($_POST['unidad'] ?? 'unidad');
            $unidad_custom = trim($_POST['unidad_custom'] ?? '');

          if ($codigo_barras === '') { $codigo_barras = null; }
          if ($prefijo_balanza === '') { $prefijo_balanza = null; }
          if ($unidad === 'otro') {
            $unidad = $unidad_custom !== '' ? $unidad_custom : 'unidad';
          }
          if ($es_pesable === 1 && $unidad === 'unidad') {
            $unidad = 'kg';
          }
          if ($es_pesable === 0) {
            $stock = (float)(int)round($stock);
            $stock_minimo = (float)(int)round($stock_minimo);
            $prefijo_balanza = null;
          }
          if ($es_pesable === 1 && ($prefijo_balanza === null || !preg_match('/^\d{7}$/', $prefijo_balanza))) {
            $mensaje = '<div class="alert alert-danger">Para productos de balanza debes ingresar un prefijo numérico de 7 dígitos.</div>';
          }

          if ($mensaje === '' && ($codigo === '' || $nombre === '')) {
                $mensaje = '<div class="alert alert-danger">Código y nombre son obligatorios.</div>';
          } elseif ($mensaje === '') {
                try {
                    if ($id > 0) {
                        $stmt = $pdo->prepare(
                  "UPDATE productos SET codigo=?, codigo_barras=?, nombre=?, descripcion=?, categoria_id=?,
                   proveedor_id=?, precio_compra=?, precio_venta=?, stock=?, stock_minimo=?,
                   es_pesable=?, prefijo_balanza=?, unidad=?
                             WHERE id=?"
                        );
                $stmt->execute([$codigo,$codigo_barras,$nombre,$descripcion,$categoria_id,$proveedor_id,
                        $precio_compra,$precio_venta,$stock,$stock_minimo,$es_pesable,
                        $prefijo_balanza,$unidad,$id]);
                        $mensaje = '<div class="alert alert-success">Producto actualizado.</div>';
                    } else {
                        $stmt = $pdo->prepare(
                  "INSERT INTO productos (codigo,codigo_barras,nombre,descripcion,categoria_id,proveedor_id,
                   precio_compra,precio_venta,stock,stock_minimo,es_pesable,prefijo_balanza,unidad)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"
                        );
                $stmt->execute([$codigo,$codigo_barras,$nombre,$descripcion,$categoria_id,$proveedor_id,
                        $precio_compra,$precio_venta,$stock,$stock_minimo,$es_pesable,
                        $prefijo_balanza,$unidad]);
                        $mensaje = '<div class="alert alert-success">Producto creado.</div>';
                    }
                } catch (PDOException $ex) {
                    if ($ex->getCode() === '23000') {
                        $mensaje = '<div class="alert alert-danger">El código ya existe.</div>';
                    } else {
                        $mensaje = '<div class="alert alert-danger">Error: ' . e($ex->getMessage()) . '</div>';
                    }
                }
            }
        } elseif ($accion === 'eliminar') {
            $id = (int)($_POST['id'] ?? 0);
            $pdo->prepare("DELETE FROM productos WHERE id=?")->execute([$id]);
            $mensaje = '<div class="alert alert-success">Producto eliminado.</div>';
        }
    }
}

// Filtros de búsqueda
$q = trim($_GET['q'] ?? '');
$cat = (int)($_GET['cat'] ?? 0);
$where = [];
$params = [];
if ($q !== '') {
  $where[] = "(p.codigo LIKE ? OR p.codigo_barras LIKE ? OR p.nombre LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
  $params[] = "%$q%";
}
if ($cat > 0) {
    $where[] = "p.categoria_id = ?";
    $params[] = $cat;
}
$sql_where = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "SELECT p.*, c.nombre AS categoria, pr.nombre AS proveedor
        FROM productos p
        LEFT JOIN categorias c  ON c.id = p.categoria_id
        LEFT JOIN proveedores pr ON pr.id = p.proveedor_id
        $sql_where
        ORDER BY p.nombre ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll();

// Categorías y proveedores para los selects
$cats = $pdo->query("SELECT id, nombre FROM categorias ORDER BY nombre")->fetchAll();
$provs = $pdo->query("SELECT id, nombre FROM proveedores ORDER BY nombre")->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h3 class="mb-1"><i class="bi bi-box me-2"></i>Productos</h3>
    <p class="text-muted mb-0"><?= count($productos) ?> registro(s)</p>
  </div>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalProducto" onclick="abrirCrear()">
    <i class="bi bi-plus-lg me-1"></i>Nuevo producto
  </button>
</div>

<?= $mensaje ?>

<div class="alert alert-light border mb-3">
  <strong>Guía rápida para productos:</strong>
  <span class="d-block">`Código`: identificador interno. `Código de barras`: para productos normales (góndola).</span>
  <span class="d-block">Activa <em>Producto de balanza</em> solo para carnes/pesables; allí se habilita el prefijo de 7 dígitos.</span>
</div>

<form method="get" class="card mb-3">
  <div class="card-body">
    <div class="row g-2 align-items-end">
      <div class="col-md-6">
        <label class="form-label small">Buscar</label>
        <input type="text" name="q" class="form-control" placeholder="Código o nombre..."
               value="<?= e($q) ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label small">Categoría</label>
        <select name="cat" class="form-select">
          <option value="0">Todas</option>
          <?php foreach ($cats as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $cat===$c['id']?'selected':'' ?>><?= e($c['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2 d-grid">
        <button class="btn btn-outline-primary"><i class="bi bi-search me-1"></i>Filtrar</button>
      </div>
    </div>
  </div>
</form>

<div class="card">
  <div class="card-body p-0">
    <?php if (empty($productos)): ?>
      <div class="empty-state">
        <i class="bi bi-inbox d-block mb-2"></i>
        No hay productos. Crea el primero con el botón <strong>"Nuevo producto"</strong>.
      </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead>
          <tr>
            <th>Código</th><th>Producto</th><th>Tipo</th><th>Categoría</th><th>Proveedor</th>
            <th class="text-end">P. Compra</th><th class="text-end">P. Venta</th>
            <th class="text-center">Stock</th><th class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($productos as $p):
                 $stockVista = ((int)($p['es_pesable'] ?? 0) === 1) ? fmt_qty($p['stock']) : fmt_int($p['stock']);
                 $minVista = ((int)($p['es_pesable'] ?? 0) === 1) ? fmt_qty($p['stock_minimo']) : fmt_int($p['stock_minimo']);
                 $clase = $p['stock'] <= 0 ? 'bg-danger text-white'
                   : ($p['stock'] <= $p['stock_minimo'] ? 'bg-warning text-dark'
                   : 'bg-success');
          ?>
          <tr>
            <td>
              <code><?= e($p['codigo']) ?></code>
              <?php if (!empty($p['codigo_barras'])): ?>
                <small class="d-block text-muted">EAN: <?= e($p['codigo_barras']) ?></small>
              <?php endif; ?>
            </td>
            <td>
              <?= e($p['nombre']) ?>
              <small class="d-block text-muted">
                <?= e($p['unidad']) ?> · mín. <?= $minVista ?>
                <?php if ((int)($p['es_pesable'] ?? 0) === 1): ?>
                  · balanza <?= e($p['prefijo_balanza'] ?: '-') ?>
                <?php endif; ?>
              </small>
            </td>
            <td>
              <?php if ((int)($p['es_pesable'] ?? 0) === 1): ?>
                <span class="badge text-bg-warning">Pesable (kg)</span>
              <?php else: ?>
                <span class="badge text-bg-primary">Normal</span>
              <?php endif; ?>
            </td>
            <td><?= e($p['categoria'] ?: '-') ?></td>
            <td><?= e($p['proveedor'] ?: '-') ?></td>
            <td class="text-end"><?= money($p['precio_compra']) ?></td>
            <td class="text-end"><?= money($p['precio_venta']) ?></td>
            <td class="text-center"><span class="badge <?= $clase ?> fs-6"><?= $stockVista ?></span></td>
            <td class="text-end">
              <button class="btn btn-sm btn-outline-primary btn-icon"
                      onclick='editar(<?= json_encode($p, JSON_UNESCAPED_UNICODE) ?>)'>
                <i class="bi bi-pencil"></i>
              </button>
              <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar este producto?')">
                <?= csrf_field() ?>
                <input type="hidden" name="accion" value="eliminar">
                <input type="hidden" name="id" value="<?= $p['id'] ?>">
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

<!-- Modal crear/editar -->
<div class="modal fade" id="modalProducto" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="post" id="formProducto">
        <?= csrf_field() ?>
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" id="f_id">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-box me-2"></i><span id="modalTitulo">Nuevo producto</span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Código *</label>
              <input type="text" name="codigo" id="f_codigo" class="form-control" required>
              <small class="text-muted">Código interno del producto.</small>
            </div>
            <div class="col-md-4">
              <label class="form-label">Código de barras</label>
              <input type="text" name="codigo_barras" id="f_codigo_barras" class="form-control" placeholder="EAN-13 / interno">
              <small class="text-muted">Código normal de scanner (no balanza).</small>
            </div>
            <div class="col-md-4">
              <label class="form-label">Nombre *</label>
              <input type="text" name="nombre" id="f_nombre" class="form-control" required>
            </div>
            <div class="col-12">
              <label class="form-label">Descripción</label>
              <textarea name="descripcion" id="f_descripcion" class="form-control" rows="2"></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label">Categoría</label>
              <select name="categoria_id" id="f_categoria" class="form-select">
                <option value="0">— Sin categoría —</option>
                <?php foreach ($cats as $c): ?>
                  <option value="<?= $c['id'] ?>"><?= e($c['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Proveedor</label>
              <select name="proveedor_id" id="f_proveedor" class="form-select">
                <option value="0">— Sin proveedor —</option>
                <?php foreach ($provs as $p): ?>
                  <option value="<?= $p['id'] ?>"><?= e($p['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Precio compra</label>
              <input type="number" step="0.01" name="precio_compra" id="f_pcompra" class="form-control" value="0">
            </div>
            <div class="col-md-4">
              <label class="form-label">Precio venta</label>
              <input type="number" step="0.01" name="precio_venta" id="f_pventa" class="form-control" value="0">
            </div>
            <div class="col-md-4">
              <label class="form-label">Unidad</label>
              <select name="unidad" id="f_unidad" class="form-select" onchange="toggleUnidadCustom()">
                <option value="unidad">Unidad</option>
                <option value="kg">Kilogramo (kg)</option>
                <option value="g">Gramo (g)</option>
                <option value="l">Litro (l)</option>
                <option value="ml">Mililitro (ml)</option>
                <option value="paquete">Paquete</option>
                <option value="caja">Caja</option>
                <option value="botella">Botella</option>
                <option value="bandeja">Bandeja</option>
                <option value="otro">Otro...</option>
              </select>
            </div>
            <div class="col-md-4" id="unidad_custom_wrap" style="display:none;">
              <label class="form-label">Unidad personalizada</label>
              <input type="text" name="unidad_custom" id="f_unidad_custom" class="form-control" placeholder="Ej.: media res, bolsa, tira">
            </div>
            <div class="col-md-4" id="prefijo_balanza_wrap" style="display:none;">
              <label class="form-label">Prefijo balanza (7 dígitos)</label>
              <input type="text" name="prefijo_balanza" id="f_prefijo_balanza" class="form-control" maxlength="7" pattern="\d{7}" placeholder="Ej.: 2501234">
              <small class="text-muted">Solo para pesables. Se compara con los primeros 7 dígitos de la etiqueta.</small>
            </div>
            <div class="col-md-4 d-flex align-items-end">
              <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" name="es_pesable" id="f_es_pesable" value="1">
                <label class="form-check-label" for="f_es_pesable">Producto de balanza</label>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label" id="lbl_stock">Stock actual (unidades)</label>
              <input type="number" step="0.001" name="stock" id="f_stock" class="form-control" value="0">
            </div>
            <div class="col-md-6">
              <label class="form-label" id="lbl_sminimo">Stock mínimo (unidades)</label>
              <input type="number" step="0.001" name="stock_minimo" id="f_sminimo" class="form-control" value="5">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function abrirCrear() {
  document.getElementById('modalTitulo').textContent = 'Nuevo producto';
  document.getElementById('formProducto').reset();
  document.getElementById('f_id').value = '';
  document.getElementById('f_unidad').value = 'unidad';
  document.getElementById('f_unidad_custom').value = '';
  document.getElementById('f_codigo_barras').value = '';
  document.getElementById('f_prefijo_balanza').value = '';
  document.getElementById('f_es_pesable').checked = false;
  document.getElementById('f_pcompra').value = 0;
  document.getElementById('f_pventa').value = 0;
  document.getElementById('f_stock').value = 0;
  document.getElementById('f_sminimo').value = 5;
  toggleUnidadCustom();
  togglePesableStockMode();
}
function editar(p) {
  const unidadesPredef = ['unidad', 'kg', 'g', 'l', 'ml', 'paquete', 'caja', 'botella', 'bandeja'];
  document.getElementById('modalTitulo').textContent = 'Editar producto';
  document.getElementById('f_id').value          = p.id;
  document.getElementById('f_codigo').value       = p.codigo;
  document.getElementById('f_codigo_barras').value = p.codigo_barras || '';
  document.getElementById('f_nombre').value       = p.nombre;
  document.getElementById('f_descripcion').value  = p.descripcion || '';
  document.getElementById('f_categoria').value    = p.categoria_id || 0;
  document.getElementById('f_proveedor').value    = p.proveedor_id || 0;
  document.getElementById('f_pcompra').value      = p.precio_compra;
  document.getElementById('f_pventa').value       = p.precio_venta;
  if (unidadesPredef.includes((p.unidad || '').toLowerCase())) {
    document.getElementById('f_unidad').value = (p.unidad || 'unidad').toLowerCase();
    document.getElementById('f_unidad_custom').value = '';
  } else {
    document.getElementById('f_unidad').value = 'otro';
    document.getElementById('f_unidad_custom').value = p.unidad || '';
  }
  document.getElementById('f_prefijo_balanza').value = p.prefijo_balanza || '';
  document.getElementById('f_es_pesable').checked = Number(p.es_pesable || 0) === 1;
  document.getElementById('f_stock').value        = p.stock;
  document.getElementById('f_sminimo').value      = p.stock_minimo;
  toggleUnidadCustom();
  togglePesableStockMode();
  new bootstrap.Modal(document.getElementById('modalProducto')).show();
}

function toggleUnidadCustom() {
  const esOtro = document.getElementById('f_unidad').value === 'otro';
  document.getElementById('unidad_custom_wrap').style.display = esOtro ? '' : 'none';
  if (!esOtro) {
    document.getElementById('f_unidad_custom').value = '';
  }
}

document.getElementById('f_es_pesable').addEventListener('change', function () {
  if (this.checked && document.getElementById('f_unidad').value === 'unidad') {
    document.getElementById('f_unidad').value = 'kg';
    toggleUnidadCustom();
  }
  togglePesableStockMode();
});

function togglePesableStockMode() {
  const esPesable = document.getElementById('f_es_pesable').checked;
  const stock = document.getElementById('f_stock');
  const smin = document.getElementById('f_sminimo');
  const lblStock = document.getElementById('lbl_stock');
  const lblSmin = document.getElementById('lbl_sminimo');
  const prefijoWrap = document.getElementById('prefijo_balanza_wrap');
  const prefijoInput = document.getElementById('f_prefijo_balanza');

  if (esPesable) {
    stock.step = '0.001';
    stock.min = '0';
    smin.step = '0.001';
    smin.min = '0';
    lblStock.textContent = 'Stock actual (kg)';
    lblSmin.textContent = 'Stock mínimo (kg)';
    prefijoWrap.style.display = '';
    prefijoInput.required = true;
  } else {
    stock.step = '1';
    stock.min = '0';
    smin.step = '1';
    smin.min = '0';
    stock.value = String(Math.round(Number(stock.value || 0)));
    smin.value = String(Math.round(Number(smin.value || 0)));
    lblStock.textContent = 'Stock actual (unidades)';
    lblSmin.textContent = 'Stock mínimo (unidades)';
    prefijoWrap.style.display = 'none';
    prefijoInput.required = false;
    prefijoInput.value = '';
  }
}
</script>
<script src="assets/js/app.js"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
