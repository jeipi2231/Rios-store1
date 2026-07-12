<?php
declare(strict_types=1);

function getPDO(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    // Leer configuración desde archivo config.env si existe
    $configFile = __DIR__ . '/../../config.env';
    $host = 'localhost';
    $db = 'despensa_db';
    $user = 'root';
    $pass = '';

    if (file_exists($configFile)) {
        $lines = file($configFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '#') === 0) continue; // Ignorar comentarios
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            switch ($key) {
                case 'DB_HOST': $host = $value; break;
                case 'DB_NAME': $db = $value; break;
                case 'DB_USER': $user = $value; break;
                case 'DB_PASS': $pass = $value; break;
            }
        }
    }

    // También leer variables de entorno (para compatibilidad)
    $host = getenv('DB_HOST') ?: $host;
    $db = getenv('DB_NAME') ?: $db;
    $user = getenv('DB_USER') ?: $user;
    $pass = getenv('DB_PASS') ?: $pass;

    $dsn = "mysql:host={$host};dbname={$db};charset=utf8mb4";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}
