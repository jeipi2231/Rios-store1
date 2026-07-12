# Sistema de Gestion para Despensa (PHP + MySQL + XAMPP)

Sistema web completo para gestionar ventas, inventario, dashboard y reportes, con autenticacion por roles (`admin`, `cajero`).

## 🚀 Instalación en XAMPP

### 1. Requisitos
- XAMPP instalado con Apache y MySQL activados
- PHP 8.0+ (viene con XAMPP)

### 2. Instalación
1. **Ejecuta `renombrar.bat`**: Este script cambia el nombre de la carpeta a "sistema" (sin espacios)
2. Copia la carpeta `sistema/` resultante a `C:\xampp\htdocs\`
3. Ejecuta `install.bat` dentro de la carpeta copiada (crea carpetas necesarias automáticamente)

La estructura final debería ser:
```
C:\xampp\htdocs\sistema\
├── app\
├── db\
├── config.env
├── etc...
```

### 3. Verificación
Antes de configurar la BD, ejecuta `verificar.php` en tu navegador para comprobar que todo esté bien:
- `http://localhost/sistema/verificar.php`

### 3. Base de datos
1. Abre phpMyAdmin: `http://localhost/phpmyadmin`
2. Crea una base de datos llamada `despensa_db`
3. Ve a la pestaña "SQL" y ejecuta el contenido del archivo `db/init.sql`

### 4. Configuración (opcional)
Si necesitas cambiar la configuración de BD, edita el archivo `config.env` con el Bloc de notas:
```
DB_HOST=localhost
DB_NAME=despensa_db
DB_USER=root
DB_PASS=
```

Este archivo ya está configurado para XAMPP por defecto. Solo edítalo si tienes una configuración especial.

### 5. Acceso al sistema
- URL: `http://localhost/sistema/index.php`
- Credenciales iniciales:
  - **Admin**: usuario `admin`, contraseña `12342231!`
  - **Cajero**: usuario `cajero`, contraseña `12342231`

## 📋 Funcionalidades incluidas

- ✅ Login y logout con sesiones de PHP
- ✅ Passwords encriptadas (bcrypt)
- ✅ Roles: `admin`, `cajero`
- ✅ CRUD completo de productos
- ✅ Códigos de barras opcionales
- ✅ Alertas de bajo stock
- ✅ Punto de venta con carrito
- ✅ Búsqueda por nombre o código de barras
- ✅ Descuento automático de stock al vender
- ✅ Impresión automática de ticket térmico
- ✅ Reimpresión manual de tickets
- ✅ Registro de movimientos de stock
- ✅ Dashboard diario con métricas
- ✅ Reportes por día y rango de fechas
- ✅ Categorías de productos
- ✅ Subida de fotos de productos
- ✅ Logs básicos en `logs/app.log`
- ✅ Montos en Guaraníes (Gs) con formato

## 🖨️ Ticket térmico

Al cobrar una venta, el sistema redirige automáticamente a:
- `/sistema/ventas/ticket.php?id=ID_VENTA&auto=1`

Esta página:
- Consulta cabecera y detalle de la venta
- Muestra ticket en formato térmico (80mm)
- Ejecuta `window.print()` automáticamente
- Incluye botón de reimpresión

Para reimprimir un ticket manualmente:
- `/sistema/ventas/ticket.php?id=ID_VENTA`

## 📁 Estructura de archivos

```
sistema/
├── app/                    # Código PHP principal
│   ├── index.php          # Página de inicio
│   ├── auth/              # Autenticación
│   ├── config/            # Configuración BD
│   ├── dashboard/         # Dashboard y reportes
│   ├── includes/          # Funciones auxiliares
│   ├── productos/         # Gestión de productos
│   ├── categorias/        # Gestión de categorías
│   ├── ventas/            # Punto de venta y tickets
│   └── assets/            # CSS, JS, imágenes
├── db/
│   └── init.sql           # Script de BD
├── logs/                  # Logs del sistema (crear manualmente)
└── README.md             # Este archivo
```

## 🔧 Notas técnicas

- Todas las consultas usan prepared statements con PDO
- El script SQL crea tablas y datos de ejemplo
- Las contraseñas están hasheadas con bcrypt
- Los uploads de imágenes están limitados a 50MB
- Compatible con PHP 8.0+ y MySQL 8.0+

## 🆘 Solución de problemas

### Error de conexión a BD
- Verifica que MySQL esté corriendo en XAMPP
- Confirma que la BD `despensa_db` existe
- Revisa credenciales en `app/config/database.php`

### Error de permisos en uploads
- Asegúrate de que la carpeta `assets/uploads/productos/` tenga permisos de escritura
- En Windows/XAMPP, normalmente no hay problemas

### Tickets no imprimen
- Los tickets usan CSS para formato térmico
- Configura la impresora para "sin márgenes" o "ticket térmico"
