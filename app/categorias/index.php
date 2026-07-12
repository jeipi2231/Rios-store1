<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/layout.php';

require_role(['admin']);

$pdo = getPDO();
$stmt = $pdo->query('SELECT id, nombre, descripcion, created_at FROM categorias ORDER BY nombre');
$categorias = $stmt->fetchAll();

render_header('Categorías');
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h4 mb-0">Categorías</h1>
    <div>
        <a href="<?php echo BASE_URL; ?>/app/productos/index.php" class="btn btn-outline-secondary me-2">Volver a productos</a>
        <a href="<?php echo BASE_URL; ?>/app/categorias/crear.php" class="btn btn-success">Nueva categoría</a>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-striped align-middle">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Descripción</th>
                <th>Creada</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$categorias): ?>
                <tr><td colspan="5" class="text-center text-muted">No hay categorías registradas.</td></tr>
            <?php endif; ?>
            <?php foreach ($categorias as $cat): ?>
                <tr>
                    <td><?php echo (int) $cat['id']; ?></td>
                    <td><?php echo e($cat['nombre']); ?></td>
                    <td><?php echo e($cat['descripcion'] ?? ''); ?></td>
                    <td><?php echo e($cat['created_at']); ?></td>
                    <td>
                        <a href="<?php echo BASE_URL; ?>/app/categorias/editar.php?id=<?php echo (int) $cat['id']; ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php render_footer();
