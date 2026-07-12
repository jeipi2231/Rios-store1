<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/schema.php';

require_login();
ensure_extended_schema();

if (isset($_GET['ajax_clientes']) && $_GET['ajax_clientes'] === '1') {
    header('Content-Type: application/json; charset=utf-8');

    $pdo = getPDO();
    $qCliente = trim($_GET['q'] ?? '');
    $stmtClientes = $pdo->prepare(
        'SELECT id, nombre, apellido, ruc
         FROM clientes
            WHERE nombre LIKE ? OR apellido LIKE ? OR ruc LIKE ?
         ORDER BY nombre ASC, apellido ASC
         LIMIT 15'
    );
        $term = '%' . $qCliente . '%';
        $stmtClientes->execute([$term, $term, $term]);

    echo json_encode($stmtClientes->fetchAll(), JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$pdo = getPDO();
$bankOptions = ['Itaú', 'Ueno', 'Continental', 'Familiar', 'Sudameris', 'Atlas', 'Basa', 'GNB'];
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

    if ($action === 'checkout' || $action === 'create_quote') {
        $cart = $_SESSION['cart'];
        if (!$cart) {
            set_flash('error', 'El carrito esta vacio.');
            redirect('/app/ventas/index.php');
        }

        $isQuote = $action === 'create_quote';
        $montoPagado = floatval($_POST['monto_pagado'] ?? 0);
        $clienteRuc = trim($_POST['cliente_ruc'] ?? '');
        $metodoPago = trim($_POST['metodo_pago'] ?? 'efectivo');
        $bancoPago = trim($_POST['banco_pago'] ?? '');
        $tipoEntrega = trim($_POST['tipo_entrega'] ?? 'retiro_tienda');
        if (!in_array($metodoPago, ['efectivo', 'qr', 'transferencia'], true)) {
            $metodoPago = 'efectivo';
        }
        if (!in_array($tipoEntrega, ['envio', 'retiro_tienda'], true)) {
            $tipoEntrega = 'retiro_tienda';
        }
        if ($metodoPago === 'efectivo') {
            $bancoPago = '';
        }
        if ($metodoPago !== 'efectivo' && $bancoPago === '') {
            set_flash('error', 'Debes indicar el banco para pagos por QR o transferencia.');
            redirect('/app/ventas/index.php');
        }
        $clienteId = null;
        $total = 0.0;

        try {
            $pdo->beginTransaction();

            $lineas = [];

            foreach ($cart as $item) {
                $sqlProducto = $isQuote
                    ? 'SELECT id, nombre, precio_venta FROM productos WHERE id = :id AND activo = 1 LIMIT 1'
                    : 'SELECT id, nombre, stock, precio_venta FROM productos WHERE id = :id AND activo = 1 FOR UPDATE';
                $stmt = $pdo->prepare($sqlProducto);
                $stmt->execute(['id' => $item['id']]);
                $producto = $stmt->fetch();

                if (!$producto) {
                    throw new RuntimeException('Producto no encontrado durante el proceso.');
                }

                if (!$isQuote && (int) $producto['stock'] < (int) $item['cantidad']) {
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

            if ($metodoPago !== 'efectivo') {
                $montoPagado = $total;
                $vuelto = 0;
            } else {
                $vuelto = $montoPagado - $total;
                if ($vuelto < 0) {
                    $vuelto = 0;
                }
            }

            if ($clienteRuc !== '') {
                $stmtCliente = $pdo->prepare('SELECT id FROM clientes WHERE ruc = :ruc LIMIT 1');
                $stmtCliente->execute(['ruc' => $clienteRuc]);
                $cliente = $stmtCliente->fetch();

                if (!$cliente) {
                    throw new RuntimeException('No existe cliente con ese RUC.');
                }

                $clienteId = (int) $cliente['id'];
            }

            if ($isQuote) {
                $stmtCotizacion = $pdo->prepare('INSERT INTO cotizaciones (total, metodo_pago, banco_pago, tipo_entrega, cliente_id, usuario_id) VALUES (:total, :metodo_pago, :banco_pago, :tipo_entrega, :cliente_id, :usuario_id)');
                $stmtCotizacion->execute([
                    'total' => $total,
                    'metodo_pago' => $metodoPago,
                    'banco_pago' => $bancoPago !== '' ? $bancoPago : null,
                    'tipo_entrega' => $tipoEntrega,
                    'cliente_id' => $clienteId,
                    'usuario_id' => (int) $_SESSION['user']['id'],
                ]);

                $cotizacionId = (int) $pdo->lastInsertId();
                $stmtDetalle = $pdo->prepare('INSERT INTO detalle_cotizaciones (cotizacion_id, producto_id, cantidad, precio_unitario, subtotal) VALUES (:cotizacion, :producto, :cantidad, :precio, :subtotal)');

                foreach ($lineas as $linea) {
                    $stmtDetalle->execute([
                        'cotizacion' => $cotizacionId,
                        'producto' => $linea['producto_id'],
                        'cantidad' => $linea['cantidad'],
                        'precio' => $linea['precio'],
                        'subtotal' => $linea['subtotal'],
                    ]);
                }
            } else {
                $stmtVenta = $pdo->prepare('INSERT INTO ventas (total, monto_pagado, vuelto, metodo_pago, banco_pago, tipo_entrega, cliente_id, usuario_id) VALUES (:total, :monto_pagado, :vuelto, :metodo_pago, :banco_pago, :tipo_entrega, :cliente_id, :usuario_id)');
                $stmtVenta->execute([
                    'total' => $total,
                    'monto_pagado' => $montoPagado > 0 ? $montoPagado : $total,
                    'vuelto' => $vuelto,
                    'metodo_pago' => $metodoPago,
                    'banco_pago' => $bancoPago !== '' ? $bancoPago : null,
                    'tipo_entrega' => $tipoEntrega,
                    'cliente_id' => $clienteId,
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
            }

            $pdo->commit();

            $_SESSION['cart'] = [];
            if ($isQuote) {
                app_log('info', 'Cotizacion registrada', ['cotizacion_id' => $cotizacionId, 'usuario_id' => (int) $_SESSION['user']['id']]);
                redirect('/app/ventas/cotizacion.php?id=' . $cotizacionId . '&auto=0');
            }

            app_log('info', 'Venta registrada', ['venta_id' => $ventaId, 'usuario_id' => (int) $_SESSION['user']['id']]);
            redirect('/app/ventas/ticket.php?id=' . $ventaId . '&auto=1');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            app_log('error', $isQuote ? 'Error al registrar cotizacion' : 'Error al registrar venta', ['error' => $e->getMessage()]);
            set_flash('error', 'No se pudo completar el proceso: ' . $e->getMessage());
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
                            <th>Precio de venta</th>
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
                                    <div class="product-name-wrapper" style="position: relative;">
                                        <?php if (!empty($r['imagen'])): ?>
                                            <span class="product-thumb">
                                                <img src="<?php echo e(public_url($r['imagen'])); ?>" alt="<?php echo e($r['nombre']); ?>">
                                            </span>
                                        <?php else: ?>
                                            <span class="product-thumb placeholder">Sin foto</span>
                                        <?php endif; ?>
                                        <span class="product-name-link"><?php echo e($r['nombre']); ?></span>
                                        <?php if (!empty($r['imagen'])): ?>
                                            <div class="product-image-preview">
                                                <img src="<?php echo e(public_url($r['imagen'])); ?>" alt="<?php echo e($r['nombre']); ?>">
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
                        <button class="btn btn-outline-secondary w-100" type="button" data-bs-toggle="collapse" data-bs-target="#clientFinderCollapse" aria-expanded="false" aria-controls="clientFinderCollapse">
                            Cliente (opcional) - buscar por nombre o RUC
                        </button>
                    </div>
                    <div class="col-12">
                        <div class="collapse" id="clientFinderCollapse">
                            <div class="client-finder mt-2">
                                <label class="form-label">Buscar cliente</label>
                                <input type="text" id="clienteSearch" class="form-control" placeholder="Ejemplo: Lopez o 1234567-8">
                                <small class="text-muted">Puedes escribir nombre, apellido o RUC.</small>
                                <div id="clienteResults" class="list-group mt-2"></div>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <small id="clienteSelectedInfo" class="text-success"></small>
                                    <button type="button" id="clearClientBtn" class="btn btn-sm btn-outline-danger">Quitar cliente</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Metodo de pago</label>
                        <select id="metodoPago" class="form-select">
                            <option value="efectivo">Efectivo</option>
                            <option value="qr">QR</option>
                            <option value="transferencia">Transferencia</option>
                        </select>
                    </div>
                    <div class="col-12 d-none" id="bancoPagoWrapper">
                        <label class="form-label">Banco</label>
                        <input type="text" id="bancoPago" class="form-control" list="bancosDisponibles" placeholder="Escribe o selecciona un banco">
                        <datalist id="bancosDisponibles">
                            <?php foreach ($bankOptions as $bank): ?>
                                <option value="<?php echo e($bank); ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Tipo de entrega</label>
                        <select id="tipoEntrega" class="form-select">
                            <option value="retiro_tienda">Retiro en tienda</option>
                            <option value="envio">Envio</option>
                        </select>
                    </div>
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
                        <input type="hidden" name="action" id="documentActionHidden" value="checkout">
                        <input type="hidden" name="monto_pagado" id="montoPagadoHidden" value="0">
                        <input type="hidden" name="metodo_pago" id="metodoPagoHidden" value="efectivo">
                        <input type="hidden" name="banco_pago" id="bancoPagoHidden" value="">
                        <input type="hidden" name="tipo_entrega" id="tipoEntregaHidden" value="retiro_tienda">
                        <input type="hidden" name="cliente_ruc" id="clienteRucHidden" value="">
                        <button class="btn btn-dark w-100" type="submit" data-document-action="checkout" <?php echo !$cart ? 'disabled' : ''; ?>>Cobrar venta</button>
                        <button class="btn btn-outline-dark w-100 mt-2" type="submit" data-document-action="create_quote" <?php echo !$cart ? 'disabled' : ''; ?>>Generar presupuesto</button>
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
(function () {
    let activePopup = null;
    let activeWrapper = null;

    function hidePopup() {
        if (activePopup) {
            activePopup.remove();
            activePopup = null;
        }
        activeWrapper = null;
    }

    function positionPopup(wrapper, popup) {
        const rect = wrapper.getBoundingClientRect();
        const margin = 8;
        const popupWidth = 220;
        const popupHeight = 220;

        let left = rect.left;
        if (left + popupWidth > window.innerWidth - margin) {
            left = window.innerWidth - popupWidth - margin;
        }
        if (left < margin) {
            left = margin;
        }

        let top = rect.bottom + margin;
        if (top + popupHeight > window.innerHeight - margin) {
            top = rect.top - popupHeight - margin;
        }
        if (top < margin) {
            top = margin;
        }

        popup.style.left = left + 'px';
        popup.style.top = top + 'px';
    }

    function showPopup(wrapper) {
        const template = wrapper.querySelector('.product-image-preview');
        if (!template) {
            return;
        }

        hidePopup();
        activeWrapper = wrapper;
        activePopup = template.cloneNode(true);
        activePopup.classList.add('floating-product-preview');
        activePopup.style.display = 'flex';
        document.body.appendChild(activePopup);
        positionPopup(wrapper, activePopup);
    }

    document.querySelectorAll('.product-name-wrapper').forEach(function (wrapper) {
        wrapper.addEventListener('mouseenter', function () {
            if (window.matchMedia('(max-width: 768px)').matches) {
                return;
            }
            showPopup(wrapper);
        });

        wrapper.addEventListener('mouseleave', function () {
            hidePopup();
        });
    });

    window.addEventListener('scroll', hidePopup, true);
    window.addEventListener('resize', function () {
        if (activePopup && activeWrapper) {
            positionPopup(activeWrapper, activePopup);
        }
    });
})();
</script>

<script>
const barcodeInput = document.getElementById('barcodeInput');
const searchForm = document.getElementById('searchForm');
const scanForm = document.getElementById('scanForm');
const scanBarcode = document.getElementById('scanBarcode');
const montoPagadoInput = document.getElementById('montoPagado');
const metodoPagoInput = document.getElementById('metodoPago');
const bancoPagoInput = document.getElementById('bancoPago');
const bancoPagoWrapper = document.getElementById('bancoPagoWrapper');
const tipoEntregaInput = document.getElementById('tipoEntrega');
const vueltoDisplay = document.getElementById('vuelto');
const montoPagadoHidden = document.getElementById('montoPagadoHidden');
const metodoPagoHidden = document.getElementById('metodoPagoHidden');
const bancoPagoHidden = document.getElementById('bancoPagoHidden');
const tipoEntregaHidden = document.getElementById('tipoEntregaHidden');
const documentActionHidden = document.getElementById('documentActionHidden');
const clienteRucHidden = document.getElementById('clienteRucHidden');
const clienteSearchInput = document.getElementById('clienteSearch');
const clienteResults = document.getElementById('clienteResults');
const clienteSelectedInfo = document.getElementById('clienteSelectedInfo');
const clearClientBtn = document.getElementById('clearClientBtn');
const checkoutForm = document.getElementById('checkoutForm');
const totalText = '<?php echo !$cart ? '0' : format_gs($total); ?>';
const totalNumeric = <?php echo (int)$total; ?>;
const baseUrl = '<?php echo e(BASE_URL); ?>';

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
    function syncMetodoPago() {
        const metodo = metodoPagoInput ? metodoPagoInput.value : 'efectivo';
        if (metodoPagoHidden) {
            metodoPagoHidden.value = metodo;
        }

        if (bancoPagoWrapper && bancoPagoInput && bancoPagoHidden) {
            const needsBank = metodo !== 'efectivo';
            bancoPagoWrapper.classList.toggle('d-none', !needsBank);
            bancoPagoInput.required = needsBank;

            if (!needsBank) {
                bancoPagoInput.value = '';
                bancoPagoHidden.value = '';
            } else {
                bancoPagoHidden.value = bancoPagoInput.value.trim();
            }
        }

        if (metodo !== 'efectivo') {
            montoPagadoInput.value = totalNumeric;
            montoPagadoInput.readOnly = true;
        } else {
            montoPagadoInput.readOnly = false;
        }

        actualizarVuelto();
    }

    function actualizarVuelto() {
        const metodo = metodoPagoInput ? metodoPagoInput.value : 'efectivo';
        const montoPagado = parseFloat(montoPagadoInput.value) || 0;
        const vuelto = metodo === 'efectivo' ? Math.max(0, montoPagado - totalNumeric) : 0;
        vueltoDisplay.value = vuelto.toLocaleString('es-PY', {minimumFractionDigits: 0, maximumFractionDigits: 0});
        montoPagadoHidden.value = montoPagado;
        if (bancoPagoHidden && bancoPagoInput) {
            bancoPagoHidden.value = bancoPagoInput.value.trim();
        }
        if (tipoEntregaHidden && tipoEntregaInput) {
            tipoEntregaHidden.value = tipoEntregaInput.value;
        }
    }

    montoPagadoInput.addEventListener('input', actualizarVuelto);
    montoPagadoInput.addEventListener('change', actualizarVuelto);

    checkoutForm.querySelectorAll('button[data-document-action]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (documentActionHidden) {
                documentActionHidden.value = button.getAttribute('data-document-action') || 'checkout';
            }
        });
    });

    checkoutForm.addEventListener('submit', function(e) {
        const metodo = metodoPagoInput ? metodoPagoInput.value : 'efectivo';
        const currentAction = documentActionHidden ? documentActionHidden.value : 'checkout';
        if (metodoPagoHidden) {
            metodoPagoHidden.value = metodo;
        }
        if (bancoPagoHidden && bancoPagoInput) {
            bancoPagoHidden.value = bancoPagoInput.value.trim();
        }
        if (tipoEntregaHidden && tipoEntregaInput) {
            tipoEntregaHidden.value = tipoEntregaInput.value;
        }

        const montoPagado = parseFloat(montoPagadoInput.value) || 0;
        if (metodo !== 'efectivo' && bancoPagoInput && bancoPagoInput.value.trim() === '') {
            e.preventDefault();
            alert('Indica el banco del pago.');
            bancoPagoInput.focus();
            return;
        }

        if (currentAction === 'checkout' && metodo === 'efectivo' && montoPagado === 0) {
            e.preventDefault();
            alert('Por favor ingresa el monto pagado');
            montoPagadoInput.focus();
            return;
        }
    });

    if (metodoPagoInput) {
        metodoPagoInput.addEventListener('change', syncMetodoPago);
    }
    if (bancoPagoInput) {
        bancoPagoInput.addEventListener('input', actualizarVuelto);
    }
    if (tipoEntregaInput) {
        tipoEntregaInput.addEventListener('change', actualizarVuelto);
    }

    syncMetodoPago();
}

if (clienteSearchInput && clienteResults && clienteRucHidden) {
    let searchTimer = null;

    const renderClients = function (items) {
        clienteResults.innerHTML = '';

        if (!items.length) {
            clienteResults.innerHTML = '<div class="list-group-item text-muted">Sin coincidencias.</div>';
            return;
        }

        items.forEach(function (c) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'list-group-item list-group-item-action client-result-item';
            btn.innerHTML = '<strong>' + c.nombre + ' ' + c.apellido + '</strong><br><small>RUC: ' + c.ruc + '</small>';
            btn.addEventListener('click', function () {
                clienteRucHidden.value = c.ruc;
                clienteSelectedInfo.textContent = 'Cliente seleccionado: ' + c.nombre + ' ' + c.apellido + ' (' + c.ruc + ')';
                clienteSearchInput.value = c.nombre + ' ' + c.apellido + ' - ' + c.ruc;
                clienteResults.innerHTML = '';
            });
            clienteResults.appendChild(btn);
        });
    };

    clienteSearchInput.addEventListener('input', function () {
        const term = clienteSearchInput.value.trim();

        if (searchTimer) {
            clearTimeout(searchTimer);
        }

        if (term.length < 2) {
            clienteResults.innerHTML = '';
            if (clienteRucHidden.value === '') {
                clienteSelectedInfo.textContent = '';
            }
            return;
        }

        searchTimer = setTimeout(function () {
            fetch(baseUrl + '/app/ventas/index.php?ajax_clientes=1&q=' + encodeURIComponent(term))
                .then(function (r) { return r.json(); })
                .then(function (items) {
                    renderClients(Array.isArray(items) ? items : []);
                })
                .catch(function () {
                    clienteResults.innerHTML = '<div class="list-group-item text-danger">No se pudo buscar clientes.</div>';
                });
        }, 220);
    });

    clearClientBtn.addEventListener('click', function () {
        clienteRucHidden.value = '';
        clienteSearchInput.value = '';
        clienteSelectedInfo.textContent = '';
        clienteResults.innerHTML = '';
    });
}
</script>

<?php render_footer();
