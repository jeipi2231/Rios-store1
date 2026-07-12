<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/logger.php';

require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');

    if ($nombre === '') {
        set_flash('error', 'El nombre de la categoría es obligatorio.');
        redirect('/app/categorias/crear.php');
    }

    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare('INSERT INTO categorias (nombre, descripcion) VALUES (:nombre, :descripcion)');
        $stmt->execute(['nombre' => $nombre, 'descripcion' => $descripcion]);
        app_log('info', 'Categoría creada', ['nombre' => $nombre]);
        set_flash('success', 'Categoría creada correctamente.');
        redirect('/app/categorias/index.php');
    } catch (Throwable $e) {
        app_log('error', 'Error al crear categoría', ['error' => $e->getMessage()]);
        set_flash('error', 'No se pudo crear la categoría. Es posible que ya exista.');
        redirect('/app/categorias/crear.php');
    }
}

render_header('Nueva categoría');
?>

<div class="card shadow-sm">
    <div class="card-body">
        <h1 class="h4">Nueva categoría</h1>
        <form method="post" class="row g-3">
            <div class="col-12 col-md-6">
                <label class="form-label">Nombre</label>
                <input class="form-control" name="nombre" required>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label">Descripción</label>
                <input class="form-control" name="descripcion" placeholder="Opcional">
            </div>
            <div class="col-12">
                <button class="btn btn-success" type="submit">Guardar categoría</button>
                <a class="btn btn-secondary" href="<?php echo BASE_URL; ?>/app/categorias/index.php">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php render_footer();
