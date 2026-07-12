# ✅ Checklist Docker - Sistema de Inventario

## Antes de empezar

Asegúrate de tener:
- [ ] Docker Desktop instalado ([descargar](https://www.docker.com/products/docker-desktop))
- [ ] Docker Compose v2+ 
- [ ] Al menos 2GB de RAM disponible
- [ ] Puerto 8080 disponible (la app)
- [ ] Puerto 8081 disponible (phpMyAdmin)
- [ ] Puerto 3306 disponible (MySQL)

Verifica:
```bash
docker --version
docker-compose --version
```

---

## Archivos creados/actualizados

✅ **Dockerfile**
- Actualizado con mejoras (uploads, permisos, optimizaciones)
- Incluye configuración de límites de PHP
- Crea carpetas de uploads automáticamente

✅ **docker-compose.yml**
- Configurado con 3 servicios: web, db, phpmyadmin
- Variables de entorno parametrizadas
- Healthcheck en MySQL
- Volúmenes configurados correctamente

✅ **.env.example**
- Template con todas las variables de configuración
- Contraseñas por defecto (cambiar en producción)
- Documentado

✅ **.gitignore**
- Excluye archivos sensibles (.env, credenciales, logs)
- Excluye uploads y datos temporales
- Seguro para Git

✅ **.dockerignore**
- Optimiza las builds de la imagen
- Excluye archivos innecesarios
- Acelera el proceso

✅ **database/init.sql**
- Schema completo de la BD
- Datos de prueba incluidos
- Relaciones y constraints correctas

✅ **src/.htaccess**
- Bloquea acceso a archivos sensibles (.env, .sql, .log)
- Habilita mod_rewrite
- Seguridad básica

✅ **DOCKER.md**
- Guía completa de Docker
- Comandos esenciales
- Troubleshooting

✅ **docker-helper.bat** (Windows)
- Script helper con comandos frecuentes
- Interfaz amigable
- 10 comandos disponibles

✅ **init.bat** (Windows)
- Inicialización automática
- Verifica requisitos
- Configura el proyecto de cero

✅ **src/config/database.php**
- Ya está configurado para leer variables de entorno
- Compatible con Docker
- Manejo de errores mejorado

✅ **src/setup.php**
- Ya existe y crea el usuario admin inicial
- Contraseñas hasheadas con bcrypt
- Listo para usarse

---

## Próximos pasos para ejecutar

### Opción 1: Inicialización automática (RECOMENDADO)
```bash
init.bat
```
Esto hace todo automáticamente.

### Opción 2: Paso a paso manual

1. **Copiar configuración:**
   ```bash
   copy .env.example .env
   ```

2. **Levantar contenedores:**
   ```bash
   docker-compose up -d --build
   ```

3. **Esperar a MySQL:**
   ```bash
   docker-compose ps
   # Esperar hasta que "inventario_db" muestre "healthy"
   ```

4. **Inicializar usuarios:**
   - Abrir: http://localhost:8080/setup.php

5. **Acceder:**
   - http://localhost:8080/login.php
   - Usuario: `admin@inventario.local`
   - Contraseña: `admin123`

---

## Verificación rápida

Una vez que esté corriendo:

```bash
# Ver estado
docker-compose ps

# Ver logs
docker-compose logs -f web

# Entrar al contenedor
docker exec -it inventario_web bash

# Ver MySQL
docker exec -it inventario_db mysql -u inventario_user -p inventario
```

---

## Configuración personalizada

Si quieres cambiar puertos o credenciales, edita `.env`:

```env
MYSQL_ROOT_PASSWORD=tu_password_nuevo
MYSQL_DATABASE=inventario
MYSQL_USER=inventario_user
MYSQL_PASSWORD=tu_password_nuevo
WEB_PORT=8080
MYSQL_PORT=3306
PMA_PORT=8081
```

⚠️ **Importante:** Cambios en `.env` requieren:
```bash
docker-compose down -v
docker-compose up -d --build
```

---

## Seguridad - Antes de producción

- [ ] Cambiar TODAS las contraseñas en `.env`
- [ ] Eliminar `src/setup.php`
- [ ] Configurar HTTPS (reverse proxy con Nginx)
- [ ] Cambiar `MYSQL_ROOT_PASSWORD`
- [ ] Usar `.env` con valores seguros
- [ ] Configurar backups automáticos
- [ ] Revisar logs regularmente
- [ ] Actualizar imagenes base: `docker-compose pull`

---

## Comandos rápidos (usa docker-helper.bat)

```bash
docker-helper.bat up        # Levantar
docker-helper.bat down      # Detener
docker-helper.bat logs      # Ver logs
docker-helper.bat bash      # Acceder PHP
docker-helper.bat mysql     # Acceder MySQL
docker-helper.bat restart   # Reiniciar
docker-helper.bat clean     # Limpiar todo
```

---

## Troubleshooting rápido

| Problema | Solución |
|----------|----------|
| Puerto 8080 en uso | Cambiar `WEB_PORT` en `.env` |
| MySQL no levanta | Esperar más, ver `docker-compose logs db` |
| No conecta a BD | Verificar que `db` esté `healthy` |
| Sin usuarios | Ejecutar `setup.php` en navegador |
| Empezar limpio | `docker-compose down -v && docker-compose up -d --build` |

---

## Archivos importantes a NO subir a Git

- `.env` (nunca)
- `uploads/*` (contenido dinámico)
- `logs/*` (contenido dinámico)
- `src/setup.php` (después del primer deploy)

Ver `.gitignore` para más detalles.

---

## Listo para continuar

Si todos los checkboxes están marcados, el sistema está **listo para usar en Docker**. 🚀

Pregunta: ¿Ejecutaste `init.bat` correctamente? ¿Viste el usuario admin creado?
