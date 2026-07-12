<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$titulo_pagina = 'Cajero';

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$mensaje = '';
$q = trim($_GET['q'] ?? '');

function cart_qty_for_product(array $cart, int $productId): float {
    $qty = 0.0;
    foreach ($cart as $item) {
        if ((int)$item['producto_id'] === $productId) {
            $qty += (float)$item['cantidad'];
        }
    }
    return $qty;
}

function is_valid_ean13(string $digits): bool {
  if (!preg_match('/^\d{13}$/', $digits)) {
    return false;
  }

  $sum = 0;
  for ($i = 0; $i < 12; $i++) {
    $n = (int)$digits[$i];
    $sum += ($i % 2 === 0) ? $n : $n * 3;
  }
  $check = (10 - ($sum % 10)) % 10;

  return $check === (int)$digits[12];
}

function parse_scale_weight_barcode(string $barcode): ?array {
  $digits = preg_replace('/\D+/', '', $barcode);
  $len = strlen($digits);
  if ($len !== 12 && $len !== 13) {
    return null;
  }

  $prefijo = substr($digits, 0, 7);
  if (!preg_match('/^\d{7}$/', $prefijo)) {
    return null;
  }

  $candidatos = [];
  $format = '';
  $checksumOk = null;

  if ($len === 12) {
    // Formato A: prefijo(7) + gramos(5)
    $pesoRaw = (int)substr($digits, 7, 5);
    if ($pesoRaw > 0) {
      $candidatos[] = [
        'peso_kg' => $pesoRaw / 1000,
        'format' => '12',
      ];
    }
    $format = '12';
  } else {
    $checksumOk = is_valid_ean13($digits);
    if ($checksumOk) {
      // Formato B: prefijo(7) + gramos(5) + check EAN
      $pesoRaw = (int)substr($digits, 7, 5);
      if ($pesoRaw > 0) {
        $candidatos[] = [
          'peso_kg' => $pesoRaw / 1000,
          'format' => '13_ean',
        ];
      }
      $format = '13_ean';
    } else {
      // Formato ambiguo: algunas balanzas usan 7+6, otras 7+5+1 sin checksum EAN válido.
      $pesoRaw6 = (int)substr($digits, 7, 6);
      $pesoRaw5 = (int)substr($digits, 7, 5);
      if ($pesoRaw6 > 0) {
        $candidatos[] = [
          'peso_kg' => $pesoRaw6 / 1000,
          'format' => '13_custom_6',
        ];
      }
      if ($pesoRaw5 > 0) {
        $candidatos[] = [
          'peso_kg' => $pesoRaw5 / 1000,
          'format' => '13_custom_5',
        ];
      }
      $format = '13_custom';
    }
  }

  if (empty($candidatos)) {
    return null;
  }

  return [
    'prefijo' => $prefijo,
    'candidatos' => $candidatos,
    'raw' => $digits,
    'checksum_ok' => $checksumOk,
    'format' => $format,
  ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $mensaje = '<div class="alert alert-danger">Token CSRF inválido.</div>';
    } else {
        $accion = $_POST['accion'] ?? '';

        if ($accion === 'scan') {
            $scan = trim($_POST['codigo_scan'] ?? '');

            if ($scan !== '') {
                $stmt = $pdo->prepare(
                    "SELECT id, codigo, codigo_barras, nombre, precio_venta, stock, unidad, es_pesable, prefijo_balanza
                     FROM productos
                     WHERE codigo = ? OR codigo_barras = ?
                     LIMIT 1"
                );
                $stmt->execute([$scan, $scan]);
                $producto = $stmt->fetch();

                if ($producto) {
                    $cart = $_SESSION['cart'];
                    $pid = (int)$producto['id'];
                    $esPesable = (int)$producto['es_pesable'] === 1;

                    if ($esPesable) {
                        $mensaje = '<div class="alert alert-warning">Ese producto es pesable. Debes escanear la etiqueta de balanza.</div>';
                    } else {
                        $qtyEnCarrito = cart_qty_for_product($cart, $pid);
                        if ($qtyEnCarrito + 1 > (float)$producto['stock']) {
                            $mensaje = '<div class="alert alert-warning">Stock insuficiente para agregar este producto.</div>';
                        } else {
                            $key = 'p_' . $pid;
                            if (!isset($cart[$key])) {
                                $cart[$key] = [
                                    'key' => $key,
                                    'producto_id' => $pid,
                                    'codigo' => $producto['codigo'],
                                    'nombre' => $producto['nombre'],
                                    'unidad' => $producto['unidad'] ?: 'unidad',
                                    'precio_unitario' => (float)$producto['precio_venta'],
                                    'cantidad' => 0.0,
                                    'tipo' => 'normal',
                                    'codigo_escaneado' => $scan,
                                ];
                            }
                            $cart[$key]['cantidad'] = (float)$cart[$key]['cantidad'] + 1.0;
                            $_SESSION['cart'] = $cart;
                            $mensaje = '<div class="alert alert-success">Producto agregado al carrito.</div>';
                        }
                    }
                } else {
                    $scaleData = parse_scale_weight_barcode($scan);
                    if ($scaleData) {
                        $stmt = $pdo->prepare(
                            "SELECT id, codigo, nombre, precio_venta, stock, unidad
                             FROM productos
                             WHERE es_pesable = 1 AND prefijo_balanza = ?
                             LIMIT 1"
                        );
                        $stmt->execute([$scaleData['prefijo']]);
                        $productoPesable = $stmt->fetch();

                        if ($productoPesable) {
                            $cart = $_SESSION['cart'];
                            $pid = (int)$productoPesable['id'];
                            $qtyEnCarrito = cart_qty_for_product($cart, $pid);
                          $stockDisponible = (float)$productoPesable['stock'];
                          $restante = max(0.0, $stockDisponible - $qtyEnCarrito);

                          $peso = 0.0;
                          $formatoElegido = '';
                          $candidatos = $scaleData['candidatos'] ?? [];

                          // Si el código es ambiguo, prioriza una lectura que entre en stock disponible.
                          $candidatosEnStock = array_values(array_filter($candidatos, function ($c) use ($restante) {
                            return (float)($c['peso_kg'] ?? 0) <= $restante;
                          }));
                          if (!empty($candidatosEnStock)) {
                            $seleccion = $candidatosEnStock[0];
                            foreach ($candidatosEnStock as $cand) {
                              if (($cand['format'] ?? '') === '13_custom_6') {
                                $seleccion = $cand;
                                break;
                              }
                            }
                            $peso = (float)$seleccion['peso_kg'];
                            $formatoElegido = (string)($seleccion['format'] ?? '');
                          } elseif (!empty($candidatos)) {
                            $seleccion = $candidatos[0];
                            $peso = (float)$seleccion['peso_kg'];
                            $formatoElegido = (string)($seleccion['format'] ?? '');
                          }

                          if ($peso <= 0) {
                            $mensaje = '<div class="alert alert-danger">No se pudo interpretar el peso del código escaneado.</div>';
                          } elseif ($qtyEnCarrito + $peso > $stockDisponible) {
                            $mensaje = '<div class="alert alert-warning">Stock insuficiente para ese producto de balanza. Escaneado: ' . fmt_qty($peso) . ' kg · disponible: ' . fmt_qty($restante) . ' kg.</div>';
                            } else {
                                $key = 'b_' . $pid . '_' . $scaleData['raw'];
                                $precioKg = (float)$productoPesable['precio_venta'];
                                $subtotal = $precioKg * $peso;

                                $cart[$key] = [
                                    'key' => $key,
                                    'producto_id' => $pid,
                                    'codigo' => $productoPesable['codigo'],
                                    'nombre' => $productoPesable['nombre'],
                                    'unidad' => $productoPesable['unidad'] ?: 'kg',
                                    'precio_unitario' => $precioKg,
                                    'cantidad' => $peso,
                                    'tipo' => 'balanza_peso',
                                    'subtotal_fijo' => $subtotal,
                                    'codigo_escaneado' => $scaleData['raw'],
                                ];
                                $_SESSION['cart'] = $cart;
                                if ($formatoElegido === '13_custom_6') {
                                  $mensaje = '<div class="alert alert-warning">Carne/pesable agregado: ' . fmt_qty($peso) . ' kg. Detectado formato de balanza 13 dígitos (prefijo + 6 de peso).</div>';
                                } elseif ($formatoElegido === '13_custom_5') {
                                  $mensaje = '<div class="alert alert-warning">Carne/pesable agregado: ' . fmt_qty($peso) . ' kg. Detectado formato de balanza 13 dígitos (prefijo + 5 de peso).</div>';
                                } elseif (($scaleData['checksum_ok'] ?? null) === false) {
                                  $mensaje = '<div class="alert alert-warning">Carne/pesable agregado: ' . fmt_qty($peso) . ' kg. Aviso: la etiqueta no pasó validación EAN-13, pero el prefijo coincide con un producto de balanza.</div>';
                                } elseif ($formatoElegido === '12') {
                                  $mensaje = '<div class="alert alert-success">Carne/pesable agregado: ' . fmt_qty($peso) . ' kg (etiqueta de 12 dígitos).</div>';
                                } else {
                                  $mensaje = '<div class="alert alert-success">Carne/pesable agregado: ' . fmt_qty($peso) . ' kg.</div>';
                                }
                            }
                        } else {
                                $mensaje = '<div class="alert alert-danger">No existe producto pesable asociado al prefijo ' . e($scaleData['prefijo']) . '.</div>';
                        }
                    } else {
                              $mensaje = '<div class="alert alert-danger">Código no encontrado. Para balanza usa 12 o 13 dígitos (prefijo de 7 + peso en gramos).</div>';
                    }
                }
            }
              } elseif ($accion === 'add_pesable_manual') {
                $productoId = (int)($_POST['producto_id'] ?? 0);
                $gramos = (int)($_POST['peso_gramos'] ?? 0);

                if ($productoId <= 0 || $gramos <= 0) {
                  $mensaje = '<div class="alert alert-danger">Debes seleccionar un producto pesable y un peso en gramos mayor a cero.</div>';
                } else {
                  $stmt = $pdo->prepare(
                    "SELECT id, codigo, nombre, precio_venta, stock, unidad
                     FROM productos
                     WHERE id = ? AND es_pesable = 1
                     LIMIT 1"
                  );
                  $stmt->execute([$productoId]);
                  $productoPesable = $stmt->fetch();

                  if (!$productoPesable) {
                    $mensaje = '<div class="alert alert-danger">El producto seleccionado no existe o no es pesable.</div>';
                  } else {
                    $peso = $gramos / 1000;
                    $cart = $_SESSION['cart'];
                    $pid = (int)$productoPesable['id'];
                    $qtyEnCarrito = cart_qty_for_product($cart, $pid);

                    if ($qtyEnCarrito + $peso > (float)$productoPesable['stock']) {
                      $disponible = max(0, (float)$productoPesable['stock'] - $qtyEnCarrito);
                      $mensaje = '<div class="alert alert-warning">Stock insuficiente. Disponible: ' . fmt_qty($disponible) . ' kg.</div>';
                    } else {
                      $key = 'bm_' . $pid . '_' . time() . '_' . random_int(100, 999);
                      $precioKg = (float)$productoPesable['precio_venta'];
                      $subtotal = $precioKg * $peso;

                      $cart[$key] = [
                        'key' => $key,
                        'producto_id' => $pid,
                        'codigo' => $productoPesable['codigo'],
                        'nombre' => $productoPesable['nombre'],
                        'unidad' => $productoPesable['unidad'] ?: 'kg',
                        'precio_unitario' => $precioKg,
                        'cantidad' => $peso,
                        'tipo' => 'balanza_peso',
                        'subtotal_fijo' => $subtotal,
                        'codigo_escaneado' => 'MANUAL:' . $gramos . 'g',
                      ];

                      $_SESSION['cart'] = $cart;
                      $mensaje = '<div class="alert alert-success">Producto pesable agregado manualmente: ' . fmt_qty($peso) . ' kg.</div>';
                    }
                  }
                }
        } elseif ($accion === 'remove_item') {
            $key = $_POST['key'] ?? '';
            if ($key !== '' && isset($_SESSION['cart'][$key])) {
                unset($_SESSION['cart'][$key]);
            }
        } elseif ($accion === 'update_qty') {
            $key = $_POST['key'] ?? '';
          $cantidadRaw = (float)($_POST['cantidad'] ?? 0);

            if ($key !== '' && isset($_SESSION['cart'][$key])) {
                $item = $_SESSION['cart'][$key];
                if (($item['tipo'] ?? '') !== 'normal') {
                    $mensaje = '<div class="alert alert-info">Los ítems de balanza no se editan manualmente. Reescanea o elimina.</div>';
                } else {
                  $cantidad = (float)(int)round($cantidadRaw);

                  if ($cantidad <= 0) {
                    unset($_SESSION['cart'][$key]);
                  } else {
                    $pid = (int)$item['producto_id'];
                    $stmt = $pdo->prepare("SELECT stock FROM productos WHERE id = ?");
                    $stmt->execute([$pid]);
                    $stock = (float)$stmt->fetchColumn();

                    $cartTemp = $_SESSION['cart'];
                    $cartTemp[$key]['cantidad'] = 0.0;
                    $qtyOtros = cart_qty_for_product($cartTemp, $pid);

                    if ($qtyOtros + $cantidad > $stock) {
                        $mensaje = '<div class="alert alert-warning">La cantidad supera el stock disponible.</div>';
                    } else {
                        $_SESSION['cart'][$key]['cantidad'] = $cantidad;
                    }
                    }
                }
            }
        } elseif ($accion === 'clear_cart') {
            $_SESSION['cart'] = [];
            $mensaje = '<div class="alert alert-info">Carrito vaciado.</div>';
        } elseif ($accion === 'checkout') {
            $cart = $_SESSION['cart'];
            if (empty($cart)) {
                $mensaje = '<div class="alert alert-warning">El carrito está vacío.</div>';
            } else {
                $montoPagado = (float)($_POST['monto_pagado'] ?? 0);
                $metodoPago = trim($_POST['metodo_pago'] ?? 'efectivo');
                $observacion = trim($_POST['observacion'] ?? '');

                $total = 0.0;
                foreach ($cart as $item) {
                    if (($item['tipo'] ?? '') === 'balanza_peso') {
                        $subtotal = (float)($item['subtotal_fijo'] ?? 0);
                    } else {
                        $subtotal = (float)$item['precio_unitario'] * (float)$item['cantidad'];
                    }
                    $total += $subtotal;
                }

                if ($montoPagado <= 0) {
                    $montoPagado = $total;
                }
                if ($montoPagado < $total) {
                    $mensaje = '<div class="alert alert-danger">El monto pagado no puede ser menor al total.</div>';
                } else {
                    try {
                        $pdo->beginTransaction();

                        $qtyPorProducto = [];
                        foreach ($cart as $item) {
                            $pid = (int)$item['producto_id'];
                            $qtyPorProducto[$pid] = ($qtyPorProducto[$pid] ?? 0) + (float)$item['cantidad'];
                        }

                        foreach ($qtyPorProducto as $pid => $qty) {
                            $stmtStock = $pdo->prepare("SELECT nombre, stock FROM productos WHERE id = ? FOR UPDATE");
                            $stmtStock->execute([$pid]);
                            $p = $stmtStock->fetch();

                            if (!$p) {
                                throw new RuntimeException('Producto inexistente durante el cobro.');
                            }
                            if ((float)$p['stock'] < $qty) {
                                throw new RuntimeException('Stock insuficiente para: ' . $p['nombre']);
                            }
                        }

                        $vuelto = $montoPagado - $total;
                        $stmtVenta = $pdo->prepare(
                            "INSERT INTO ventas (total, monto_pagado, vuelto, metodo_pago, observacion, usuario_id)
                             VALUES (?, ?, ?, ?, ?, ?)"
                        );
                        $stmtVenta->execute([
                            $total,
                            $montoPagado,
                            $vuelto,
                            $metodoPago,
                            $observacion !== '' ? $observacion : null,
                            (int)($_SESSION['user']['id'] ?? 0),
                        ]);
                        $ventaId = (int)$pdo->lastInsertId();

                        $stmtDetalle = $pdo->prepare(
                            "INSERT INTO detalle_ventas (venta_id, producto_id, cantidad, precio_unitario, subtotal, codigo_escaneado)
                             VALUES (?, ?, ?, ?, ?, ?)"
                        );
                        $stmtMov = $pdo->prepare(
                            "INSERT INTO movimientos (producto_id, tipo, cantidad, motivo, usuario_id)
                             VALUES (?, 'salida', ?, ?, ?)"
                        );

                        foreach ($cart as $item) {
                            $cantidad = (float)$item['cantidad'];
                            if (($item['tipo'] ?? '') === 'balanza_peso') {
                                $subtotal = (float)($item['subtotal_fijo'] ?? 0);
                            } else {
                                $subtotal = (float)$item['precio_unitario'] * $cantidad;
                            }

                            $stmtDetalle->execute([
                                $ventaId,
                                (int)$item['producto_id'],
                                $cantidad,
                                (float)$item['precio_unitario'],
                                $subtotal,
                                $item['codigo_escaneado'] ?? null,
                            ]);

                            $stmtMov->execute([
                                (int)$item['producto_id'],
                                $cantidad,
                                'Venta caja #' . $ventaId,
                                (int)($_SESSION['user']['id'] ?? 0),
                            ]);
                        }

                        $stmtUpdateStock = $pdo->prepare("UPDATE productos SET stock = stock - ? WHERE id = ?");
                        foreach ($qtyPorProducto as $pid => $qty) {
                            $stmtUpdateStock->execute([$qty, $pid]);
                        }

                        $pdo->commit();
                        $_SESSION['cart'] = [];
                        header('Location: ticket.php?id=' . $ventaId . '&auto=1');
                        exit;
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        $mensaje = '<div class="alert alert-danger">No se pudo registrar la venta: ' . e($e->getMessage()) . '</div>';
                    }
                }
            }
        }
    }
}

$resultados = [];
if ($q !== '') {
    $stmt = $pdo->prepare(
        "SELECT id, codigo, codigo_barras, nombre, precio_venta, stock, unidad, es_pesable, prefijo_balanza
         FROM productos
         WHERE nombre LIKE ? OR codigo LIKE ? OR codigo_barras = ?
         ORDER BY nombre
         LIMIT 20"
    );
    $stmt->execute(['%' . $q . '%', '%' . $q . '%', $q]);
    $resultados = $stmt->fetchAll();
}

$cart = $_SESSION['cart'];
$total = 0.0;
foreach ($cart as $item) {
    if (($item['tipo'] ?? '') === 'balanza_peso') {
        $total += (float)($item['subtotal_fijo'] ?? 0);
    } else {
        $total += (float)$item['precio_unitario'] * (float)$item['cantidad'];
    }
}

require __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h3 class="mb-1"><i class="bi bi-upc-scan me-2"></i>Cajero</h3>
    <p class="text-muted mb-0">Normal por unidades y carnicería por kilos con escaneo de balanza.</p>
  </div>
</div>

<?= $mensaje ?>

<div class="alert alert-light border mb-3">
  <strong>Cómo cobrar (paso a paso):</strong>
  <span class="d-block">1) Producto normal: escanea su código de barras y suma unidades.</span>
  <span class="d-block">2) Producto pesable (carne): escanea la etiqueta de balanza (13 dígitos que empieza en 2).</span>
  <span class="d-block">3) Revisa total, carga monto pagado y pulsa <em>Cobrar</em>.</span>
</div>

<div class="row g-3">
  <div class="col-12 col-lg-7">
    <div class="card cashier-panel h-100">
      <div class="card-body">
        <form method="post" id="scanForm" class="row g-2 align-items-end mb-3">
          <?= csrf_field() ?>
          <input type="hidden" name="accion" value="scan">
          <div class="col-12 col-md-9">
            <label class="form-label">Escáner / Código</label>
            <input type="text" name="codigo_scan" id="codigo_scan" class="form-control form-control-lg" placeholder="Escanea producto normal o etiqueta de balanza" autofocus>
            <small class="text-muted">Balanza: 12 dígitos (7+5) o 13 dígitos (7+5+control, o 7+6 en algunos modelos).</small>
          </div>
          <div class="col-12 col-md-3 d-grid">
            <button class="btn btn-primary btn-lg"><i class="bi bi-plus-circle me-1"></i>Agregar</button>
          </div>
        </form>

        <form method="get" class="row g-2 mb-3">
          <div class="col-md-9">
            <input type="text" name="q" class="form-control" placeholder="Buscar por nombre/código" value="<?= e($q) ?>">
          </div>
          <div class="col-md-3 d-grid">
            <button class="btn btn-outline-primary"><i class="bi bi-search me-1"></i>Buscar</button>
          </div>
        </form>

        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Producto</th>
                <th>Código</th>
                <th class="text-end">Precio</th>
                <th class="text-center">Stock</th>
                <th class="text-end">Acción</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($resultados)): ?>
                <tr><td colspan="5" class="text-muted">Usa el buscador o escáner para agregar productos.</td></tr>
              <?php else: ?>
                <?php foreach ($resultados as $r): ?>
                  <tr class="<?= (int)$r['es_pesable'] === 1 ? 'table-warning-subtle' : '' ?>">
                    <td>
                      <div class="fw-semibold"><?= e($r['nombre']) ?></div>
                      <small class="text-muted">
                        <?= e($r['unidad']) ?>
                        <?php if ((int)$r['es_pesable'] === 1): ?>
                          · pesable · prefijo <?= e($r['prefijo_balanza'] ?: '-') ?>
                        <?php endif; ?>
                      </small>
                    </td>
                    <td><code><?= e($r['codigo_barras'] ?: $r['codigo']) ?></code></td>
                    <td class="text-end"><?= money($r['precio_venta']) ?><?= (int)$r['es_pesable'] === 1 ? ' /kg' : '' ?></td>
                    <td class="text-center"><?= fmt_qty($r['stock']) ?></td>
                    <td class="text-end">
                      <?php if ((int)$r['es_pesable'] === 1): ?>
                        <button
                          type="button"
                          class="btn btn-sm btn-outline-warning"
                          onclick='abrirPesableManual(
                            <?= (int)$r['id'] ?>,
                            <?= json_encode($r['nombre'], JSON_UNESCAPED_UNICODE) ?>,
                            <?= (float)$r['precio_venta'] ?>,
                            <?= (float)$r['stock'] ?>,
                            <?= json_encode($r['unidad'] ?: 'kg', JSON_UNESCAPED_UNICODE) ?>
                          )'
                        >
                          <i class="bi bi-calculator me-1"></i>Cargar gramos
                        </button>
                      <?php else: ?>
                        <form method="post" class="d-inline">
                          <?= csrf_field() ?>
                          <input type="hidden" name="accion" value="scan">
                          <input type="hidden" name="codigo_scan" value="<?= e($r['codigo_barras'] ?: $r['codigo']) ?>">
                          <button class="btn btn-sm btn-outline-primary" <?= (float)$r['stock'] <= 0 ? 'disabled' : '' ?>>Agregar</button>
                        </form>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-5">
    <div class="card cashier-panel h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-cart3 me-2"></i>Carrito</span>
        <form method="post" class="m-0">
          <?= csrf_field() ?>
          <input type="hidden" name="accion" value="clear_cart">
          <button class="btn btn-sm btn-outline-danger" <?= empty($cart) ? 'disabled' : '' ?>>Vaciar</button>
        </form>
      </div>
      <div class="card-body">
        <?php if (empty($cart)): ?>
          <div class="empty-state py-4">
            <i class="bi bi-cart-x d-block mb-2"></i>
            Carrito vacío
          </div>
        <?php else: ?>
          <div class="table-responsive mb-3">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Ítem</th>
                  <th class="text-center">Cantidad</th>
                  <th class="text-end">Subt.</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($cart as $item): ?>
                  <?php
                    $esPeso = ($item['tipo'] ?? '') === 'balanza_peso';
                    $cantidad = (float)$item['cantidad'];
                    $subtotal = $esPeso ? (float)($item['subtotal_fijo'] ?? 0) : (float)$item['precio_unitario'] * $cantidad;
                  ?>
                  <tr>
                    <td>
                      <div class="fw-semibold small"><?= e($item['nombre']) ?></div>
                      <small class="text-muted">
                        <?= money($item['precio_unitario']) ?><?= $esPeso ? ' /kg' : '' ?>
                      </small>
                    </td>
                    <td class="text-center">
                      <?php if (!$esPeso): ?>
                        <form method="post" class="d-inline-flex gap-1 align-items-center justify-content-center">
                          <?= csrf_field() ?>
                          <input type="hidden" name="accion" value="update_qty">
                          <input type="hidden" name="key" value="<?= e($item['key']) ?>">
                          <input type="number" min="1" step="1" name="cantidad" class="form-control form-control-sm" style="width:86px" value="<?= (int)$cantidad ?>">
                          <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-check2"></i></button>
                        </form>
                      <?php else: ?>
                        <span class="badge text-bg-warning"><?= fmt_qty($cantidad) ?> kg</span>
                      <?php endif; ?>
                    </td>
                    <td class="text-end fw-semibold"><?= money($subtotal) ?></td>
                    <td class="text-end">
                      <form method="post" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="accion" value="remove_item">
                        <input type="hidden" name="key" value="<?= e($item['key']) ?>">
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div class="cash-summary p-3 rounded-3 mb-3">
            <div class="d-flex justify-content-between align-items-center">
              <span class="text-muted">Total a cobrar</span>
              <div class="h4 mb-0"><?= money($total) ?></div>
            </div>
          </div>

          <form method="post" class="row g-2">
            <?= csrf_field() ?>
            <input type="hidden" name="accion" value="checkout">
            <div class="col-md-6">
              <label class="form-label small">Método de pago</label>
              <select name="metodo_pago" class="form-select">
                <option value="efectivo">Efectivo</option>
                <option value="tarjeta">Tarjeta</option>
                <option value="transferencia">Transferencia</option>
                <option value="mixto">Mixto</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small">Monto pagado</label>
              <input type="number" step="0.01" min="0" name="monto_pagado" class="form-control" value="<?= number_format($total, 2, '.', '') ?>">
            </div>
            <div class="col-12">
              <label class="form-label small">Observación (opcional)</label>
              <input type="text" name="observacion" class="form-control" placeholder="Ej.: Venta carnicería, cliente frecuente...">
            </div>
            <div class="col-12 d-grid mt-2">
              <button class="btn btn-primary btn-lg"><i class="bi bi-cash-coin me-1"></i>Cobrar</button>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalPesableManual" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" id="formPesableManual">
        <?= csrf_field() ?>
        <input type="hidden" name="accion" value="add_pesable_manual">
        <input type="hidden" name="producto_id" id="manual_producto_id">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-calculator me-2"></i>Carga manual de pesable</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2">
            <div class="fw-semibold" id="manual_nombre">Producto</div>
            <small class="text-muted" id="manual_info"></small>
          </div>
          <div class="mb-3">
            <label class="form-label">Peso (gramos)</label>
            <input type="number" min="1" step="1" name="peso_gramos" id="manual_gramos" class="form-control" value="500" required>
            <small class="text-muted">Ejemplos: 450 = 0.450 kg, 1500 = 1.500 kg</small>
          </div>
          <div class="alert alert-light border mb-0 py-2">
            <div id="manual_preview_kg">Peso: 0.500 kg</div>
            <div id="manual_preview_subtotal">Subtotal: 0</div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-warning"><i class="bi bi-plus-circle me-1"></i>Agregar al carrito</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const scanInput = document.getElementById('codigo_scan');
if (scanInput) {
  scanInput.addEventListener('keydown', function (event) {
    if (event.key === 'Enter') {
      event.preventDefault();
      document.getElementById('scanForm').submit();
    }
  });

  window.addEventListener('load', function () {
    scanInput.focus();
  });
}

let manualPrecioKg = 0;

function moneyManual(value) {
  return '$' + Number(value || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function updateManualPreview() {
  const gramos = Math.max(1, parseInt(document.getElementById('manual_gramos').value || '0', 10));
  const kg = gramos / 1000;
  const subtotal = kg * manualPrecioKg;
  document.getElementById('manual_preview_kg').textContent = 'Peso: ' + kg.toFixed(3) + ' kg';
  document.getElementById('manual_preview_subtotal').textContent = 'Subtotal: ' + moneyManual(subtotal);
}

function abrirPesableManual(id, nombre, precioKg, stock, unidad) {
  document.getElementById('manual_producto_id').value = id;
  document.getElementById('manual_nombre').textContent = nombre;
  document.getElementById('manual_info').textContent = 'Stock: ' + Number(stock || 0).toFixed(3) + ' ' + (unidad || 'kg') + ' · Precio: ' + moneyManual(precioKg) + ' /kg';
  document.getElementById('manual_gramos').value = 500;
  manualPrecioKg = Number(precioKg || 0);
  updateManualPreview();
  new bootstrap.Modal(document.getElementById('modalPesableManual')).show();
}

document.getElementById('manual_gramos')?.addEventListener('input', updateManualPreview);
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
