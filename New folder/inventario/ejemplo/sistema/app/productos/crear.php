<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/logger.php';

require_role(['admin']);

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
            redirect('/app/productos/crear.php');
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            set_flash('error', 'Error al subir imagen. Intenta nuevamente.');
            redirect('/app/productos/crear.php');
        }

        if (!in_array($ext, $allowed)) {
            set_flash('error', 'Formato no permitido. Usa jpg, png, gif o webp.');
            redirect('/app/productos/crear.php');
        }

        if ($file['size'] > 52428800) { // 50MB
            set_flash('error', 'Imagen muy grande (maximo 50MB).');
            redirect('/app/productos/crear.php');
        }

        $uploadDir = __DIR__ . '/../assets/uploads/productos/';
        @mkdir($uploadDir, 0755, true);
        $fileName = 'prod_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $uploadPath = $uploadDir . $fileName;

        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            $imagen = '/assets/uploads/productos/' . $fileName;
        } else {
            set_flash('error', 'No se pudo guardar la imagen. Verifica permisos.');
            redirect('/app/productos/crear.php');
        }
    }

    if (
        $nombre === ''
        || !is_valid_gs_500($precioCompra)
        || !is_valid_gs_500($precioVenta)
        || $stock < 0
        || $stockMin < 0
    ) {
        set_flash('error', 'Datos invalidos para crear el producto.');
        redirect('/app/productos/crear.php');
    }

    try {
        $pdo = getPDO();
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('INSERT INTO productos (nombre, codigo_barras, precio_compra, precio_venta, stock, stock_minimo, categoria_id, imagen) VALUES (:n, :c, :pc, :pv, :s, :sm, :cat, :img)');
        $stmt->execute([
            'n' => $nombre,
            'c' => $codigo,
            'pc' => $precioCompra,
            'pv' => $precioVenta,
            's' => $stock,
            'sm' => $stockMin,
            'cat' => $categoryId,
            'img' => $imagen,
        ]);

        $productoId = (int) $pdo->lastInsertId();

        if ($stock > 0) {
            $mov = $pdo->prepare('INSERT INTO movimientos_stock (producto_id, tipo, cantidad) VALUES (:pid, :tipo, :cant)');
            $mov->execute([
                'pid' => $productoId,
                'tipo' => 'entrada',
                'cant' => $stock,
            ]);
        }

        $pdo->commit();
        app_log('info', 'Producto creado', ['producto_id' => $productoId]);
        set_flash('success', 'Producto creado correctamente.');
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        app_log('error', 'Error al crear producto', ['error' => $e->getMessage()]);
        set_flash('error', 'No se pudo crear el producto (codigo repetido o datos invalidos).');
    }

    redirect('/app/productos/index.php');
}

render_header('Crear producto');
?>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h4 mb-0">Nuevo producto</h1>
            <a href="<?php echo BASE_URL; ?>/app/categorias/index.php" class="btn btn-sm btn-secondary">Administrar categorías</a>
        </div>
        <form method="post" class="row g-3" enctype="multipart/form-data">
            <div class="col-12 col-md-6">
                <label class="form-label">Nombre</label>
                <input class="form-control" name="nombre" required>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label">Codigo de barras</label>
                <input class="form-control" name="codigo_barras" placeholder="Opcional">
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label">Categoria</label>
                <select class="form-select" name="categoria_id">
                    <option value="">Seleccionar categoria</option>
                    <?php
                    $stmtCats = $pdo->query('SELECT id, nombre FROM categorias ORDER BY nombre');
                    foreach ($stmtCats->fetchAll() as $cat):
                    ?>
                        <option value="<?php echo (int) $cat['id']; ?>"><?php echo e($cat['nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Precio compra</label>
                <input class="form-control" type="number" name="precio_compra" min="500" step="500" required>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Precio venta</label>
                <input class="form-control" type="number" name="precio_venta" min="500" step="500" required>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Stock</label>
                <input class="form-control" type="number" name="stock" min="0" value="0" required>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Stock minimo</label>
                <input class="form-control" type="number" name="stock_minimo" min="0" value="5" required>
            </div>
            <div class="col-12">
                <label class="form-label">Foto del producto</label>
                <input class="form-control" type="file" name="imagen" accept="image/*">
                <small class="text-muted">JPG, PNG, GIF o WebP. Maximo 50MB</small>
            </div>
            <div class="col-12">
                <button class="btn btn-success" type="submit">Guardar</button>
                <a class="btn btn-secondary" href="<?php echo BASE_URL; ?>/app/productos/index.php">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php render_footer();
