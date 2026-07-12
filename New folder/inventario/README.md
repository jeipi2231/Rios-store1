# Sistema de Inventario · PHP + MySQL + Docker

Sistema web completo para gestión de inventario con **PHP 8.2**, **MySQL 8.0** y **Docker**. Permite administrar productos, categorías, proveedores, movimientos de stock (entradas/salidas), usuarios y generar reportes con alertas de stock bajo.

---

## 📦 Requisitos

- [Docker](https://docs.docker.com/get-docker/) 20+
- [Docker Compose](https://docs.docker.com/compose/install/) v2+

> No necesitas tener PHP ni MySQL instalados localmente. Todo corre dentro de los contenedores.

---

## 🚀 Instalación rápida

1. **Clonar o descomprimir** el proyecto en una carpeta.

2. **Levantar los contenedores:**
   ```bash
   docker-compose up -d --build
   ```
   Esto descargará las imágenes necesarias y construirá la imagen PHP personalizada. Tarda ~1-2 minutos la primera vez.

3. **Esperar a que MySQL esté listo** (unos 10-15 segundos). Verifica con:
   ```bash
   docker-compose ps
   ```
   El contenedor `inventario_db` debe mostrar `healthy`.

4. **Inicializar usuarios:** abre en el navegador:
   ```
   http://localhost:8080/setup.php
   ```
   Esto crea el usuario admin inicial. **Elimina `setup.php` después** por seguridad.

5. **Entrar al sistema:**
   ```
   http://localhost:8080/login.php
   ```
   - Usuario admin: `admin@inventario.local` / `admin123`

---

## 🌐 Servicios desplegados

| Servicio      | URL                          | Descripción                  |
|---------------|------------------------------|------------------------------|
| App PHP       | http://localhost:8080        | Aplicación de inventario     |
| phpMyAdmin    | http://localhost:8081        | Administrador de MySQL       |
| MySQL         | localhost:3306               | Base de datos (opcional p/ clientes externes) |

---

## 🧩 Funcionalidades

## 🧠 Guía rápida (principiantes)

1. Crea productos normales con `Código` y opcionalmente `Código de barras`.
2. Para carne/pesables, marca **Producto de balanza** y carga **Prefijo balanza** (7 dígitos).
3. En **Cajero**:
   - normales: escaneo común (unidad por unidad)
   - pesables: escaneo de etiqueta de balanza (peso en gramos, convertido a kg)
4. Confirma total, monto pagado y cobra para imprimir ticket.

### Módulos principales
- **Dashboard**: estadísticas generales, valor de inventario, últimos movimientos, top productos, alertas de stock bajo.
- **Productos**: CRUD completo con código, precios, stock, stock mínimo, categoría, proveedor, unidad. Búsqueda y filtro por categoría.
- **Cajero**: pantalla de cobro rápida para escáner, carrito, cálculo de vuelto y emisión de ticket.
- **Categorías**: CRUD con conteo de productos asociados.
- **Proveedores**: CRUD con datos de contacto (nombre, contacto, teléfono, email, dirección).
- **Movimientos**: registro de entradas/salidas con actualización automática del stock. Soporta filtros por tipo, producto y rango de fechas. Eliminación con reversión de stock.
- **Reportes**: alerta de stock bajo con cantidad sugerida, resumen por categoría y por proveedor, movimientos de los últimos 30 días.
- **Usuarios**: gestión de cuentas con roles `admin` y `usuario`. Solo los administradores acceden.

### Características técnicas
- ✅ Autenticación con sesiones y contraseñas hasheadas con **bcrypt**.
- ✅ Protección **CSRF** en todos los formularios.
- ✅ Consultas preparadas con **PDO** (protección contra SQL injection).
- ✅ Transacciones en movimientos de stock (consistencia garantizada).
- ✅ Interfaz responsive con **Bootstrap 5** y **Bootstrap Icons**.
- ✅ Arranque limpio: sin datos de prueba, listo para cargar información real.
- ✅ Soporte para productos de balanza por prefijo (7 dígitos) y escaneo directo en caja.

### Configuración de balanza en productos

1. En productos, marca **Producto de balanza**.
2. Carga el **Prefijo balanza** de 7 dígitos definido por tu balanza.
3. En caja, al escanear etiqueta de balanza, el sistema acepta:
   - 12 dígitos: `prefijo(7) + gramos(5)`
   - 13 dígitos EAN: `prefijo(7) + gramos(5) + control(1)`
   - 13 dígitos custom (algunas balanzas): `prefijo(7) + gramos(6)`
4. El sistema convierte automáticamente a kg y calcula subtotal con precio por kg.
5. Funciona igual si vende menos de 1 kg (ej. 0.450 kg) o más de 1 kg (ej. 1.275 kg).

---

## 🗂️ Estructura del proyecto

```
inventario/
├── docker-compose.yml      # Orquestación de servicios
├── Dockerfile              # Imagen PHP 8.2 + Apache
├── .env                    # Variables de entorno (credenciales, puertos)
├── .dockerignore
├── database/
│   └── init.sql            # Esquema + datos iniciales (se ejecuta al crear el contenedor)
└── src/                    # Código PHP (montado como volumen)
    ├── setup.php           # Instalación inicial de usuarios (¡eliminar después!)
    ├── login.php
    ├── logout.php
    ├── index.php           # Dashboard
    ├── products.php        # Productos
    ├── categories.php      # Categorías
    ├── suppliers.php       # Proveedores
    ├── movements.php       # Movimientos
    ├── reports.php         # Reportes
    ├── users.php           # Usuarios (solo admin)
    ├── config/
    │   └── database.php    # Conexión PDO + helpers
    ├── includes/
    │   ├── auth.php        # Sesiones, CSRF, roles
    │   ├── header.php      # Layout superior + navegación
    │   └── footer.php      # Layout inferior
    └── assets/
        ├── css/app.css
        └── js/app.js
```

---

## 🛠️ Comandos útiles

```bash
# Levantar
docker-compose up -d --build

# Ver logs
docker-compose logs -f web
docker-compose logs -f db

# Detener
docker-compose down

# Detener y borrar la base de datos (¡perderás los datos!)
docker-compose down -v

# Reiniciar un servicio
docker-compose restart web

# Acceder al contenedor PHP
docker exec -it inventario_web bash

# Acceder a MySQL desde el contenedor
docker exec -it inventario_db mysql -u inventario_user -p inventario
```

---

## 🔧 Configuración personalizada

Edita el archivo `.env` antes de levantar los contenedores:

```env
MYSQL_ROOT_PASSWORD=rootpass123    # Password de root de MySQL
MYSQL_DATABASE=inventario           # Nombre de la base de datos
MYSQL_USER=inventario_user          # Usuario de la app
MYSQL_PASSWORD=inventario_pass      # Password del usuario de la app
WEB_PORT=8080                       # Puerto de la app PHP
PMA_PORT=8081                       # Puerto de phpMyAdmin
DB_PORT=3306                        # Puerto de MySQL (host)
```

> ⚠️ Si cambias las credenciales después del primer arranque, debes borrar el volumen con `docker-compose down -v` y volver a levantar.

---

## 🗄️ Esquema de base de datos

| Tabla         | Descripción                                            |
|---------------|--------------------------------------------------------|
| `usuarios`    | Cuentas con rol (admin/usuario) y password bcrypt      |
| `categorias`  | Clasificación de productos                             |
| `proveedores` | Datos de contacto de proveedores                       |
| `productos`   | Catálogo con código, precios, stock, stock mínimo      |
| `movimientos` | Historial de entradas/salidas con producto y usuario   |

Relaciones:
- `productos.categoria_id → categorias.id`
- `productos.proveedor_id → proveedores.id`
- `movimientos.producto_id → productos.id`
- `movimientos.usuario_id → usuarios.id`

---

## 🔒 Seguridad

- Contraseñas hasheadas con `password_hash()` (bcrypt, cost 10).
- Tokens CSRF en todos los formularios POST.
- Consultas preparadas con PDO (sin SQL injection).
- Sesiones con `session_start()` y validación de rol por página.
- `.htaccess` bloquea acceso a archivos sensibles (`.env`, `.sql`, etc.).

**Recomendaciones de producción:**
1. Cambia TODAS las contraseñas por defecto en `.env`.
2. Elimina `setup.php` después del primer arranque.
3. Configura HTTPS (coloca un reverse proxy como Nginx/Caddy delante).
4. Haz backups periódicos: `docker exec inventario_db mysqldump -u root -p inventario > backup.sql`

---

## 📝 Licencia

Proyecto de ejemplo libre para uso educativo y comercial.

---

## ❓ Problemas comunes

**El contenedor `db` no pasa a `healthy`**
→ Espera 30-60 segundos. Si persiste, revisa los logs: `docker-compose logs db`.

**"No se pudo conectar a la base de datos"**
→ Verifica que los contenedores estén corriendo: `docker-compose ps`. Reinicia con `docker-compose restart`.

**No aparecen los usuarios al hacer login**
→ Ejecuta `http://localhost:8080/setup.php` para crearlos.

**Quiero empezar de cero con la base de datos**
→ `docker-compose down -v && docker-compose up -d --build` y luego vuelve a ejecutar `setup.php`.
