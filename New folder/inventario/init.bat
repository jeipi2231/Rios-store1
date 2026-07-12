@echo off
REM ============================================================
REM Script de inicialización - Configura el proyecto para Docker
REM ============================================================
setlocal enabledelayedexpansion

echo.
echo ============================================================
echo  Sistema de Inventario - Inicializacion Docker
echo ============================================================
echo.

REM Verificar si Docker está instalado
docker --version >nul 2>&1
if errorlevel 1 (
    echo [ERROR] Docker no está instalado o no está en el PATH
    echo Por favor instala Docker: https://www.docker.com/products/docker-desktop
    pause
    exit /b 1
)

docker-compose --version >nul 2>&1
if errorlevel 1 (
    echo [ERROR] Docker Compose no está instalado
    echo Por favor instala Docker Compose: https://docs.docker.com/compose/install/
    pause
    exit /b 1
)

echo [OK] Docker detectado: 
docker --version

echo.
echo [INFO] Verificando estructura del proyecto...

REM Verificar carpetas necesarias
if not exist "src" (
    echo [ERROR] No se encontró carpeta 'src/'
    exit /b 1
)

if not exist "database" (
    echo [ERROR] No se encontró carpeta 'database/'
    exit /b 1
)

if not exist "database\init.sql" (
    echo [ERROR] No se encontró 'database/init.sql'
    exit /b 1
)

echo [OK] Estructura verificada

echo.
echo [INFO] Configurando archivo .env...

REM Verificar si .env ya existe
if exist ".env" (
    echo [AVISO] Ya existe un archivo .env
    set /p continuar="¿Quieres regenerarlo? (s/n): "
    if /i not "!continuar!"=="s" (
        echo Usando .env existente
        goto :skip_env
    )
)

REM Crear .env desde .env.example
if exist ".env.example" (
    copy ".env.example" ".env" >nul
    echo [OK] Archivo .env creado
) else (
    echo [ERROR] No se encontró .env.example
    exit /b 1
)

:skip_env

echo.
echo [INFO] Creando carpetas de trabajo...

REM Crear carpetas de uploads si no existen
if not exist "src\assets" mkdir src\assets
if not exist "src\assets\uploads" mkdir src\assets\uploads
if not exist "src\assets\uploads\productos" mkdir src\assets\uploads\productos
if not exist "src\logs" mkdir src\logs

echo [OK] Carpetas creadas

echo.
echo [INFO] Levantando contenedores (esto puede tomar 1-2 minutos)...

docker-compose down >nul 2>&1
docker-compose up -d --build

if errorlevel 1 (
    echo [ERROR] No se pudo levantar los contenedores
    echo Ver logs: docker-compose logs
    pause
    exit /b 1
)

echo [OK] Contenedores levantados

echo.
echo [INFO] Esperando a que MySQL esté listo...

REM Esperar 15 segundos para que MySQL esté listo
timeout /t 15 /nobreak

echo.
echo [INFO] Verificando salud de los contenedores...
docker-compose ps

echo.
echo ============================================================
echo  ¡INICIALIZACION COMPLETADA!
echo ============================================================
echo.
echo Accede a:
echo  - Aplicacion:  http://localhost:8080
echo  - phpMyAdmin:  http://localhost:8081
echo  - MySQL:       localhost:3306
echo.
echo Proximo paso:
echo  1. Abre http://localhost:8080/setup.php en tu navegador
echo  2. Se creará el usuario admin inicial
echo  3. Inicia sesion: admin@inventario.local / admin123
echo.
echo Comandos utiles:
echo  - docker-helper.bat up     - Levantar contenedores
echo  - docker-helper.bat down   - Detener contenedores
echo  - docker-helper.bat logs   - Ver logs
echo  - docker-helper.bat bash   - Acceder al contenedor PHP
echo.
echo ============================================================
echo.

pause
