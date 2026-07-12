<?php
/**
 * Conexión a la base de datos MySQL usando PDO
 */

define('DB_HOST', getenv('DB_HOST') ?: 'db');
define('DB_NAME', getenv('DB_NAME') ?: 'inventario');
define('DB_USER', getenv('DB_USER') ?: 'inventario_user');
define('DB_PASS', getenv('DB_PASS') ?: 'inventario_pass');
define('DB_CHARSET', 'utf8mb4');

$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // Mostrar mensaje amigable en lugar del error crudo
    http_response_code(500);
    die(
        '<div style="font-family:sans-serif;max-width:600px;margin:80px auto;padding:30px;'
        . 'border:1px solid #e0e0e0;border-radius:8px;text-align:center;background:#fafafa;">'
        . '<h2 style="color:#c0392b;">No se pudo conectar a la base de datos</h2>'
        . '<p>Verifica que los contenedores de Docker estén corriendo '
        . 'ejecutando <code>docker-compose up -d</code>.</p>'
        . '<p style="color:#888;font-size:0.9em;">Detalle técnico: '
        . htmlspecialchars($e->getMessage()) . '</p></div>'
    );
}

/**
 * Formatea un número como moneda (Gs. por defecto en Paraguay)
 */
function money($value): string {
    return 'Gs. ' . number_format((float)$value, 0, ',', '.');
}

/**
 * Formatea un número entero con separador de miles
 */
function fmt_int($value): string {
    return number_format((int)$value, 0, ',', '.');
}

/**
 * Formatea cantidades enteras o decimales (ej.: unidades, kg, litros)
 */
function fmt_qty($value, int $maxDecimals = 3): string {
    $num = (float)$value;
    $formatted = number_format($num, $maxDecimals, ',', '.');
    $formatted = rtrim(rtrim($formatted, '0'), ',');
    return $formatted === '' ? '0' : $formatted;
}

/**
 * Escapa HTML
 */
function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
