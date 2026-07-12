<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$titulo_pagina = 'Proveedores';
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $mensaje = '<div class="alert alert-danger">Token CSRF inválido.</div>';
    } else {
        $accion = $_POST['accion'] ?? '';
        if ($accion === 'guardar') {
            $id = (int)($_POST['id'] ?? 0);
            $nombre    = trim($_POST['nombre'] ?? '');
            $contacto = trim($_POST['contacto'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            $email    = trim($_POST['email'] ?? '');
            $direccion= trim($_POST['direccion'] ?? '');
            if ($nombre === '') {
                $mensaje = '<div class="alert alert-danger">El nombre es obligatorio.</div>';
            } else {
                if ($id > 0) {
                    $stmt = $pdo->prepare(
                        "UPDATE proveedores SET nombre=?, contacto=?, telefono=?, email=?, direccion=? WHERE id=?"
                    );
                    $stmt->execute([$nombre,$contacto,$telefono,$email,$direccion,$id]);
                    $mensaje = '<div class="alert alert-success">Proveedor actualizado.</div>';
                } else {
                    $stmt = $pdo->prepare(
                        "INSERT INTO proveedores (nombre, contacto, telefono, email, direccion) VALUES (?,?,?,?,?)"
                    );
                    $stmt->execute([$nombre,$contacto,$telefono,$email,$direccion]);
                    $mensaje = '<div class="alert alert-success">Proveedor creado.</div>';
                }
            }
        } elseif ($accion === 'eliminar') {
            $id = (int)($_POST['id'] ?? 0);
            try {
                $pdo->prepare("DELETE FROM proveedores WHERE id=?")->execute([$id]);
                $mensaje = '<div class="alert alert-success">Proveedor eliminado.</div>';
            } catch (PDOException $ex) {
                $mensaje = '<div class="alert alert-danger">No se puede eliminar: hay productos asociados.</div>';
            }
        }
    }
}

$proveedores = $pdo->query(
    "SELECT pr.*, (SELECT COUNT(*) FROM productos p WHERE p.proveedor_id = pr.id) AS total_productos
     FROM proveedores pr ORDER BY pr.nombre"
)->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h3 class="mb-1"><i class="bi bi-truck me-2"></i>Proveedores</h3>
    <p class="text-muted mb-0"><?= count($proveedores) ?> registro(s)</p>
  </div>
  <button class="btn btn-primary" onclick="abrirCrear()" data-bs-toggle="modal" data-bs-target="#modalProv">
    <i class="bi bi-plus-lg me-1"></i>Nuevo proveedor
  </button>
</div>

<?= $mensaje ?>

<div class="card">
  <div class="card-body p-0">
    <?php if (empty($proveedores)): ?>
      <div class="empty-state"><i class="bi bi-inbox d-block mb-2"></i>Sin proveedores.</div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead>
          <tr><th>Nombre</th><th>Contacto</th><th>Teléfono</th><th>Email</th><th>Dirección</th><th class="text-center">Productos</th><th class="text-end">Acciones</th></tr>
        </thead>
        <tbody>
          <?php foreach ($proveedores as $p): ?>
          <tr>
            <td class="fw-semibold"><?= e($p['nombre']) ?></td>
            <td><?= e($p['contacto'] ?: '-') ?></td>
            <td><?= e($p['telefono'] ?: '-') ?></td>
            <td><?= $p['email'] ? '<a href="mailto:'.e($p['email']).'">'.e($p['email']).'</a>' : '-' ?></td>
            <td><small><?= e($p['direccion'] ?: '-') ?></small></td>
            <td class="text-center"><span class="badge bg-secondary"><?= $p['total_productos'] ?></span></td>
            <td class="text-end">
              <button class="btn btn-sm btn-outline-primary btn-icon"
                      onclick='editar(<?= json_encode($p, JSON_UNESCAPED_UNICODE) ?>)'>
                <i class="bi bi-pencil"></i>
              </button>
              <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar proveedor?')">
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

<div class="modal fade" id="modalProv" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="post" id="formProv">
        <?= csrf_field() ?>
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" id="f_id">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-truck me-2"></i><span id="modalTitulo">Nuevo proveedor</span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label">Nombre / Empresa *</label>
              <input type="text" name="nombre" id="f_nombre" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Contacto</label>
              <input type="text" name="contacto" id="f_contacto" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Teléfono</label>
              <input type="text" name="telefono" id="f_telefono" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Email</label>
              <input type="email" name="email" id="f_email" class="form-control">
            </div>
            <div class="col-12">
              <label class="form-label">Dirección</label>
              <textarea name="direccion" id="f_direccion" class="form-control" rows="2"></textarea>
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
  document.getElementById('modalTitulo').textContent = 'Nuevo proveedor';
  document.getElementById('formProv').reset();
  document.getElementById('f_id').value = '';
}
function editar(p) {
  document.getElementById('modalTitulo').textContent = 'Editar proveedor';
  document.getElementById('f_id').value        = p.id;
  document.getElementById('f_nombre').value     = p.nombre;
  document.getElementById('f_contacto').value   = p.contacto || '';
  document.getElementById('f_telefono').value   = p.telefono || '';
  document.getElementById('f_email').value      = p.email || '';
  document.getElementById('f_direccion').value  = p.direccion || '';
  new bootstrap.Modal(document.getElementById('modalProv')).show();
}
</script>
<script src="assets/js/app.js"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
