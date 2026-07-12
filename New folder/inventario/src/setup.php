<?php
/**
 * Script de instalación inicial (se ejecuta una sola vez).
 * Crea el usuario administrador con contraseña hasheada con bcrypt.
 * Acceder vía http://localhost:8080/setup.php
 */
require_once __DIR__ . '/config/database.php';

$mensaje = '';
$ya_existe = false;

try {
    $total = (int)$pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();

    if ($total > 0) {
        $ya_existe = true;
        $mensaje = '<div class="alert alert-info">Ya existen usuarios en el sistema. '
                 . 'Si quieres recrear el admin, elimina primero los usuarios desde phpMyAdmin.</div>';
    } else {
      // Crear solo usuario administrador
        $admin_pass = password_hash('admin123', PASSWORD_BCRYPT);

        $stmt = $pdo->prepare(
        "INSERT INTO usuarios (nombre, email, password, rol) VALUES
         ('Administrador', 'admin@inventario.local', ?, 'admin')"
        );
      $stmt->execute([$admin_pass]);

        $mensaje = '<div class="alert alert-success">
            <h4 class="alert-heading">¡Instalación completada!</h4>
        <p>Se creó el usuario inicial:</p>
            <ul>
              <li><strong>Admin:</strong> admin@inventario.local / <code>admin123</code></li>
            </ul>
            <hr>
            <a href="login.php" class="btn btn-primary">Ir al inicio de sesión</a>
        </div>';
    }
} catch (Throwable $ex) {
    $mensaje = '<div class="alert alert-danger">Error: '
             . htmlspecialchars($ex->getMessage()) . '</div>';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Instalación - Sistema de Inventario</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>body{background:#f5f7fa;font-family:'Segoe UI',sans-serif;} .wrap{max-width:640px;margin:60px auto;}</style>
</head>
<body>
  <div class="wrap">
    <div class="card shadow-sm">
      <div class="card-body p-4">
        <h3 class="mb-3">Sistema de Inventario · Instalación</h3>
        <?= $mensaje ?>
        <?php if (!$ya_existe && strpos($mensaje, 'alert-success') !== false): ?>
          <p class="text-muted small mt-3">Por seguridad, elimina este archivo (<code>setup.php</code>) después de instalar.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>
</html>
