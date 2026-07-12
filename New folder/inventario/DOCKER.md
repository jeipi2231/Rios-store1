# 🐳 Guía de Docker - Sistema de Inventario

## Inicio rápido

```bash
# 1. Levantar los contenedores (primera vez tarda 1-2 minutos)
docker-compose up -d --build

# 2. Esperar a que MySQL esté listo (~15 segundos)
docker-compose ps

# 3. Inicializar usuarios
# Abre: http://localhost:8080/setup.php

# 4. Inicia sesión
# http://localhost:8080/login.php
# Usuario: admin@inventario.local
# Contraseña: admin123
```

---

## 📋 Servicios

| Servicio   | URL / Puerto          | Usuario               | Contraseña            |
|------------|-----------------------|-----------------------|-----------------------|
| App PHP    | http://localhost:8080 | -                     | -                     |
| phpMyAdmin | http://localhost:8081 | root                  | rootpass123           |
| MySQL      | localhost:3306        | inventario_user       | inventario_pass       |

---

## 🛠️ Comandos esenciales

### Levantar / Detener

```bash
# Levantar en background (recomendado)
docker-compose up -d --build

# Ver estado
docker-compose ps

# Detener
docker-compose down

# Detener y borrar la BD (¡perderás los datos!)
docker-compose down -v
```

### Logs

```bash
# Ver logs de todos los servicios
docker-compose logs -f

# Ver logs de solo la app PHP
docker-compose logs -f web

# Ver logs de solo MySQL
docker-compose logs -f db

# Salir: Presiona Ctrl+C
```

### Acceder a los contenedores

```bash
# Bash en el contenedor PHP
docker exec -it inventario_web bash

# MySQL desde el contenedor
docker exec -it inventario_db mysql -u inventario_user -p inventario

# Ejecutar comando puntual en PHP
docker exec inventario_web php -v
```

### Mantenimiento

```bash
# Reiniciar un servicio
docker-compose restart web
docker-compose restart db

# Reconstruir la imagen PHP (sin cachés)
docker-compose build --no-cache web

# Ver uso de disco
docker system df

# Limpiar imágenes/contenedores no usados
docker system prune -a
```

---

## 🔧 Configuración

Edita `.env` **antes** de levantar los contenedores:

```env
MYSQL_ROOT_PASSWORD=rootpass123    # Root de MySQL
MYSQL_DATABASE=inventario           # Nombre BD
MYSQL_USER=inventario_user          # Usuario BD
MYSQL_PASSWORD=inventario_pass      # Password BD
WEB_PORT=8080                       # Puerto app PHP
MYSQL_PORT=3306                     # Puerto MySQL
PMA_PORT=8081                       # Puerto phpMyAdmin
```

> ⚠️ **Importante:** Si cambias credenciales después del primer arranque, necesitas:
> ```bash
> docker-compose down -v
> docker-compose up -d --build
> ```

---

## 📁 Estructura Docker

```
inventario/
├── docker-compose.yml      # Orquestación
├── Dockerfile              # Imagen PHP personalizada
├── .dockerignore
├── .env.example            # Template de configuración
├── docker-helper.bat       # Script helper (Windows)
├── database/
│   └── init.sql            # Datos iniciales (se ejecuta al crear la BD)
└── src/                    # Código PHP (volumen montado)
```

### Volúmenes

- **`./src:/var/www/html`** - Código PHP (sincronización en vivo)
- **`db_data`** - Datos de MySQL (persistencia)
- **`./database/init.sql`** - Script de inicialización de BD

---

## 🚨 Troubleshooting

### El contenedor `db` no pasa a `healthy`

```bash
# Ver los logs de MySQL
docker-compose logs db

# Esperar más tiempo (a veces tarda 30-60 seg)
docker-compose ps

# Reintentar
docker-compose restart db
```

### "No se pudo conectar a la base de datos"

```bash
# Verificar que todos los contenedores estén corriendo
docker-compose ps

# Si falta alguno, reiniciar
docker-compose restart

# Ver logs
docker-compose logs web
```

### No aparecen los usuarios en login

```bash
# Ejecutar setup.php nuevamente
# http://localhost:8080/setup.php

# O insertar manualmente desde MySQL:
docker exec -it inventario_db mysql -u root -p inventario
# SQL> INSERT INTO usuarios VALUES (...);
```

### Quiero empezar con la BD limpia

```bash
docker-compose down -v
docker-compose up -d --build
# Luego ejecuta setup.php
```

### El puerto 8080 ya está en uso

```bash
# Edita .env y cambia WEB_PORT a otro valor (ej: 8082)
# Luego reinicia
docker-compose down
docker-compose up -d --build
```

---

## 🐛 Debug

### Ver el PHP.ini dentro del contenedor

```bash
docker exec inventario_web php -i
```

### Listar archivos del contenedor

```bash
docker exec inventario_web ls -la /var/www/html
```

### Ver variables de entorno

```bash
docker exec inventario_web printenv
```

### Ejecutar un script PHP manualmente

```bash
docker exec inventario_web php /var/www/html/setup.php
```

---

## 📦 Archivos Dockerfile personalizados

El `Dockerfile` personalizado incluye:

- **PHP 8.2** con Apache
- **Extensiones:** PDO, PDO MySQL, MySQLi
- **Módulo Apache:** Rewrite (para .htaccess)
- **Límites de upload:** 50MB (configurables)
- **Permisos:** Carpetas de uploads con permisos 777

---

## ✅ Checklist de producción

Si quieres desplegar en producción:

- [ ] Cambia TODAS las contraseñas en `.env`
- [ ] Elimina `setup.php` del servidor
- [ ] Activa HTTPS (coloca Nginx/Caddy como reverse proxy)
- [ ] Aumenta los límites de memoria si es necesario
- [ ] Configura backups automáticos: `docker exec inventario_db mysqldump -u root -p inventario > backup.sql`
- [ ] Monitorea logs regularmente

---

## 📚 Referencias

- [Docker Compose Documentation](https://docs.docker.com/compose/)
- [PHP Docker Official Image](https://hub.docker.com/_/php)
- [MySQL Docker Official Image](https://hub.docker.com/_/mysql)
- [phpMyAdmin Docker Image](https://hub.docker.com/r/phpmyadmin/phpmyadmin)
