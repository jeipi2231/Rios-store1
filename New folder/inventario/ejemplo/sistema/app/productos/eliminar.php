<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/logger.php';

require_role(['admin']);

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    set_flash('error', 'Producto invalido.');
    redirect('/app/productos/index.php');
}

try {
    $pdo = getPDO();
    $check = $pdo->prepare('SELECT COUNT(*) AS total FROM detalle_ventas WHERE producto_id = :id');
    $check->execute(['id' => $id]);
    $ventasAsociadas = (int) ($check->fetch()['total'] ?? 0);

    if ($ventasAsociadas > 0) {
        $stmt = $pdo->prepare('UPDATE productos SET activo = 0, stock = 0 WHERE id = :id');
        $stmt->execute(['id' => $id]);
        app_log('info', 'Producto desactivado por historial de ventas', ['producto_id' => $id]);
        set_flash('success', 'Producto desactivado. Tiene ventas historicas y no puede borrarse fisicamente.');
    } else {
        $stmt = $pdo->prepare('DELETE FROM productos WHERE id = :id');
        $stmt->execute(['id' => $id]);
        app_log('info', 'Producto eliminado', ['producto_id' => $id]);
        set_flash('success', 'Producto eliminado.');
    }
} catch (Throwable $e) {
    app_log('error', 'Error al eliminar producto', ['producto_id' => $id, 'error' => $e->getMessage()]);
    set_flash('error', 'No se pudo eliminar (verifica relaciones o ventas existentes).');
}

redirect('/app/productos/index.php');
