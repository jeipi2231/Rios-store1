<?php
declare(strict_types=1);

// Leer configuración desde config.env
$configFile = __DIR__ . '/../../config.env';
$baseUrl = '/'; // Default to root
$dbHost = 'localhost';
$dbName = 'despensa_db';
$dbUser = 'root';
$dbPass = '';

if (file_exists($configFile)) {
    $lines = file($configFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0 || strpos($line, '=') === false) {
            continue;
        }
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        switch ($key) {
            case 'BASE_URL': $baseUrl = $value; break;
            case 'DB_HOST': $dbHost = $value; break;
            case 'DB_NAME': $dbName = $value; break;
            case 'DB_USER': $dbUser = $value; break;
            case 'DB_PASS': $dbPass = $value; break;
        }
    }
}

// Normaliza BASE_URL para evitar URLs invalidas como //app/...
$baseUrl = trim($baseUrl);
if ($baseUrl === '' || $baseUrl === '/') {
    $baseUrl = '';
} else {
    $baseUrl = '/' . trim($baseUrl, '/');
}

define('BASE_URL', $baseUrl);
define('DB_HOST', $dbHost);
define('DB_NAME', $dbName);
define('DB_USER', $dbUser);
define('DB_PASS', $dbPass);
define('APP_NAME', 'Rios Store');
define('APP_ADDRESS', 'Encarnacion, Paraguay');
