<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/logger.php';

require_role(['admin']);

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    set_flash('error', 'Categoría inválida.');
    redirect('/app/categorias/index.php');
}

$pdo = getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');

    if ($nombre === '') {
        set_flash('error', 'El nombre de la categoría es obligatorio.');
        redirect('/app/categorias/editar.php?id=' . $id);
    }

    try {
        $stmt = $pdo->prepare('UPDATE categorias SET nombre = :nombre, descripcion = :descripcion WHERE id = :id');
        $stmt->execute(['nombre' => $nombre, 'descripcion' => $descripcion, 'id' => $id]);
        app_log('info', 'Categoría editada', ['categoria_id' => $id]);
        set_flash('success', 'Categoría actualizada.');
        redirect('/app/categorias/index.php');
    } catch (Throwable $e) {
        app_log('error', 'Error al editar categoría', ['error' => $e->getMessage()]);
        set_flash('error', 'No se pudo actualizar la categoría.');
        redirect('/app/categorias/editar.php?id=' . $id);
    }
}

$stmt = $pdo->prepare('SELECT id, nombre, descripcion FROM categorias WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $id]);
$categoria = $stmt->fetch();

if (!$categoria) {
    set_flash('error', 'Categoría no encontrada.');
    redirect('/app/categorias/index.php');
}

render_header('Editar categoría');
?>

<div class="card shadow-sm">
    <div class="card-body">
        <h1 class="h4">Editar categoría #<?php echo (int) $categoria['id']; ?></h1>
        <form method="post" class="row g-3">
            <input type="hidden" name="id" value="<?php echo (int) $categoria['id']; ?>">
            <div class="col-12 col-md-6">
                <label class="form-label">Nombre</label>
                <input class="form-control" name="nombre" required value="<?php echo e($categoria['nombre']); ?>">
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label">Descripción</label>
                <input class="form-control" name="descripcion" value="<?php echo e($categoria['descripcion'] ?? ''); ?>">
            </div>
            <div class="col-12">
                <button class="btn btn-primary" type="submit">Guardar cambios</button>
                <a class="btn btn-secondary" href="<?php echo BASE_URL; ?>/app/categorias/index.php">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php render_footer();
