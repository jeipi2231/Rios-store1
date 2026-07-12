<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/logger.php';

require_role(['admin']);

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    set_flash('error', 'Producto invalido.');
    redirect('/app/productos/index.php');
}

$pdo = getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $codigo = trim($_POST['codigo_barras'] ?? '');
    $precioCompra = (float) ((int) ($_POST['precio_compra'] ?? 0));
    $precioVenta = (float) ((int) ($_POST['precio_venta'] ?? 0));
    $stock = (int) ($_POST['stock'] ?? 0);
    $stockMin = (int) ($_POST['stock_minimo'] ?? 0);
    $categoryId = isset($_POST['categoria_id']) && (int) $_POST['categoria_id'] > 0 ? (int) $_POST['categoria_id'] : null;

    $codigo = $codigo === '' ? null : $codigo;

    $imagen = null;
    if (!empty($_FILES['imagen']['name'])) {
        $file = $_FILES['imagen'];
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
            set_flash('error', 'Imagen muy grande (maximo 50MB).');
            redirect('/app/productos/editar.php?id=' . $id);
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            set_flash('error', 'Error al subir imagen. Intenta nuevamente.');
            redirect('/app/productos/editar.php?id=' . $id);
        }

        if (!in_array($ext, $allowed)) {
            set_flash('error', 'Formato no permitido. Usa jpg, png, gif o webp.');
            redirect('/app/productos/editar.php?id=' . $id);
        }

        if ($file['size'] > 52428800) { // 50MB
            set_flash('error', 'Imagen muy grande (maximo 50MB).');
            redirect('/app/productos/editar.php?id=' . $id);
        }

        $uploadDir = __DIR__ . '/../assets/uploads/productos/';
        @mkdir($uploadDir, 0755, true);
        $fileName = 'prod_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $uploadPath = $uploadDir . $fileName;

        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            $imagen = '/assets/uploads/productos/' . $fileName;
        } else {
            set_flash('error', 'No se pudo guardar la imagen. Verifica permisos.');
            redirect('/app/productos/editar.php?id=' . $id);
        }
    }

    try {
        $pdo->beginTransaction();

        $prevStmt = $pdo->prepare('SELECT stock, imagen FROM productos WHERE id = :id FOR UPDATE');
        $prevStmt->execute(['id' => $id]);
        $prev = $prevStmt->fetch();

        if (!$prev) {
            throw new RuntimeException('Producto no existe');
        }

        $imagenFinal = $imagen ?? $prev['imagen'];

        $stmt = $pdo->prepare('UPDATE productos SET nombre=:n, codigo_barras=:c, precio_compra=:pc, precio_venta=:pv, stock=:s, stock_minimo=:sm, categoria_id=:cat, imagen=:img WHERE id=:id');
        $stmt->execute([
            'n' => $nombre,
            'c' => $codigo,
            'pc' => $precioCompra,
            'pv' => $precioVenta,
            's' => $stock,
            'sm' => $stockMin,
            'cat' => $categoryId,
            'img' => $imagenFinal,
            'id' => $id,
        ]);

        $diff = $stock - (int) $prev['stock'];
        if ($diff !== 0) {
            $mov = $pdo->prepare('INSERT INTO movimientos_stock (producto_id, tipo, cantidad) VALUES (:pid, :tipo, :cant)');
            $mov->execute([
                'pid' => $id,
                'tipo' => $diff > 0 ? 'entrada' : 'salida',
                'cant' => abs($diff),
            ]);
        }

        $pdo->commit();
        app_log('info', 'Producto editado', ['producto_id' => $id]);
        set_flash('success', 'Producto actualizado.');
        redirect('/app/productos/index.php');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        app_log('error', 'Error al editar producto', ['producto_id' => $id, 'error' => $e->getMessage()]);
        set_flash('error', 'No se pudo actualizar el producto.');
        redirect('/app/productos/editar.php?id=' . $id);
    }
}

$stmt = $pdo->prepare('SELECT * FROM productos WHERE id = :id');
$stmt->execute(['id' => $id]);
$producto = $stmt->fetch();

if (!$producto) {
    set_flash('error', 'Producto no encontrado.');
    redirect('/app/productos/index.php');
}

render_header('Editar producto');
?>

    <div class="card shadow-sm">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h4 mb-0">Editar producto #<?php echo (int) $producto['id']; ?></h1>
            <a href="<?php echo BASE_URL; ?>/app/categorias/index.php" class="btn btn-sm btn-secondary">Administrar categorías</a>
        </div>
        <form method="post" class="row g-3" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?php echo (int) $producto['id']; ?>">
            <div class="col-12 col-md-6">
                <label class="form-label">Nombre</label>
                <input class="form-control" name="nombre" required value="<?php echo e($producto['nombre']); ?>">
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label">Codigo de barras</label>
                <input class="form-control" name="codigo_barras" placeholder="Opcional" value="<?php echo e($producto['codigo_barras'] ?? ''); ?>">
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label">Categoria</label>
                <select class="form-select" name="categoria_id">
                    <option value="">Sin categoría</option>
                    <?php
                    $stmtCats = $pdo->query('SELECT id, nombre FROM categorias ORDER BY nombre');
                    foreach ($stmtCats->fetchAll() as $cat):
                    ?>
                        <option value="<?php echo (int) $cat['id']; ?>" <?php echo ((int) $producto['categoria_id'] === (int) $cat['id']) ? 'selected' : ''; ?>>
                            <?php echo e($cat['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Precio compra</label>
                <input class="form-control" type="number" name="precio_compra" min="500" step="500" required value="<?php echo e((string) (int) $producto['precio_compra']); ?>">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Precio venta</label>
                <input class="form-control" type="number" name="precio_venta" min="500" step="500" required value="<?php echo e((string) (int) $producto['precio_venta']); ?>">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Stock</label>
                <input class="form-control" type="number" name="stock" min="0" required value="<?php echo (int) $producto['stock']; ?>">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Stock minimo</label>
                <input class="form-control" type="number" name="stock_minimo" min="0" required value="<?php echo (int) $producto['stock_minimo']; ?>">
            </div>
            <div class="col-12">
                <label class="form-label">Foto del producto</label>
                <?php if (!empty($producto['imagen'])): ?>
                    <div class="mb-2">
                        <img src="<?php echo e($producto['imagen']); ?>" alt="<?php echo e($producto['nombre']); ?>" style="max-width: 150px; max-height: 150px; border-radius: 4px;">
                        <p class="text-muted small">Foto actual</p>
                    </div>
                <?php endif; ?>
                <input class="form-control" type="file" name="imagen" accept="image/*">
                <small class="text-muted">JPG, PNG, GIF o WebP. Maximo 50MB. Dejar en blanco para mantener la foto actual</small>
            </div>
            <div class="col-12">
                <button class="btn btn-primary" type="submit">Guardar cambios</button>
                <a class="btn btn-secondary" href="<?php echo BASE_URL; ?>/app/productos/index.php">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php render_footer();
