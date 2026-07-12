<?php
// VERIFICACION DEL SISTEMA DE GESTION
// Ejecuta este archivo para verificar que todo esté configurado correctamente
// URL: http://localhost/sistema/verificar.php

echo "<h1>🔍 Verificación del Sistema</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .ok{color:green;} .error{color:red;} .warning{color:orange;}</style>";

// Verificar PHP
echo "<h2>📋 Verificación del Sistema</h2>";
echo "<p>PHP Version: " . phpversion() . "</p>";

// Verificar extensiones requeridas
$extensions = ['pdo', 'pdo_mysql', 'mbstring', 'fileinfo'];
echo "<h3>Extensiones PHP:</h3><ul>";
foreach ($extensions as $ext) {
    $loaded = extension_loaded($ext);
    echo "<li class='" . ($loaded ? 'ok' : 'error') . "'>";
    echo $ext . ": " . ($loaded ? "✓ OK" : "✗ FALTA");
    echo "</li>";
}
echo "</ul>";

// Verificar carpetas
echo "<h3>Carpetas necesarias:</h3><ul>";
$folders = [
    'app/assets/uploads/productos',
    'logs'
];
foreach ($folders as $folder) {
    $exists = is_dir($folder);
    $writable = $exists && is_writable($folder);
    echo "<li class='" . ($exists && $writable ? 'ok' : 'error') . "'>";
    echo $folder . ": ";
    if (!$exists) {
        echo "✗ NO EXISTE";
    } elseif (!$writable) {
        echo "⚠ EXISTE PERO NO SE PUEDE ESCRIBIR";
    } else {
        echo "✓ OK";
    }
    echo "</li>";
}
echo "</ul>";

// Verificar conexión a BD
echo "<h3>Conexión a Base de Datos:</h3>";
try {
    require_once 'app/config/database.php';
    $pdo = getPDO();
    echo "<p class='ok'>✓ Conexión exitosa a MySQL</p>";

    // Verificar tablas
    $tables = ['usuarios', 'productos', 'ventas', 'categorias'];
    echo "<h4>Tablas en la BD:</h4><ul>";
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        $exists = $stmt->rowCount() > 0;
        echo "<li class='" . ($exists ? 'ok' : 'error') . "'>";
        echo $table . ": " . ($exists ? "✓ OK" : "✗ FALTA");
        echo "</li>";
    }
    echo "</ul>";

    // Verificar usuarios
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios");
    $count = $stmt->fetch()['total'];
    echo "<p>Usuarios registrados: $count</p>";

} catch (Exception $e) {
    echo "<p class='error'>✗ Error de conexión: " . $e->getMessage() . "</p>";
    echo "<p><strong>Solución:</strong> Verifica que MySQL esté corriendo y que la BD 'despensa_db' exista.</p>";
}

echo "<hr>";
echo "<h2>🚀 Próximos pasos:</h2>";
echo "<ol>";
echo "<li>Si hay errores arriba, corrígelos primero</li>";
echo "<li>Ve a: <a href='index.php'>http://localhost/sistema/index.php</a></li>";
echo "<li>Usa las credenciales del archivo INSTALACION.txt</li>";
echo "</ol>";

echo "<p><strong>¿Problemas?</strong> Revisa el archivo INSTALACION.txt o contacta soporte.</p>";
?>