<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/logger.php';

require_login();

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$pdo = getPDO();
$q = trim($_GET['q'] ?? '');
$resultados = [];

if ($q !== '') {
    $stmtSearch = $pdo->prepare('SELECT id, nombre, codigo_barras, precio_venta, stock, imagen FROM productos WHERE activo = 1 AND (nombre LIKE :q OR codigo_barras = :code) ORDER BY nombre LIMIT 20');
    $stmtSearch->execute([
        'q' => '%' . $q . '%',
        'code' => $q,
    ]);
    $resultados = $stmtSearch->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_by_barcode') {
        $barcode = trim($_POST['barcode'] ?? '');

        if ($barcode === '') {
            redirect('/app/ventas/index.php');
        }

        $stmt = $pdo->prepare('SELECT id, nombre, precio_venta, stock FROM productos WHERE codigo_barras = :barcode AND activo = 1 LIMIT 1');
        $stmt->execute(['barcode' => $barcode]);
        $p = $stmt->fetch();

        if (!$p) {
            set_flash('error', 'No se encontro producto con ese codigo de barras.');
            redirect('/app/ventas/index.php');
        }

        $productoId = (int) $p['id'];
        $currentQty = $_SESSION['cart'][$productoId]['cantidad'] ?? 0;

        if ($currentQty + 1 > (int) $p['stock']) {
            set_flash('error', 'Stock insuficiente para agregar mas unidades.');
            redirect('/app/ventas/index.php');
        }

        $_SESSION['cart'][$productoId] = [
            'id' => $productoId,
            'nombre' => $p['nombre'],
            'precio' => (float) $p['precio_venta'],
            'cantidad' => $currentQty + 1,
        ];

        set_flash('success', 'Producto agregado por escaneo.');
        redirect('/app/ventas/index.php');
    }

    if ($action === 'add_item') {
        $productoId = (int) ($_POST['producto_id'] ?? 0);

        $stmt = $pdo->prepare('SELECT id, nombre, precio_venta, stock FROM productos WHERE id = :id AND activo = 1 LIMIT 1');
        $stmt->execute(['id' => $productoId]);
        $p = $stmt->fetch();

        if (!$p) {
            set_flash('error', 'Producto no encontrado.');
            redirect('/app/ventas/index.php');
        }

        $currentQty = $_SESSION['cart'][$productoId]['cantidad'] ?? 0;
        if ($currentQty + 1 > (int) $p['stock']) {
            set_flash('error', 'Stock insuficiente para agregar mas unidades.');
            redirect('/app/ventas/index.php');
        }

        $_SESSION['cart'][$productoId] = [
            'id' => (int) $p['id'],
            'nombre' => $p['nombre'],
            'precio' => (float) $p['precio_venta'],
            'cantidad' => $currentQty + 1,
        ];

        set_flash('success', 'Producto agregado al carrito.');
        redirect('/app/ventas/index.php');
    }

    if ($action === 'update_qty') {
        $productoId = (int) ($_POST['producto_id'] ?? 0);
        $cantidad = (int) ($_POST['cantidad'] ?? 1);

        if (isset($_SESSION['cart'][$productoId])) {
            if ($cantidad <= 0) {
                unset($_SESSION['cart'][$productoId]);
            } else {
                $stmt = $pdo->prepare('SELECT stock FROM productos WHERE id = :id');
                $stmt->execute(['id' => $productoId]);
                $stock = (int) ($stmt->fetch()['stock'] ?? 0);

                if ($cantidad > $stock) {
                    set_flash('error', 'Cantidad supera el stock disponible.');
                    redirect('/app/ventas/index.php');
                }

                $_SESSION['cart'][$productoId]['cantidad'] = $cantidad;
            }
        }

        redirect('/app/ventas/index.php');
    }

    if ($action === 'remove_item') {
        $productoId = (int) ($_POST['producto_id'] ?? 0);
        unset($_SESSION['cart'][$productoId]);
        redirect('/app/ventas/index.php');
    }

    if ($action === 'clear_cart') {
        $_SESSION['cart'] = [];
        set_flash('success', 'Carrito vaciado.');
        redirect('/app/ventas/index.php');
    }

    if ($action === 'checkout') {
        $cart = $_SESSION['cart'];
        if (!$cart) {
            set_flash('error', 'El carrito esta vacio.');
            redirect('/app/ventas/index.php');
        }

        $montoPagado = floatval($_POST['monto_pagado'] ?? 0);
        $total = 0.0;

        try {
            $pdo->beginTransaction();

            $lineas = [];

            foreach ($cart as $item) {
                $stmt = $pdo->prepare('SELECT id, nombre, stock, precio_venta FROM productos WHERE id = :id AND activo = 1 FOR UPDATE');
                $stmt->execute(['id' => $item['id']]);
                $producto = $stmt->fetch();

                if (!$producto) {
                    throw new RuntimeException('Producto no encontrado durante la venta.');
                }

                if ((int) $producto['stock'] < (int) $item['cantidad']) {
                    throw new RuntimeException('Stock insuficiente para ' . $producto['nombre']);
                }

                $precio = (float) $producto['precio_venta'];
                $subtotal = $precio * (int) $item['cantidad'];
                $total += $subtotal;

                $lineas[] = [
                    'producto_id' => (int) $producto['id'],
                    'cantidad' => (int) $item['cantidad'],
                    'precio' => $precio,
                    'subtotal' => $subtotal,
                ];
            }

            $vuelto = $montoPagado - $total;
            if ($vuelto < 0) {
                $vuelto = 0;
            }

            $stmtVenta = $pdo->prepare('INSERT INTO ventas (total, monto_pagado, vuelto, usuario_id) VALUES (:total, :monto_pagado, :vuelto, :usuario_id)');
            $stmtVenta->execute([
                'total' => $total,
                'monto_pagado' => $montoPagado > 0 ? $montoPagado : $total,
                'vuelto' => $vuelto,
                'usuario_id' => (int) $_SESSION['user']['id'],
            ]);

            $ventaId = (int) $pdo->lastInsertId();

            $stmtDetalle = $pdo->prepare('INSERT INTO detalle_ventas (venta_id, producto_id, cantidad, precio_unitario, subtotal) VALUES (:venta, :producto, :cantidad, :precio, :subtotal)');
            $stmtStock = $pdo->prepare('UPDATE productos SET stock = stock - :cantidad WHERE id = :id');
            $stmtMov = $pdo->prepare('INSERT INTO movimientos_stock (producto_id, tipo, cantidad) VALUES (:producto_id, :tipo, :cantidad)');

            foreach ($lineas as $linea) {
                $stmtDetalle->execute([
                    'venta' => $ventaId,
                    'producto' => $linea['producto_id'],
                    'cantidad' => $linea['cantidad'],
                    'precio' => $linea['precio'],
                    'subtotal' => $linea['subtotal'],
                ]);

                $stmtStock->execute([
                    'cantidad' => $linea['cantidad'],
                    'id' => $linea['producto_id'],
                ]);

                $stmtMov->execute([
                    'producto_id' => $linea['producto_id'],
                    'tipo' => 'salida',
                    'cantidad' => $linea['cantidad'],
                ]);
            }

            $pdo->commit();

            $_SESSION['cart'] = [];
            app_log('info', 'Venta registrada', ['venta_id' => $ventaId, 'usuario_id' => (int) $_SESSION['user']['id']]);
            redirect('/app/ventas/ticket.php?id=' . $ventaId . '&auto=1');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            app_log('error', 'Error al registrar venta', ['error' => $e->getMessage()]);
            set_flash('error', 'No se pudo completar la venta: ' . $e->getMessage());
            redirect('/app/ventas/index.php');
        }
    }
}

$cart = $_SESSION['cart'];
$total = 0.0;
foreach ($cart as $item) {
    $total += (float) $item['precio'] * (int) $item['cantidad'];
}

render_header('Ventas');
?>

<div class="row g-4">
    <div class="col-12 col-lg-7">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h4">Nueva venta</h1>
                <form method="get" class="row g-2 mb-3" id="searchForm">
                    <div class="col-12 col-md-8">
                        <label class="form-label">Buscar por nombre o escanear codigo de barras</label>
                        <input id="barcodeInput" type="text" name="q" class="form-control" placeholder="Escanea o escribe codigo" value="<?php echo e($q); ?>" autofocus>
                    </div>
                    <div class="col-6 col-md-2 d-grid align-items-end">
                        <button class="btn btn-primary" type="submit">Buscar</button>
                    </div>
                    <div class="col-6 col-md-2 d-grid align-items-end">
                        <a class="btn btn-outline-secondary" href="<?php echo BASE_URL; ?>/app/ventas/index.php">Limpiar</a>
                    </div>
                </form>
                <form method="post" id="scanForm" class="d-none">
                    <input type="hidden" name="action" value="add_by_barcode">
                    <input type="hidden" name="barcode" id="scanBarcode">
                </form>

                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Codigo</th>
                            <th>Precio</th>
                            <th>Stock</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$resultados): ?>
                            <tr><td colspan="5" class="text-muted">Busca un producto para agregarlo.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($resultados as $r): ?>
                            <tr class="product-row" data-product-id="<?php echo (int) $r['id']; ?>">
                                <td>
                                    <div class="product-name-wrapper" style="position: relative; display: inline-block;">
                                        <span class="product-name-link"><?php echo e($r['nombre']); ?></span>
                                        <?php if (!empty($r['imagen'])): ?>
                                            <div class="product-image-preview">
                                                <img src="<?php echo e($r['imagen']); ?>" alt="<?php echo e($r['nombre']); ?>">
                                            </div>
                                        <?php else: ?>
                                            <div class="product-image-preview product-image-placeholder">
                                                <span>Sin foto</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?php echo e($r['codigo_barras'] ?? 'Sin codigo'); ?></td>
                                <td><?php echo e(format_gs((float) $r['precio_venta'])); ?></td>
                                <td><?php echo (int) $r['stock']; ?></td>
                                <td>
                                    <form method="post" class="m-0">
                                        <input type="hidden" name="action" value="add_item">
                                        <input type="hidden" name="producto_id" value="<?php echo (int) $r['id']; ?>">
                                        <button class="btn btn-sm btn-success" type="submit" <?php echo (int) $r['stock'] <= 0 ? 'disabled' : ''; ?>>Agregar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5">Carrito</h2>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Cant.</th>
                            <th>Subt.</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$cart): ?>
                            <tr><td colspan="4" class="text-muted">Sin productos en carrito.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($cart as $item): ?>
                            <tr>
                                <td><?php echo e($item['nombre']); ?></td>
                                <td>
                                    <form method="post" class="d-flex gap-1">
                                        <input type="hidden" name="action" value="update_qty">
                                        <input type="hidden" name="producto_id" value="<?php echo (int) $item['id']; ?>">
                                        <input type="number" class="form-control form-control-sm" style="max-width:80px" min="1" name="cantidad" value="<?php echo (int) $item['cantidad']; ?>">
                                        <button class="btn btn-sm btn-outline-primary" type="submit">OK</button>
                                    </form>
                                </td>
                                <td><?php echo e(format_gs((float) $item['precio'] * (int) $item['cantidad'])); ?></td>
                                <td>
                                    <form method="post">
                                        <input type="hidden" name="action" value="remove_item">
                                        <input type="hidden" name="producto_id" value="<?php echo (int) $item['id']; ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit">X</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <hr>
                <hr>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong>Total</strong>
                    <strong class="fs-5 text-primary"><?php echo e(format_gs($total)); ?></strong>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-12">
                        <label class="form-label">Monto pagado</label>
                        <input type="number" id="montoPagado" class="form-control" placeholder="Ingresa el monto pagado" step="500" min="0" value="<?php echo !$cart ? '0' : format_gs($total); ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Vuelto</label>
                        <div class="input-group">
                            <span class="input-group-text">Gs</span>
                            <input type="text" id="vuelto" class="form-control" placeholder="0" readonly>
                        </div>
                    </div>
                </div>
                <div class="d-grid gap-2">
                    <form method="post" id="checkoutForm">
                        <input type="hidden" name="action" value="checkout">
                        <input type="hidden" name="monto_pagado" id="montoPagadoHidden" value="0">
                        <button class="btn btn-success w-100" type="submit" <?php echo !$cart ? 'disabled' : ''; ?>>Cobrar</button>
                    </form>
                    <form method="post">
                        <input type="hidden" name="action" value="clear_cart">
                        <button class="btn btn-outline-secondary w-100" type="submit" <?php echo !$cart ? 'disabled' : ''; ?>>Vaciar carrito</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const barcodeInput = document.getElementById('barcodeInput');
const searchForm = document.getElementById('searchForm');
const scanForm = document.getElementById('scanForm');
const scanBarcode = document.getElementById('scanBarcode');
const montoPagadoInput = document.getElementById('montoPagado');
const vueltoDisplay = document.getElementById('vuelto');
const montoPagadoHidden = document.getElementById('montoPagadoHidden');
const checkoutForm = document.getElementById('checkoutForm');
const totalText = '<?php echo !$cart ? '0' : format_gs($total); ?>';
const totalNumeric = <?php echo (int)$total; ?>;

if (barcodeInput && searchForm && scanForm && scanBarcode) {
    barcodeInput.focus();

  barcodeInput.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
      e.preventDefault();

            const value = barcodeInput.value.trim();
            const looksLikeBarcode = /^\d{6,}$/.test(value);

            if (looksLikeBarcode) {
                scanBarcode.value = value;
                scanForm.submit();
                return;
            }

            searchForm.submit();
    }
  });
}

if (montoPagadoInput && vueltoDisplay && montoPagadoHidden && checkoutForm) {
    function actualizarVuelto() {
        const montoPagado = parseFloat(montoPagadoInput.value) || 0;
        const vuelto = Math.max(0, montoPagado - totalNumeric);
        vueltoDisplay.value = vuelto.toLocaleString('es-PY', {minimumFractionDigits: 0, maximumFractionDigits: 0});
        montoPagadoHidden.value = montoPagado;
    }

    montoPagadoInput.addEventListener('input', actualizarVuelto);
    montoPagadoInput.addEventListener('change', actualizarVuelto);

    checkoutForm.addEventListener('submit', function(e) {
        const montoPagado = parseFloat(montoPagadoInput.value) || 0;
        if (montoPagado === 0) {
            e.preventDefault();
            alert('Por favor ingresa el monto pagado');
            montoPagadoInput.focus();
        }
    });

    actualizarVuelto();
}
</script>

<?php render_footer();
