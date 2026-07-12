<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

require_login();

$ventaId = (int) ($_GET['id'] ?? 0);
$autoPrint = ($_GET['auto'] ?? '1') === '1';

if ($ventaId <= 0) {
    http_response_code(400);
    echo 'ID de venta invalido.';
    exit;
}

$pdo = getPDO();

$stmtVenta = $pdo->prepare(
    'SELECT v.id, v.fecha, v.total, v.monto_pagado, v.vuelto, u.nombre AS vendedor
     FROM ventas v
     INNER JOIN usuarios u ON u.id = v.usuario_id
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

$negocio = 'Bloch Store';
$direccion = 'Encarnacion, Paraguay';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ticket #<?php echo (int) $venta['id']; ?></title>
    <style>
        :root {
            --ticket-width: 80mm;
        }

        body {
            margin: 0;
            padding: 12px;
            background: #f2f2f2;
            font-family: "Courier New", Courier, monospace;
            font-size: 12px;
            line-height: 1.25;
            color: #000;
        }

        .ticket {
            width: var(--ticket-width);
            margin: 0 auto;
            background: #fff;
            padding: 10px;
            box-sizing: border-box;
            border: 1px solid #ddd;
        }

        .center {
            text-align: center;
        }

        .line {
            margin: 6px 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: clip;
        }

        .meta {
            margin-top: 6px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th,
        td {
            padding: 2px 0;
            vertical-align: top;
            word-wrap: break-word;
        }

        th {
            text-align: left;
            font-weight: 700;
        }

        .num {
            text-align: right;
        }

        .controls {
            margin: 12px auto;
            width: var(--ticket-width);
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
            font-size: 12px;
        }

        @media print {
            @page {
                size: auto;
                margin: 2mm;
            }

            body {
                background: #fff;
                padding: 0;
            }

            .ticket {
                width: 100%;
                border: 0;
                margin: 0;
                padding: 0;
            }

            .controls {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="ticket" id="ticket">
        <div class="center">
            <strong><?php echo e($negocio); ?></strong><br>
            <?php echo e($direccion); ?>
        </div>

        <div class="line">----------------------------------------</div>
        <div class="meta">
            <div>Ticket: #<?php echo (int) $venta['id']; ?></div>
            <div>Fecha: <?php echo e($venta['fecha']); ?></div>
            <div>Vendedor: <?php echo e($venta['vendedor']); ?></div>
        </div>
        <div class="line">----------------------------------------</div>

        <table>
            <thead>
                <tr>
                    <th style="width: 42%;">Producto</th>
                    <th class="num" style="width: 14%;">Cant</th>
                    <th class="num" style="width: 22%;">P.U.</th>
                    <th class="num" style="width: 22%;">Subt</th>
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

        <div class="line">----------------------------------------</div>
        <div class="num"><strong>TOTAL: <?php echo e(format_gs((float) $venta['total'])); ?></strong></div>
        <?php if ((float) $venta['monto_pagado'] > 0): ?>
            <div class="num">Pagado: <?php echo e(format_gs((float) $venta['monto_pagado'])); ?></div>
            <div class="num">Vuelto: <?php echo e(format_gs((float) $venta['vuelto'])); ?></div>
        <?php endif; ?>
        <div class="line">----------------------------------------</div>
        <div class="center">Gracias por su compra</div>
    </div>

    <div class="controls">
        <button type="button" onclick="window.print()">Reimprimir ticket</button>
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
