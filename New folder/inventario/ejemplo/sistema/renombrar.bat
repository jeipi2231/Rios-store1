@echo off
echo ========================================
echo   PREPARACION PARA INSTALACION
echo ========================================
echo.
echo Este script renombrara la carpeta para evitar espacios en la URL
echo.

set "current_dir=%~dp0"
set "parent_dir=%current_dir%.."
set "new_name=sistema"

echo Carpeta actual: %current_dir%
echo Nueva carpeta: %parent_dir%\%new_name%
echo.

if exist "%parent_dir%\%new_name%" (
    echo ⚠ La carpeta "sistema" ya existe.
    echo   Borrala o muevela antes de continuar.
    echo.
    pause
    exit /b 1
)

echo Renombrando carpeta...
ren "%current_dir%.." "%new_name%"

if errorlevel 1 (
    echo ✗ Error al renombrar la carpeta.
    echo   Asegurate de que no haya archivos abiertos.
    pause
    exit /b 1
)

echo ✓ Carpeta renombrada exitosamente!
echo.
echo ========================================
echo   SIGUIENTE PASO:
echo ========================================
echo.
echo 1. Copia la carpeta "sistema" a C:\xampp\htdocs\
echo 2. Ejecuta install.bat dentro de la carpeta copiada
echo 3. Sigue las instrucciones del archivo INSTALACION.txt
echo.
pause