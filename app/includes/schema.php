<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function ensure_extended_schema(): void
{
    $pdo = getPDO();

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS proveedores (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(120) NOT NULL,
            telefono VARCHAR(30) NULL,
            email VARCHAR(120) NULL,
            direccion VARCHAR(180) NULL,
            notas VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS clientes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(100) NOT NULL,
            apellido VARCHAR(100) NOT NULL,
            ruc VARCHAR(30) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB"
    );

    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE()
         AND table_name = 'ventas'
         AND column_name = 'cliente_id'"
    );
    $hasClienteId = (int) $stmt->fetchColumn() > 0;

    if (!$hasClienteId) {
        $pdo->exec("ALTER TABLE ventas ADD COLUMN cliente_id INT NULL AFTER vuelto");
        $pdo->exec(
            "ALTER TABLE ventas
             ADD CONSTRAINT fk_ventas_cliente
             FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL"
        );
    }

    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE()
         AND table_name = 'ventas'
         AND column_name = 'metodo_pago'"
    );
    $hasMetodoPago = (int) $stmt->fetchColumn() > 0;

    if (!$hasMetodoPago) {
        $pdo->exec("ALTER TABLE ventas ADD COLUMN metodo_pago ENUM('efectivo','qr','transferencia') NOT NULL DEFAULT 'efectivo' AFTER vuelto");
    }

    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE()
         AND table_name = 'ventas'
         AND column_name = 'banco_pago'"
    );
    $hasBancoPago = (int) $stmt->fetchColumn() > 0;

    if (!$hasBancoPago) {
        $pdo->exec("ALTER TABLE ventas ADD COLUMN banco_pago VARCHAR(100) NULL AFTER metodo_pago");
    }

    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE()
         AND table_name = 'ventas'
         AND column_name = 'tipo_entrega'"
    );
    $hasTipoEntrega = (int) $stmt->fetchColumn() > 0;

    if (!$hasTipoEntrega) {
        $pdo->exec("ALTER TABLE ventas ADD COLUMN tipo_entrega ENUM('envio','retiro_tienda') NOT NULL DEFAULT 'retiro_tienda' AFTER banco_pago");
    }

    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE()
         AND table_name = 'movimientos_stock'
         AND column_name = 'motivo'"
    );
    $hasMotivo = (int) $stmt->fetchColumn() > 0;

    if (!$hasMotivo) {
        $pdo->exec("ALTER TABLE movimientos_stock ADD COLUMN motivo VARCHAR(255) NULL AFTER cantidad");
    }

    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE()
         AND table_name = 'movimientos_stock'
         AND column_name = 'monto'"
    );
    $hasMonto = (int) $stmt->fetchColumn() > 0;

    if (!$hasMonto) {
        $pdo->exec("ALTER TABLE movimientos_stock ADD COLUMN monto DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER motivo");
    }

    $stmt = $pdo->query(
        "SELECT IS_NULLABLE FROM information_schema.columns
         WHERE table_schema = DATABASE()
         AND table_name = 'movimientos_stock'
         AND column_name = 'producto_id'
         LIMIT 1"
    );
    $productoNullable = strtoupper((string) $stmt->fetchColumn()) === 'YES';

    if (!$productoNullable) {
        $pdo->exec("ALTER TABLE movimientos_stock MODIFY producto_id INT NULL");
    }

    $stmt = $pdo->query(
        "SELECT COLUMN_TYPE FROM information_schema.columns
         WHERE table_schema = DATABASE()
         AND table_name = 'movimientos_stock'
         AND column_name = 'tipo'
         LIMIT 1"
    );
    $tipoDef = strtolower((string) $stmt->fetchColumn());

    if (strpos($tipoDef, "'ingreso'") === false) {
        $pdo->exec("ALTER TABLE movimientos_stock MODIFY tipo ENUM('entrada','salida','ingreso') NOT NULL");
    }

    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE()
         AND table_name = 'productos'
         AND column_name = 'proveedor_id'"
    );
    $hasProveedorId = (int) $stmt->fetchColumn() > 0;

    if (!$hasProveedorId) {
        $pdo->exec("ALTER TABLE productos ADD COLUMN proveedor_id INT NULL AFTER categoria_id");
    }

    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.key_column_usage
         WHERE table_schema = DATABASE()
         AND table_name = 'productos'
         AND column_name = 'proveedor_id'
         AND referenced_table_name = 'proveedores'"
    );
    $hasProveedorFk = (int) $stmt->fetchColumn() > 0;

    if (!$hasProveedorFk) {
        $pdo->exec(
            "ALTER TABLE productos
             ADD CONSTRAINT fk_productos_proveedor
             FOREIGN KEY (proveedor_id) REFERENCES proveedores(id)
             ON DELETE SET NULL"
        );
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS cotizaciones (
            id INT AUTO_INCREMENT PRIMARY KEY,
            fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            total DECIMAL(10,2) NOT NULL,
            metodo_pago ENUM('efectivo','qr','transferencia') NOT NULL DEFAULT 'efectivo',
            banco_pago VARCHAR(100) NULL,
            tipo_entrega ENUM('envio','retiro_tienda') NOT NULL DEFAULT 'retiro_tienda',
            cliente_id INT NULL,
            usuario_id INT NOT NULL,
            CONSTRAINT fk_cotizaciones_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
            CONSTRAINT fk_cotizaciones_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ) ENGINE=InnoDB"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS detalle_cotizaciones (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cotizacion_id INT NOT NULL,
            producto_id INT NOT NULL,
            cantidad INT NOT NULL,
            precio_unitario DECIMAL(10,2) NOT NULL,
            subtotal DECIMAL(10,2) NOT NULL,
            CONSTRAINT fk_detalle_cotizacion FOREIGN KEY (cotizacion_id) REFERENCES cotizaciones(id) ON DELETE CASCADE,
            CONSTRAINT fk_detalle_cotizacion_producto FOREIGN KEY (producto_id) REFERENCES productos(id)
        ) ENGINE=InnoDB"
    );
}
