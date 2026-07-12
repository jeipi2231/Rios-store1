@echo off
REM ============================================================
REM Script helper para Docker - Windows
REM Facilita los comandos Docker más comunes
REM ============================================================

if "%1"=="" (
    echo.
    echo  === Sistema de Inventario - Docker Helper ===
    echo.
    echo  Comandos disponibles:
    echo.
    echo  %0 up        - Levantar los contenedores
    echo  %0 down      - Detener los contenedores
    echo  %0 logs      - Ver logs en tiempo real
    echo  %0 logs-web  - Ver logs de la app PHP
    echo  %0 logs-db   - Ver logs de MySQL
    echo  %0 bash      - Acceder al contenedor PHP
    echo  %0 mysql     - Acceder a MySQL
    echo  %0 clean     - Detener y borrar TODO (incluida la BD)
    echo  %0 restart   - Reiniciar los contenedores
    echo  %0 ps        - Ver estado de los contenedores
    echo  %0 build     - Reconstruir la imagen PHP
    echo.
    exit /b 1
)

if "%1"=="up" (
    echo Levantando contenedores...
    docker-compose up -d --build
    timeout /t 3
    docker-compose ps
    echo.
    echo Accede a:
    echo  - Aplicacion: http://localhost:8080
    echo  - phpMyAdmin: http://localhost:8081
    exit /b 0
)

if "%1"=="down" (
    echo Deteniendo contenedores...
    docker-compose down
    exit /b 0
)

if "%1"=="logs" (
    echo Ver logs de todos los contenedores (Presiona Ctrl+C para salir)...
    docker-compose logs -f
    exit /b 0
)

if "%1"=="logs-web" (
    echo Ver logs de la app PHP (Presiona Ctrl+C para salir)...
    docker-compose logs -f web
    exit /b 0
)

if "%1"=="logs-db" (
    echo Ver logs de MySQL (Presiona Ctrl+C para salir)...
    docker-compose logs -f db
    exit /b 0
)

if "%1"=="bash" (
    echo Accediendo al contenedor PHP...
    docker exec -it inventario_web bash
    exit /b 0
)

if "%1"=="mysql" (
    echo Accediendo a MySQL...
    docker exec -it inventario_db mysql -u inventario_user -p inventario
    exit /b 0
)

if "%1"=="clean" (
    echo.
    echo !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
    echo ADVERTENCIA: Se eliminaran TODOS los datos
    echo !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
    echo.
    set /p confirmacion="¿Continuar? (s/n): "
    if /i "%confirmacion%"=="s" (
        echo Limpiando...
        docker-compose down -v
        echo Limpieza completada. La proxima vez que levantes los contenedores tendras una BD limpia.
    ) else (
        echo Cancelado.
    )
    exit /b 0
)

if "%1"=="restart" (
    echo Reiniciando contenedores...
    docker-compose restart
    docker-compose ps
    exit /b 0
)

if "%1"=="ps" (
    echo Estado de los contenedores:
    docker-compose ps
    exit /b 0
)

if "%1"=="build" (
    echo Reconstruyendo la imagen PHP...
    docker-compose build --no-cache web
    exit /b 0
)

echo Comando no reconocido: %1
exit /b 1
