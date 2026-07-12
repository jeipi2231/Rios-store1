<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$titulo_pagina = 'Categorías';
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $mensaje = '<div class="alert alert-danger">Token CSRF inválido.</div>';
    } else {
        $accion = $_POST['accion'] ?? '';
        if ($accion === 'guardar') {
            $id = (int)($_POST['id'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            if ($nombre === '') {
                $mensaje = '<div class="alert alert-danger">El nombre es obligatorio.</div>';
            } else {
                if ($id > 0) {
                    $stmt = $pdo->prepare("UPDATE categorias SET nombre=?, descripcion=? WHERE id=?");
                    $stmt->execute([$nombre, $descripcion, $id]);
                    $mensaje = '<div class="alert alert-success">Categoría actualizada.</div>';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO categorias (nombre, descripcion) VALUES (?, ?)");
                    $stmt->execute([$nombre, $descripcion]);
                    $mensaje = '<div class="alert alert-success">Categoría creada.</div>';
                }
            }
        } elseif ($accion === 'eliminar') {
            $id = (int)($_POST['id'] ?? 0);
            try {
                $pdo->prepare("DELETE FROM categorias WHERE id=?")->execute([$id]);
                $mensaje = '<div class="alert alert-success">Categoría eliminada.</div>';
            } catch (PDOException $ex) {
                $mensaje = '<div class="alert alert-danger">No se puede eliminar: hay productos que la usan.</div>';
            }
        }
    }
}

$categorias = $pdo->query(
    "SELECT c.*, (SELECT COUNT(*) FROM productos p WHERE p.categoria_id = c.id) AS total_productos
     FROM categorias c ORDER BY c.nombre"
)->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h3 class="mb-1"><i class="bi bi-tags me-2"></i>Categorías</h3>
    <p class="text-muted mb-0"><?= count($categorias) ?> registro(s)</p>
  </div>
  <button class="btn btn-primary" onclick="abrirCrear()" data-bs-toggle="modal" data-bs-target="#modalCat">
    <i class="bi bi-plus-lg me-1"></i>Nueva categoría
  </button>
</div>

<?= $mensaje ?>

<div class="card">
  <div class="card-body p-0">
    <?php if (empty($categorias)): ?>
      <div class="empty-state"><i class="bi bi-inbox d-block mb-2"></i>Sin categorías. Crea la primera.</div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr><th>Nombre</th><th>Descripción</th><th class="text-center">Productos</th><th>Creada</th><th class="text-end">Acciones</th></tr>
        </thead>
        <tbody>
          <?php foreach ($categorias as $c): ?>
          <tr>
            <td class="fw-semibold"><?= e($c['nombre']) ?></td>
            <td><?= e($c['descripcion'] ?: '-') ?></td>
            <td class="text-center"><span class="badge bg-secondary"><?= $c['total_productos'] ?></span></td>
            <td><small class="text-muted"><?= date('d/m/Y', strtotime($c['creado_en'])) ?></small></td>
            <td class="text-end">
              <button class="btn btn-sm btn-outline-primary btn-icon"
                      onclick='editar(<?= json_encode($c, JSON_UNESCAPED_UNICODE) ?>)'>
                <i class="bi bi-pencil"></i>
              </button>
              <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar categoría?')">
                <?= csrf_field() ?>
                <input type="hidden" name="accion" value="eliminar">
                <input type="hidden" name="id" value="<?= $c['id'] ?>">
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

<div class="modal fade" id="modalCat" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" id="formCat">
        <?= csrf_field() ?>
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" id="f_id">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-tags me-2"></i><span id="modalTitulo">Nueva categoría</span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Nombre *</label>
            <input type="text" name="nombre" id="f_nombre" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Descripción</label>
            <textarea name="descripcion" id="f_descripcion" class="form-control" rows="3"></textarea>
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
  document.getElementById('modalTitulo').textContent = 'Nueva categoría';
  document.getElementById('formCat').reset();
  document.getElementById('f_id').value = '';
}
function editar(c) {
  document.getElementById('modalTitulo').textContent = 'Editar categoría';
  document.getElementById('f_id').value         = c.id;
  document.getElementById('f_nombre').value      = c.nombre;
  document.getElementById('f_descripcion').value = c.descripcion || '';
  new bootstrap.Modal(document.getElementById('modalCat')).show();
}
</script>
<script src="assets/js/app.js"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
