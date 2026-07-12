<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/session.php';

function redirect(string $path): void
{
    // Si el path empieza con /, es interno, prepend BASE_URL
    if (strpos($path, '/') === 0) {
        $path = BASE_URL . $path;
    }
    header("Location: {$path}");
    exit;
}

function require_login(): void
{
    if (empty($_SESSION['user'])) {
        redirect('/app/auth/login.php');
    }
}

function require_role(array $roles): void
{
    require_login();
    $role = $_SESSION['user']['rol'] ?? null;
    if (!in_array($role, $roles, true)) {
        set_flash('error', 'No tienes permisos para acceder a esta seccion.');
        redirect('/app/dashboard.php');
    }
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function format_gs(float $value): string
{
    return 'Gs ' . number_format(round_gs_to_500($value), 0, ',', '.');
}

function round_gs_to_500(float $value): int
{
    return (int) (round($value / 500) * 500);
}

function is_valid_gs_500(float $value): bool
{
    if ($value <= 0) {
        return false;
    }

    return ((int) $value) % 500 === 0;
}
