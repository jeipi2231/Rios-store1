<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/schema.php';

require_role(['admin']);
ensure_extended_schema();

$pdo = getPDO();
$q = trim($_GET['q'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $notas = trim($_POST['notas'] ?? '');

        if ($nombre === '') {
            set_flash('error', 'El nombre del proveedor es obligatorio.');
            redirect('/app/proveedores/index.php');
        }

        try {
            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE proveedores SET nombre=:nombre, telefono=:telefono, email=:email, direccion=:direccion, notas=:notas WHERE id=:id');
                $stmt->execute([
                    'nombre' => $nombre,
                    'telefono' => $telefono === '' ? null : $telefono,
                    'email' => $email === '' ? null : $email,
                    'direccion' => $direccion === '' ? null : $direccion,
                    'notas' => $notas === '' ? null : $notas,
                    'id' => $id,
                ]);
                set_flash('success', 'Proveedor actualizado.');
            } else {
                $stmt = $pdo->prepare('INSERT INTO proveedores (nombre, telefono, email, direccion, notas) VALUES (:nombre, :telefono, :email, :direccion, :notas)');
                $stmt->execute([
                    'nombre' => $nombre,
                    'telefono' => $telefono === '' ? null : $telefono,
                    'email' => $email === '' ? null : $email,
                    'direccion' => $direccion === '' ? null : $direccion,
                    'notas' => $notas === '' ? null : $notas,
                ]);
                set_flash('success', 'Proveedor creado.');
            }
        } catch (Throwable $e) {
            set_flash('error', 'No se pudo guardar el proveedor.');
        }

        redirect('/app/proveedores/index.php');
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('DELETE FROM proveedores WHERE id = :id');
            $stmt->execute(['id' => $id]);
            set_flash('success', 'Proveedor eliminado.');
        }
        redirect('/app/proveedores/index.php');
    }
}

$params = [];
$sql = 'SELECT id, nombre, telefono, email, direccion, notas, created_at FROM proveedores';
if ($q !== '') {
    $sql .= ' WHERE nombre LIKE :q OR telefono LIKE :q OR email LIKE :q';
    $params['q'] = '%' . $q . '%';
}
$sql .= ' ORDER BY id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$proveedores = $stmt->fetchAll();

render_header('Proveedores');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Proveedores</h1>
    <button class="btn btn-primary" type="button" onclick="openCreateProveedor()">Nuevo proveedor</button>
</div>

<form class="row g-2 mb-3" method="get">
    <div class="col-12 col-md-10">
        <input type="text" name="q" class="form-control" value="<?php echo e($q); ?>" placeholder="Buscar por nombre, telefono o email">
    </div>
    <div class="col-12 col-md-2 d-grid">
        <button class="btn btn-outline-primary" type="submit">Buscar</button>
    </div>
</form>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Nombre</th>
                    <th>Telefono</th>
                    <th>Email</th>
                    <th>Direccion</th>
                    <th>Notas</th>
                    <th class="text-end">Acciones</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$proveedores): ?>
                    <tr><td colspan="7" class="text-muted">No hay proveedores cargados.</td></tr>
                <?php endif; ?>
                <?php foreach ($proveedores as $p): ?>
                    <tr>
                        <td><?php echo (int) $p['id']; ?></td>
                        <td><?php echo e($p['nombre']); ?></td>
                        <td><?php echo e($p['telefono'] ?? '-'); ?></td>
                        <td><?php echo e($p['email'] ?? '-'); ?></td>
                        <td><?php echo e($p['direccion'] ?? '-'); ?></td>
                        <td><?php echo e($p['notas'] ?? '-'); ?></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-primary" type="button" onclick='openEditProveedor(<?php echo json_encode($p, JSON_UNESCAPED_UNICODE); ?>)'>Editar</button>
                            <form method="post" class="d-inline" onsubmit="return confirm('Eliminar proveedor?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo (int) $p['id']; ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="proveedorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="p_id" value="0">
                <div class="modal-header">
                    <h2 class="h5 mb-0" id="p_title">Nuevo proveedor</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label">Nombre</label>
                            <input class="form-control" id="p_nombre" name="nombre" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Telefono (opcional)</label>
                            <input class="form-control" id="p_telefono" name="telefono">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Email/Gmail (opcional)</label>
                            <input class="form-control" type="email" id="p_email" name="email">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Direccion (opcional)</label>
                            <input class="form-control" id="p_direccion" name="direccion">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notas (opcional)</label>
                            <textarea class="form-control" id="p_notas" name="notas" rows="2"></textarea>
                        </div>
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
let proveedorModalInstance = null;

function getProveedorModal() {
    if (!proveedorModalInstance) {
        proveedorModalInstance = new bootstrap.Modal(document.getElementById('proveedorModal'));
    }
    return proveedorModalInstance;
}

function openCreateProveedor() {
    document.getElementById('p_title').textContent = 'Nuevo proveedor';
    document.getElementById('p_id').value = '0';
    document.getElementById('p_nombre').value = '';
    document.getElementById('p_telefono').value = '';
    document.getElementById('p_email').value = '';
    document.getElementById('p_direccion').value = '';
    document.getElementById('p_notas').value = '';
    getProveedorModal().show();
}

function openEditProveedor(p) {
    document.getElementById('p_title').textContent = 'Editar proveedor';
    document.getElementById('p_id').value = p.id || 0;
    document.getElementById('p_nombre').value = p.nombre || '';
    document.getElementById('p_telefono').value = p.telefono || '';
    document.getElementById('p_email').value = p.email || '';
    document.getElementById('p_direccion').value = p.direccion || '';
    document.getElementById('p_notas').value = p.notas || '';
    getProveedorModal().show();
}
</script>

<?php render_footer();
