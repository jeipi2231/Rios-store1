<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/layout.php';

require_login();

$pdo = getPDO();
$q = trim($_GET['q'] ?? '');
$categoryId = isset($_GET['categoria']) ? (int) $_GET['categoria'] : 0;

// Obtener categorías
$stmtCats = $pdo->query('SELECT id, nombre FROM categorias ORDER BY nombre');
$categorias = $stmtCats->fetchAll();

// Construir query con filtros
$filters = ['activo = 1'];
$params = [];

if ($categoryId > 0) {
    $filters[] = 'categoria_id = :catId';
    $params['catId'] = $categoryId;
}

if ($q !== '') {
    $filters[] = '(nombre LIKE :q OR codigo_barras LIKE :q)';
    $params['q'] = '%' . $q . '%';
}

$sql = 'SELECT p.*, c.nombre as categoria FROM productos p 
        LEFT JOIN categorias c ON p.categoria_id = c.id 
        WHERE ' . implode(' AND ', $filters) . ' 
        ORDER BY p.id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll();

render_header('Productos');
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h4 mb-0">Gestion de productos</h1>
    <div class="btn-group" role="group">
        <?php if (($_SESSION['user']['rol'] ?? '') === 'admin'): ?>
            <a href="<?php echo BASE_URL; ?>/app/categorias/index.php" class="btn btn-outline-secondary">Categorías</a>
            <a href="<?php echo BASE_URL; ?>/app/productos/crear.php" class="btn btn-success">Nuevo producto</a>
        <?php endif; ?>
    </div>
</div>

<form class="row g-2 mb-3" method="get">
    <div class="col-12 col-md-6">
        <input type="text" name="q" class="form-control" value="<?php echo e($q); ?>" placeholder="Buscar por nombre o codigo de barras">
    </div>
    <div class="col-12 col-md-3">
        <select name="categoria" class="form-select">
            <option value="0">Todas las categorías</option>
            <?php foreach ($categorias as $cat): ?>
                <option value="<?php echo (int) $cat['id']; ?>" <?php echo $categoryId === (int) $cat['id'] ? 'selected' : ''; ?>>
                    <?php echo e($cat['nombre']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-12 col-md-3">
        <button class="btn btn-primary w-100" type="submit">Buscar</button>
    </div>
</form>

<div class="table-responsive">
    <table class="table table-striped align-middle">
        <thead>
        <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Categoria</th>
            <th>Codigo</th>
            <th>Compra</th>
            <th>Venta</th>
            <th>Stock</th>
            <th>Minimo</th>
            <th>Acciones</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$productos): ?>
            <tr><td colspan="9" class="text-center text-muted">No se encontraron productos.</td></tr>
        <?php endif; ?>
        <?php foreach ($productos as $p): ?>
            <?php $low = (int) $p['stock'] < (int) $p['stock_minimo']; ?>
            <tr class="<?php echo $low ? 'table-warning' : ''; ?>">
                <td><?php echo (int) $p['id']; ?></td>
                <td>
                    <div class="product-name-wrapper" style="position: relative; display: inline-block;">
                        <span class="product-name-link"><?php echo e($p['nombre']); ?></span>
                        <?php if (!empty($p['imagen'])): ?>
                            <div class="product-image-preview">
                                <img src="<?php echo e($p['imagen']); ?>" alt="<?php echo e($p['nombre']); ?>">
                            </div>
                        <?php else: ?>
                            <div class="product-image-preview product-image-placeholder">
                                <span>Sin foto</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </td>
                <td><?php echo e($p['categoria'] ?? 'Sin categoría'); ?></td>
                <td><?php echo e($p['codigo_barras'] ?? 'Sin codigo'); ?></td>
                <td><?php echo e(format_gs((float) $p['precio_compra'])); ?></td>
                <td><?php echo e(format_gs((float) $p['precio_venta'])); ?></td>
                <td><?php echo (int) $p['stock']; ?></td>
                <td><?php echo (int) $p['stock_minimo']; ?></td>
                <td>
                    <?php if (($_SESSION['user']['rol'] ?? '') === 'admin'): ?>
                        <a href="<?php echo BASE_URL; ?>/app/productos/editar.php?id=<?php echo (int) $p['id']; ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                        <a href="<?php echo BASE_URL; ?>/app/productos/eliminar.php?id=<?php echo (int) $p['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Eliminar producto?');">Eliminar</a>
                    <?php else: ?>
                        <span class="text-muted small">Solo admin</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php render_footer();
