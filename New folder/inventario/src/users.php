<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_admin();

$titulo_pagina = 'Usuarios';
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $mensaje = '<div class="alert alert-danger">Token CSRF inválido.</div>';
    } else {
        $accion = $_POST['accion'] ?? '';
        if ($accion === 'guardar') {
            $id       = (int)($_POST['id'] ?? 0);
            $nombre   = trim($_POST['nombre'] ?? '');
            $email    = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $rol      = $_POST['rol'] ?? 'usuario';

            if ($nombre === '' || $email === '') {
                $mensaje = '<div class="alert alert-danger">Nombre y email son obligatorios.</div>';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $mensaje = '<div class="alert alert-danger">Email inválido.</div>';
            } else {
                try {
                    if ($id > 0) {
                        if ($password !== '') {
                            $hash = password_hash($password, PASSWORD_BCRYPT);
                            $stmt = $pdo->prepare("UPDATE usuarios SET nombre=?, email=?, rol=?, password=? WHERE id=?");
                            $stmt->execute([$nombre, $email, $rol, $hash, $id]);
                        } else {
                            $stmt = $pdo->prepare("UPDATE usuarios SET nombre=?, email=?, rol=? WHERE id=?");
                            $stmt->execute([$nombre, $email, $rol, $id]);
                        }
                        $mensaje = '<div class="alert alert-success">Usuario actualizado.</div>';
                    } else {
                        if (strlen($password) < 6) {
                            $mensaje = '<div class="alert alert-danger">La contraseña debe tener al menos 6 caracteres.</div>';
                        } else {
                            $hash = password_hash($password, PASSWORD_BCRYPT);
                            $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, email, password, rol) VALUES (?,?,?,?)");
                            $stmt->execute([$nombre, $email, $hash, $rol]);
                            $mensaje = '<div class="alert alert-success">Usuario creado.</div>';
                        }
                    }
                } catch (PDOException $ex) {
                    if ($ex->getCode() === '23000') {
                        $mensaje = '<div class="alert alert-danger">Ya existe un usuario con ese email.</div>';
                    } else {
                        $mensaje = '<div class="alert alert-danger">Error: ' . e($ex->getMessage()) . '</div>';
                    }
                }
            }
        } elseif ($accion === 'eliminar') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id === (int)current_user()['id']) {
                $mensaje = '<div class="alert alert-warning">No puedes eliminar tu propia cuenta.</div>';
            } else {
                $pdo->prepare("DELETE FROM usuarios WHERE id=?")->execute([$id]);
                $mensaje = '<div class="alert alert-success">Usuario eliminado.</div>';
            }
        }
    }
}

$usuarios = $pdo->query("SELECT id, nombre, email, rol, creado_en FROM usuarios ORDER BY id")->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h3 class="mb-1"><i class="bi bi-people me-2"></i>Usuarios</h3>
    <p class="text-muted mb-0">Gestión de cuentas y permisos</p>
  </div>
  <button class="btn btn-primary" onclick="abrirCrear()" data-bs-toggle="modal" data-bs-target="#modalUser">
    <i class="bi bi-plus-lg me-1"></i>Nuevo usuario
  </button>
</div>

<?= $mensaje ?>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead>
          <tr><th>ID</th><th>Nombre</th><th>Email</th><th>Rol</th><th>Creado</th><th class="text-end">Acciones</th></tr>
        </thead>
        <tbody>
          <?php foreach ($usuarios as $u): ?>
          <tr>
            <td><?= $u['id'] ?></td>
            <td>
              <?= e($u['nombre']) ?>
              <?php if ($u['id'] === (int)current_user()['id']): ?>
                <span class="badge bg-info">Tú</span>
              <?php endif; ?>
            </td>
            <td><?= e($u['email']) ?></td>
            <td>
              <?php if ($u['rol'] === 'admin'): ?>
                <span class="badge bg-danger">Administrador</span>
              <?php else: ?>
                <span class="badge bg-secondary">Usuario</span>
              <?php endif; ?>
            </td>
            <td><small class="text-muted"><?= date('d/m/Y', strtotime($u['creado_en'])) ?></small></td>
            <td class="text-end">
              <button class="btn btn-sm btn-outline-primary btn-icon"
                      onclick='editar(<?= json_encode($u, JSON_UNESCAPED_UNICODE) ?>)'>
                <i class="bi bi-pencil"></i>
              </button>
              <form method="post" class="d-inline"
                    onsubmit="return confirm('¿Eliminar usuario «<?= e($u['nombre']) ?>»?')">
                <?= csrf_field() ?>
                <input type="hidden" name="accion" value="eliminar">
                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                <button class="btn btn-sm btn-outline-danger btn-icon"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal fade" id="modalUser" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" id="formUser">
        <?= csrf_field() ?>
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" id="f_id">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-person me-2"></i><span id="modalTitulo">Nuevo usuario</span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Nombre *</label>
            <input type="text" name="nombre" id="f_nombre" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Email *</label>
            <input type="email" name="email" id="f_email" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Contraseña <?= isset($_GET['edit']) ? '(dejar vacío para no cambiar)' : '*' ?></label>
            <input type="password" name="password" id="f_password" class="form-control" minlength="6">
            <small class="text-muted" id="passHelp">Mínimo 6 caracteres.</small>
          </div>
          <div class="mb-3">
            <label class="form-label">Rol</label>
            <select name="rol" id="f_rol" class="form-select">
              <option value="usuario">Usuario</option>
              <option value="admin">Administrador</option>
            </select>
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
  document.getElementById('modalTitulo').textContent = 'Nuevo usuario';
  document.getElementById('formUser').reset();
  document.getElementById('f_id').value = '';
  document.getElementById('f_rol').value = 'usuario';
  document.getElementById('f_password').required = true;
  document.getElementById('passHelp').textContent = 'Mínimo 6 caracteres.';
}
function editar(u) {
  document.getElementById('modalTitulo').textContent = 'Editar usuario';
  document.getElementById('f_id').value       = u.id;
  document.getElementById('f_nombre').value    = u.nombre;
  document.getElementById('f_email').value     = u.email;
  document.getElementById('f_rol').value       = u.rol;
  document.getElementById('f_password').value  = '';
  document.getElementById('f_password').required = false;
  document.getElementById('passHelp').textContent = 'Dejar vacío para mantener la contraseña actual.';
  new bootstrap.Modal(document.getElementById('modalUser')).show();
}
</script>
<script src="assets/js/app.js"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
