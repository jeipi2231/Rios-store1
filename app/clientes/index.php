<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/schema.php';

require_login();
ensure_extended_schema();

$pdo = getPDO();
$q = trim($_GET['q'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $ruc = trim($_POST['ruc'] ?? '');

        if ($nombre === '' || $apellido === '' || $ruc === '') {
            set_flash('error', 'Completa nombre, apellido y RUC.');
            redirect('/app/clientes/index.php');
        }

        try {
            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE clientes SET nombre = :nombre, apellido = :apellido, ruc = :ruc WHERE id = :id');
                $stmt->execute([
                    'nombre' => $nombre,
                    'apellido' => $apellido,
                    'ruc' => $ruc,
                    'id' => $id,
                ]);
                set_flash('success', 'Cliente actualizado.');
            } else {
                $stmt = $pdo->prepare('INSERT INTO clientes (nombre, apellido, ruc) VALUES (:nombre, :apellido, :ruc)');
                $stmt->execute([
                    'nombre' => $nombre,
                    'apellido' => $apellido,
                    'ruc' => $ruc,
                ]);
                set_flash('success', 'Cliente creado.');
            }
        } catch (Throwable $e) {
            set_flash('error', 'No se pudo guardar cliente. Verifica que el RUC no este repetido.');
        }

        redirect('/app/clientes/index.php');
    }

    if ($action === 'delete') {
        require_role(['admin']);
        $id = (int) ($_POST['id'] ?? 0);

        if ($id > 0) {
            $stmt = $pdo->prepare('DELETE FROM clientes WHERE id = :id');
            $stmt->execute(['id' => $id]);
            set_flash('success', 'Cliente eliminado.');
        }

        redirect('/app/clientes/index.php');
    }
}

$params = [];
$sql = 'SELECT id, nombre, apellido, ruc, created_at FROM clientes';

if ($q !== '') {
    $sql .= ' WHERE nombre LIKE :q OR apellido LIKE :q OR ruc LIKE :q';
    $params['q'] = '%' . $q . '%';
}

$sql .= ' ORDER BY id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clientes = $stmt->fetchAll();

render_header('Clientes');
?>

<div class="d-flex justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h4 mb-0">Clientes</h1>
    <button class="btn btn-primary" type="button" onclick="openCreate()">Nuevo cliente</button>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <h2 class="h6">Alta rapida de cliente</h2>
        <form method="post" class="row g-2">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="0">
            <div class="col-12 col-md-4">
                <label class="form-label">Nombre</label>
                <input class="form-control" name="nombre" required>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label">Apellido</label>
                <input class="form-control" name="apellido" required>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label">RUC</label>
                <input class="form-control" name="ruc" required>
            </div>
            <div class="col-12 col-md-1 d-grid align-items-end">
                <button class="btn btn-success" type="submit">Guardar</button>
            </div>
        </form>
    </div>
</div>

<form method="get" class="row g-2 mb-3">
    <div class="col-12 col-md-8">
        <input class="form-control" type="text" name="q" value="<?php echo e($q); ?>" placeholder="Buscar por nombre, apellido o RUC">
    </div>
    <div class="col-6 col-md-2 d-grid">
        <button class="btn btn-outline-primary" type="submit">Buscar</button>
    </div>
    <div class="col-6 col-md-2 d-grid">
        <a class="btn btn-outline-secondary" href="<?php echo BASE_URL; ?>/app/clientes/index.php">Limpiar</a>
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
                    <th>Apellido</th>
                    <th>RUC</th>
                    <th>Creado</th>
                    <th class="text-end">Acciones</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$clientes): ?>
                    <tr><td colspan="6" class="text-muted">Sin clientes registrados.</td></tr>
                <?php endif; ?>
                <?php foreach ($clientes as $c): ?>
                    <tr>
                        <td><?php echo (int) $c['id']; ?></td>
                        <td><?php echo e($c['nombre']); ?></td>
                        <td><?php echo e($c['apellido']); ?></td>
                        <td><?php echo e($c['ruc']); ?></td>
                        <td><?php echo e(date('d/m/Y H:i', strtotime($c['created_at']))); ?></td>
                        <td class="text-end">
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-primary"
                                onclick='openEdit(<?php echo json_encode($c, JSON_UNESCAPED_UNICODE); ?>)'>
                                Editar
                            </button>
                            <?php if (($_SESSION['user']['rol'] ?? '') === 'admin'): ?>
                                <form method="post" class="d-inline" onsubmit="return confirm('Eliminar cliente?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo (int) $c['id']; ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Eliminar</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="clienteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" id="clienteForm">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="f_id" value="0">
                <div class="modal-header">
                    <h2 class="h5 mb-0" id="modalTitle">Nuevo cliente</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <input class="form-control" name="nombre" id="f_nombre" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Apellido</label>
                        <input class="form-control" name="apellido" id="f_apellido" required>
                    </div>
                    <div>
                        <label class="form-label">RUC</label>
                        <input class="form-control" name="ruc" id="f_ruc" required>
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
const clienteModal = new bootstrap.Modal(document.getElementById('clienteModal'));

function openCreate() {
    document.getElementById('modalTitle').textContent = 'Nuevo cliente';
    document.getElementById('f_id').value = '0';
    document.getElementById('f_nombre').value = '';
    document.getElementById('f_apellido').value = '';
    document.getElementById('f_ruc').value = '';
    clienteModal.show();
}

function openEdit(cliente) {
    document.getElementById('modalTitle').textContent = 'Editar cliente';
    document.getElementById('f_id').value = cliente.id;
    document.getElementById('f_nombre').value = cliente.nombre || '';
    document.getElementById('f_apellido').value = cliente.apellido || '';
    document.getElementById('f_ruc').value = cliente.ruc || '';
    clienteModal.show();
}
</script>

<?php render_footer();
