<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/schema.php';

require_login();
ensure_extended_schema();

$ventaId = (int) ($_GET['id'] ?? 0);
$autoPrint = ($_GET['auto'] ?? '1') === '1';

if ($ventaId <= 0) {
    http_response_code(400);
    echo 'ID de venta invalido.';
    exit;
}

$pdo = getPDO();

$stmtVenta = $pdo->prepare(
    'SELECT v.id, v.fecha, v.total, v.monto_pagado, v.vuelto, v.metodo_pago, v.banco_pago, v.tipo_entrega, u.nombre AS vendedor,
            c.nombre AS cliente_nombre, c.apellido AS cliente_apellido, c.ruc AS cliente_ruc
     FROM ventas v
     INNER JOIN usuarios u ON u.id = v.usuario_id
     LEFT JOIN clientes c ON c.id = v.cliente_id
     WHERE v.id = :id
     LIMIT 1'
);
$stmtVenta->execute(['id' => $ventaId]);
$venta = $stmtVenta->fetch();

if (!$venta) {
    http_response_code(404);
    echo 'Venta no encontrada.';
    exit;
}

$stmtDetalle = $pdo->prepare(
    'SELECT d.cantidad, d.precio_unitario, d.subtotal, p.nombre
     FROM detalle_ventas d
     INNER JOIN productos p ON p.id = d.producto_id
     WHERE d.venta_id = :venta_id
     ORDER BY d.id ASC'
);
$stmtDetalle->execute(['venta_id' => $ventaId]);
$detalle = $stmtDetalle->fetchAll();

if (!$detalle) {
    http_response_code(404);
    echo 'No hay detalle para esta venta.';
    exit;
}

$negocio = APP_NAME;
$direccion = APP_ADDRESS;
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ticket #<?php echo (int) $venta['id']; ?></title>
    <style>
        body {
            margin: 0;
            padding: 20px;
            background: #ececec;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
            line-height: 1.45;
            color: #000;
        }

        .sheet {
            width: 148mm;
            min-height: 210mm;
            margin: 0 auto;
            background: #fff;
            padding: 16mm;
            box-sizing: border-box;
            border: 1px solid #cfcfcf;
            box-shadow: 0 14px 32px rgba(0, 0, 0, 0.08);
        }

        .center {
            text-align: center;
        }

        .document-header {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
            padding-bottom: 14px;
            border-bottom: 2px solid #111;
        }

        .document-title {
            font-size: 28px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .muted {
            color: #555;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px 20px;
            margin-bottom: 18px;
        }

        .meta-box,
        .total-box {
            border: 1px solid #111;
            padding: 10px 12px;
        }

        .meta-box strong,
        .total-box strong {
            display: block;
            margin-bottom: 4px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        th,
        td {
            padding: 9px 8px;
            vertical-align: top;
            border-bottom: 1px solid #d9d9d9;
        }

        th {
            text-align: left;
            font-weight: 700;
            background: #f5f5f5;
        }

        .num {
            text-align: right;
        }

        .controls {
            margin: 12px auto;
            width: 148mm;
            display: flex;
            gap: 8px;
        }

        .controls button,
        .controls a {
            flex: 1;
            text-align: center;
            border: 1px solid #444;
            background: #fff;
            padding: 8px 10px;
            cursor: pointer;
            text-decoration: none;
            color: #000;
            font-size: 13px;
        }

        .totals {
            margin-top: 16px;
            display: grid;
            gap: 10px;
            justify-items: end;
        }

        .total-box {
            min-width: 220px;
            text-align: right;
        }

        .footer-note {
            margin-top: 18px;
            padding-top: 12px;
            border-top: 1px solid #111;
            text-align: center;
        }

        @media print {
            @page {
                size: A5 portrait;
                margin: 10mm;
            }

            body {
                background: #fff;
                padding: 0;
            }

            .sheet {
                width: 100%;
                border: 0;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }

            .controls {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="sheet" id="ticket">
        <div class="document-header">
            <div>
                <div class="document-title">Ticket de venta</div>
                <div><strong><?php echo e($negocio); ?></strong></div>
                <div class="muted"><?php echo e($direccion); ?></div>
            </div>
            <div class="text-end">
                <div><strong>Nro:</strong> #<?php echo (int) $venta['id']; ?></div>
                <div><strong>Fecha:</strong> <?php echo e(date('d/m/Y H:i', strtotime((string) $venta['fecha']))); ?></div>
            </div>
        </div>

        <div class="meta-grid">
            <div class="meta-box">
                <strong>Datos de la venta</strong>
                <div>Vendedor: <?php echo e($venta['vendedor']); ?></div>
                <div>Metodo de pago: <?php echo e(payment_method_label((string) $venta['metodo_pago'])); ?></div>
                <div>Banco: <?php echo e($venta['banco_pago'] ?: '-'); ?></div>
                <div>Entrega: <?php echo e(delivery_type_label((string) $venta['tipo_entrega'])); ?></div>
            </div>
            <div class="meta-box">
                <strong>Cliente</strong>
                <?php if (!empty($venta['cliente_ruc'])): ?>
                    <div><?php echo e(trim(($venta['cliente_nombre'] ?? '') . ' ' . ($venta['cliente_apellido'] ?? ''))); ?></div>
                    <div>RUC: <?php echo e($venta['cliente_ruc']); ?></div>
                <?php else: ?>
                    <div class="muted">Consumidor final</div>
                <?php endif; ?>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 46%;">Producto</th>
                    <th class="num" style="width: 14%;">Cant.</th>
                    <th class="num" style="width: 20%;">Precio de venta</th>
                    <th class="num" style="width: 20%;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detalle as $item): ?>
                    <tr>
                        <td><?php echo e($item['nombre']); ?></td>
                        <td class="num"><?php echo (int) $item['cantidad']; ?></td>
                        <td class="num"><?php echo e(format_gs((float) $item['precio_unitario'])); ?></td>
                        <td class="num"><?php echo e(format_gs((float) $item['subtotal'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totals">
            <div class="total-box">
                <strong>Total</strong>
                <div><?php echo e(format_gs((float) $venta['total'])); ?></div>
            </div>
            <?php if ((float) $venta['monto_pagado'] > 0): ?>
                <div class="total-box">
                    <strong>Monto pagado</strong>
                    <div><?php echo e(format_gs((float) $venta['monto_pagado'])); ?></div>
                    <div>Vuelto: <?php echo e(format_gs((float) $venta['vuelto'])); ?></div>
                </div>
            <?php endif; ?>
        </div>

        <div class="footer-note">Gracias por su compra</div>
    </div>

    <div class="controls">
        <button type="button" onclick="window.print()">Imprimir ticket</button>
        <a href="<?php echo BASE_URL; ?>/app/ventas/index.php">Volver a ventas</a>
    </div>

    <script>
    (function () {
      const autoPrint = <?php echo $autoPrint ? 'true' : 'false'; ?>;
      const isMobile = /Android|iPhone|iPad|iPod|Opera Mini|IEMobile|WPDesktop/i.test(navigator.userAgent);

      if (autoPrint) {
        window.onload = function () {
          window.print();
        };

        if (!isMobile) {
          window.onafterprint = function () {
            window.location.href = '<?php echo BASE_URL; ?>/app/ventas/index.php';
          };
        }
      }
    })();
    </script>
</body>
</html>
