<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/schema.php';

require_login();
ensure_extended_schema();

$cotizacionId = (int) ($_GET['id'] ?? 0);
$autoPrint = ($_GET['auto'] ?? '0') === '1';

if ($cotizacionId <= 0) {
    http_response_code(400);
    echo 'ID de cotizacion invalido.';
    exit;
}

$pdo = getPDO();

$stmtCotizacion = $pdo->prepare(
    'SELECT c.id, c.fecha, c.total, c.metodo_pago, c.banco_pago, c.tipo_entrega, u.nombre AS vendedor,
            cl.nombre AS cliente_nombre, cl.apellido AS cliente_apellido, cl.ruc AS cliente_ruc
     FROM cotizaciones c
     INNER JOIN usuarios u ON u.id = c.usuario_id
     LEFT JOIN clientes cl ON cl.id = c.cliente_id
     WHERE c.id = :id
     LIMIT 1'
);
$stmtCotizacion->execute(['id' => $cotizacionId]);
$cotizacion = $stmtCotizacion->fetch();

if (!$cotizacion) {
    http_response_code(404);
    echo 'Cotizacion no encontrada.';
    exit;
}

$stmtDetalle = $pdo->prepare(
    'SELECT d.cantidad, d.precio_unitario, d.subtotal, p.nombre
     FROM detalle_cotizaciones d
     INNER JOIN productos p ON p.id = d.producto_id
     WHERE d.cotizacion_id = :cotizacion_id
     ORDER BY d.id ASC'
);
$stmtDetalle->execute(['cotizacion_id' => $cotizacionId]);
$detalle = $stmtDetalle->fetchAll();

if (!$detalle) {
    http_response_code(404);
    echo 'No hay detalle para esta cotizacion.';
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
    <title>Cotizacion #<?php echo (int) $cotizacion['id']; ?></title>
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
    <div class="sheet">
        <div class="document-header">
            <div>
                <div class="document-title">Cotizacion</div>
                <div><strong><?php echo e($negocio); ?></strong></div>
                <div class="muted"><?php echo e($direccion); ?></div>
            </div>
            <div class="text-end">
                <div><strong>Nro:</strong> #<?php echo (int) $cotizacion['id']; ?></div>
                <div><strong>Fecha:</strong> <?php echo e(date('d/m/Y H:i', strtotime((string) $cotizacion['fecha']))); ?></div>
            </div>
        </div>

        <div class="meta-grid">
            <div class="meta-box">
                <strong>Datos de la cotizacion</strong>
                <div>Vendedor: <?php echo e($cotizacion['vendedor']); ?></div>
                <div>Metodo sugerido: <?php echo e(payment_method_label((string) $cotizacion['metodo_pago'])); ?></div>
                <div>Banco: <?php echo e($cotizacion['banco_pago'] ?: '-'); ?></div>
                <div>Entrega: <?php echo e(delivery_type_label((string) $cotizacion['tipo_entrega'])); ?></div>
            </div>
            <div class="meta-box">
                <strong>Cliente</strong>
                <?php if (!empty($cotizacion['cliente_ruc'])): ?>
                    <div><?php echo e(trim(($cotizacion['cliente_nombre'] ?? '') . ' ' . ($cotizacion['cliente_apellido'] ?? ''))); ?></div>
                    <div>RUC: <?php echo e($cotizacion['cliente_ruc']); ?></div>
                <?php else: ?>
                    <div class="muted">Cliente no especificado</div>
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
                <strong>Total cotizado</strong>
                <div><?php echo e(format_gs((float) $cotizacion['total'])); ?></div>
            </div>
        </div>

        <div class="footer-note">Esta cotizacion no descuenta stock y no representa una venta confirmada.</div>
    </div>

    <div class="controls">
        <button type="button" onclick="window.print()">Imprimir cotizacion</button>
        <a href="<?php echo BASE_URL; ?>/app/ventas/index.php">Volver a ventas</a>
    </div>

    <script>
    (function () {
      const autoPrint = <?php echo $autoPrint ? 'true' : 'false'; ?>;

      if (autoPrint) {
        window.onload = function () {
          window.print();
        };
      }
    })();
    </script>
</body>
</html>