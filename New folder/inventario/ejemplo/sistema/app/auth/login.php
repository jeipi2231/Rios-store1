<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/logger.php';

if (!empty($_SESSION['user'])) {
    redirect('/app/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($usuario === '' || $password === '') {
        set_flash('error', 'Completa usuario y contrasena.');
        redirect('/app/auth/login.php');
    }

    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT id, nombre, usuario, password, rol FROM usuarios WHERE usuario = :usuario LIMIT 1');
    $stmt->execute(['usuario' => $usuario]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        app_log('warning', 'Intento de login fallido', ['usuario' => $usuario]);
        set_flash('error', 'Credenciales invalidas.');
        redirect('/app/auth/login.php');
    }

    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'nombre' => $user['nombre'],
        'usuario' => $user['usuario'],
        'rol' => $user['rol'],
    ];

    app_log('info', 'Login exitoso', ['usuario_id' => (int) $user['id']]);
    set_flash('success', 'Bienvenido al sistema.');
    redirect('/app/dashboard.php');
}

render_header('Iniciar sesion');
?>

<div class="row justify-content-center">
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h4 mb-3">Iniciar sesion</h1>
                <form method="post" novalidate>
                    <div class="mb-3">
                        <label class="form-label">Usuario</label>
                        <input type="text" name="usuario" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contrasena</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Ingresar</button>
                </form>
                <p class="text-muted mt-3 mb-0 small">Usuario inicial: admin | Contrasena: password</p>
            </div>
        </div>
    </div>
</div>

<?php render_footer();
