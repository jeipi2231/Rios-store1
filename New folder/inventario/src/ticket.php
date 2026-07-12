<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$ventaId = (int)($_GET['id'] ?? 0);
$autoPrint = ($_GET['auto'] ?? '1') === '1';

if ($ventaId <= 0) {
    http_response_code(400);
    die('ID de venta inválido.');
}

$stmtVenta = $pdo->prepare(
    "SELECT v.id, v.fecha, v.total, v.monto_pagado, v.vuelto, v.metodo_pago, v.observacion, u.nombre AS vendedor
     FROM ventas v
     JOIN usuarios u ON u.id = v.usuario_id
     WHERE v.id = ?
     LIMIT 1"
);
$stmtVenta->execute([$ventaId]);
$venta = $stmtVenta->fetch();

if (!$venta) {
    http_response_code(404);
    die('Venta no encontrada.');
}

$stmtDetalle = $pdo->prepare(
  "SELECT d.cantidad, d.precio_unitario, d.subtotal, d.codigo_escaneado, p.nombre, p.codigo, p.unidad
     FROM detalle_ventas d
     JOIN productos p ON p.id = d.producto_id
     WHERE d.venta_id = ?
     ORDER BY d.id ASC"
);
$stmtDetalle->execute([$ventaId]);
$detalle = $stmtDetalle->fetchAll();

if (!$detalle) {
    http_response_code(404);
    die('No hay detalle para esta venta.');
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ticket #<?= (int)$venta['id'] ?></title>
  <style>
    :root { --ticket-width: 80mm; }
    body {
      margin: 0;
      padding: 12px;
      background: #f1efe9;
      color: #111;
      font-family: "Courier New", monospace;
      font-size: 12px;
      line-height: 1.3;
    }
    .ticket {
      width: var(--ticket-width);
      margin: 0 auto;
      background: #fff;
      border: 1px solid #dfd8cb;
      padding: 10px;
      box-sizing: border-box;
    }
    .center { text-align: center; }
    .line { margin: 6px 0; white-space: nowrap; overflow: hidden; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    th, td { padding: 2px 0; vertical-align: top; }
    th { text-align: left; }
    .num { text-align: right; }
    .controls {
      margin: 12px auto;
      width: var(--ticket-width);
      display: flex;
      gap: 8px;
    }
    .controls a,
    .controls button {
      flex: 1;
      border: 1px solid #4f4a43;
      background: #fff;
      text-decoration: none;
      color: #222;
      font-size: 12px;
      padding: 8px;
      text-align: center;
      cursor: pointer;
    }
    @media print {
      @page { margin: 2mm; }
      body { background: #fff; padding: 0; }
      .ticket { border: 0; width: 100%; margin: 0; padding: 0; }
      .controls { display: none; }
    }
  </style>
</head>
<body>
  <div class="ticket">
    <div class="center">
      <strong>Ticket de venta</strong><br>
      Sistema de Inventario
    </div>

    <div class="line">----------------------------------------</div>
    <div>
      <div>Ticket: #<?= (int)$venta['id'] ?></div>
      <div>Fecha: <?= e($venta['fecha']) ?></div>
      <div>Cajero: <?= e($venta['vendedor']) ?></div>
      <div>Pago: <?= e(ucfirst((string)$venta['metodo_pago'])) ?></div>
      <?php if (!empty($venta['observacion'])): ?>
        <div>Obs.: <?= e($venta['observacion']) ?></div>
      <?php endif; ?>
    </div>
    <div class="line">----------------------------------------</div>

    <table>
      <thead>
        <tr>
          <th style="width: 38%;">Producto</th>
          <th class="num" style="width: 12%;">Cant</th>
          <th class="num" style="width: 24%;">P.U.</th>
          <th class="num" style="width: 26%;">Subt</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($detalle as $item): ?>
          <tr>
            <td>
              <?= e($item['nombre']) ?><br>
              <small><?= e($item['codigo']) ?><?= $item['codigo_escaneado'] ? ' · ' . e($item['codigo_escaneado']) : '' ?></small>
            </td>
            <td class="num"><?= fmt_qty($item['cantidad']) ?><?= (($item['unidad'] ?? 'unidad') === 'kg') ? 'kg' : '' ?></td>
            <td class="num"><?= money($item['precio_unitario']) ?></td>
            <td class="num"><?= money($item['subtotal']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="line">----------------------------------------</div>
    <div class="num"><strong>TOTAL: <?= money($venta['total']) ?></strong></div>
    <div class="num">Pagado: <?= money($venta['monto_pagado']) ?></div>
    <div class="num">Vuelto: <?= money($venta['vuelto']) ?></div>
    <div class="line">----------------------------------------</div>
    <div class="center">Gracias por su compra</div>
  </div>

  <div class="controls">
    <button type="button" onclick="window.print()">Imprimir ticket</button>
    <a href="cashier.php">Volver a cajero</a>
  </div>

  <script>
    (function () {
      const autoPrint = <?= $autoPrint ? 'true' : 'false' ?>;
      if (autoPrint) {
        window.onload = function () {
          window.print();
        };
      }
    })();
  </script>
</body>
</html>
