<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/layout.php';

require_role(['admin']);

$pdo = getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $usuario = trim($_POST['usuario'] ?? '');
        $rol = trim($_POST['rol'] ?? 'cajero');
        $password = trim($_POST['password'] ?? '');

        if ($nombre === '' || $usuario === '' || !in_array($rol, ['admin', 'cajero'], true)) {
            set_flash('error', 'Completa nombre, usuario y rol validos.');
            redirect('/app/usuarios/index.php');
        }

        try {
            if ($id > 0) {
                if ($password !== '') {
                    $stmt = $pdo->prepare('UPDATE usuarios SET nombre = :nombre, usuario = :usuario, rol = :rol, password = :password WHERE id = :id');
                    $stmt->execute([
                        'nombre' => $nombre,
                        'usuario' => $usuario,
                        'rol' => $rol,
                        'password' => password_hash($password, PASSWORD_BCRYPT),
                        'id' => $id,
                    ]);
                } else {
                    $stmt = $pdo->prepare('UPDATE usuarios SET nombre = :nombre, usuario = :usuario, rol = :rol WHERE id = :id');
                    $stmt->execute([
                        'nombre' => $nombre,
                        'usuario' => $usuario,
                        'rol' => $rol,
                        'id' => $id,
                    ]);
                }

                set_flash('success', 'Usuario actualizado.');
            } else {
                if ($password === '') {
                    set_flash('error', 'La contrasena es obligatoria para crear un usuario.');
                    redirect('/app/usuarios/index.php');
                }

                $stmt = $pdo->prepare('INSERT INTO usuarios (nombre, usuario, password, rol) VALUES (:nombre, :usuario, :password, :rol)');
                $stmt->execute([
                    'nombre' => $nombre,
                    'usuario' => $usuario,
                    'password' => password_hash($password, PASSWORD_BCRYPT),
                    'rol' => $rol,
                ]);

                set_flash('success', 'Usuario creado correctamente.');
            }
        } catch (Throwable $e) {
            set_flash('error', 'No se pudo guardar el usuario. Verifica si el nombre de usuario ya existe.');
        }

        redirect('/app/usuarios/index.php');
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $currentUserId = (int) ($_SESSION['user']['id'] ?? 0);

        if ($id <= 0) {
            redirect('/app/usuarios/index.php');
        }

        if ($id === $currentUserId) {
            set_flash('error', 'No puedes eliminar tu propio usuario.');
            redirect('/app/usuarios/index.php');
        }

        $stmtTarget = $pdo->prepare('SELECT rol FROM usuarios WHERE id = :id LIMIT 1');
        $stmtTarget->execute(['id' => $id]);
        $target = $stmtTarget->fetch();

        if (!$target) {
            set_flash('error', 'Usuario no encontrado.');
            redirect('/app/usuarios/index.php');
        }

        if (($target['rol'] ?? '') === 'admin') {
            $adminCount = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'admin'")->fetchColumn();
            if ($adminCount <= 1) {
                set_flash('error', 'Debe existir al menos un administrador.');
                redirect('/app/usuarios/index.php');
            }
        }

        $stmtDel = $pdo->prepare('DELETE FROM usuarios WHERE id = :id');
        $stmtDel->execute(['id' => $id]);
        set_flash('success', 'Usuario eliminado.');
        redirect('/app/usuarios/index.php');
    }
}

$usuarios = $pdo->query('SELECT id, nombre, usuario, rol FROM usuarios ORDER BY id ASC')->fetchAll();

render_header('Usuarios');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Usuarios del sistema</h1>
    <button type="button" class="btn btn-primary" onclick="openCreateUser()">Nuevo usuario</button>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <p class="text-muted mb-0">Solo un usuario con rol <strong>admin</strong> puede crear, editar o eliminar usuarios. Los usuarios con rol <strong>cajero</strong> no pueden editar productos.</p>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Usuario</th>
                    <th>Rol</th>
                    <th class="text-end">Acciones</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$usuarios): ?>
                    <tr><td colspan="5" class="text-muted">Sin usuarios.</td></tr>
                <?php endif; ?>
                <?php foreach ($usuarios as $u): ?>
                    <tr>
                        <td><?php echo (int) $u['id']; ?></td>
                        <td><?php echo e($u['nombre']); ?></td>
                        <td><?php echo e($u['usuario']); ?></td>
                        <td>
                            <?php if (($u['rol'] ?? '') === 'admin'): ?>
                                <span class="badge text-bg-dark">admin</span>
                            <?php else: ?>
                                <span class="badge text-bg-secondary">cajero</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick='openEditUser(<?php echo json_encode($u, JSON_UNESCAPED_UNICODE); ?>)'>Editar</button>
                            <form method="post" class="d-inline" onsubmit="return confirm('Eliminar usuario?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" id="userForm">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="u_id" value="0">
                <div class="modal-header">
                    <h2 class="h5 mb-0" id="userModalTitle">Nuevo usuario</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <input class="form-control" name="nombre" id="u_nombre" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Usuario</label>
                        <input class="form-control" name="usuario" id="u_usuario" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Rol</label>
                        <select class="form-select" name="rol" id="u_rol" required>
                            <option value="cajero">cajero</option>
                            <option value="admin">admin</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label" id="u_password_label">Contrasena</label>
                        <input class="form-control" type="password" name="password" id="u_password" autocomplete="new-password">
                        <small class="text-muted" id="u_password_help">Obligatoria para crear. En edicion, dejar vacia para mantener.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const userModal = new bootstrap.Modal(document.getElementById('userModal'));

function openCreateUser() {
    document.getElementById('userModalTitle').textContent = 'Nuevo usuario';
    document.getElementById('u_id').value = '0';
    document.getElementById('u_nombre').value = '';
    document.getElementById('u_usuario').value = '';
    document.getElementById('u_rol').value = 'cajero';
    document.getElementById('u_password').value = '';
    document.getElementById('u_password_label').textContent = 'Contrasena';
    document.getElementById('u_password').required = true;
    document.getElementById('u_password_help').textContent = 'Obligatoria para crear.';
    userModal.show();
}

function openEditUser(user) {
    document.getElementById('userModalTitle').textContent = 'Editar usuario';
    document.getElementById('u_id').value = user.id;
    document.getElementById('u_nombre').value = user.nombre || '';
    document.getElementById('u_usuario').value = user.usuario || '';
    document.getElementById('u_rol').value = user.rol || 'cajero';
    document.getElementById('u_password').value = '';
    document.getElementById('u_password').required = false;
    document.getElementById('u_password_help').textContent = 'Dejar vacia para mantener la contrasena actual.';
    userModal.show();
}
</script>

<?php render_footer();
