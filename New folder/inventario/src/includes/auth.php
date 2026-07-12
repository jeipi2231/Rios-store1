<?php
/**
 * Helpers de autenticación por sesión
 */
session_start();

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool {
    return isset($_SESSION['user']);
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function require_admin(): void {
    require_login();
    if (($_SESSION['user']['rol'] ?? '') !== 'admin') {
        http_response_code(403);
        die('<div style="font-family:sans-serif;text-align:center;padding:60px;">
               <h2>403 · Acceso denegado</h2>
               <p>Solo los administradores pueden acceder a esta sección.</p>
               <a href="index.php">Volver al inicio</a>
             </div>');
    }
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): bool {
    $token = $_POST['csrf'] ?? '';
    return is_string($token) && hash_equals($_SESSION['csrf'] ?? '', $token);
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}
