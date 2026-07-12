@echo off
echo ========================================
echo   INSTALADOR DEL SISTEMA DE GESTION
echo ========================================
echo.
echo Creando carpetas necesarias...
if not exist "app\assets\uploads\productos" (
    mkdir "app\assets\uploads\productos"
    echo ✓ Carpeta productos creada
) else (
    echo ✓ Carpeta productos ya existe
)

if not exist "logs" (
    mkdir "logs"
    echo ✓ Carpeta logs creada
) else (
    echo ✓ Carpeta logs ya existe
)

echo.
echo ========================================
echo   PROXIMOS PASOS:
echo ========================================
echo.
echo 1. Renombra esta carpeta a "sistema" (sin espacios)
echo 2. Copia la carpeta "sistema" a C:\xampp\htdocs\
echo 3. Abre XAMPP y inicia Apache + MySQL
echo 4. Ve a http://localhost/phpmyadmin
echo 5. Crea BD llamada 'despensa_db'
echo 6. Importa el archivo db\init.sql
echo 7. Accede en http://localhost/sistema/index.php
echo.
echo USUARIOS:
echo - Admin: admin / 12342231!
echo - Cajero: cajero / 12342231
echo.
echo ========================================
echo   ¡PRESIONA CUALQUIER TECLA!
echo ========================================
pause > nul