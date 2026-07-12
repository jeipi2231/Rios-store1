DROP DATABASE IF EXISTS despensa_db;
CREATE DATABASE despensa_db;
USE despensa_db;

CREATE TABLE usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  usuario VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  rol ENUM('admin', 'cajero') NOT NULL DEFAULT 'cajero'
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS categorias (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL UNIQUE,
  descripcion VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS proveedores (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  telefono VARCHAR(30) NULL,
  email VARCHAR(120) NULL,
  direccion VARCHAR(180) NULL,
  notas VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS productos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(150) NOT NULL,
  codigo_barras VARCHAR(80) NULL UNIQUE,
  precio_compra DECIMAL(10,2) NOT NULL,
  precio_venta DECIMAL(10,2) NOT NULL,
  stock INT NOT NULL DEFAULT 0,
  stock_minimo INT NOT NULL DEFAULT 5,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  categoria_id INT,
  proveedor_id INT,
  imagen VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_productos_categoria FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL,
  CONSTRAINT fk_productos_proveedor FOREIGN KEY (proveedor_id) REFERENCES proveedores(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS clientes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  apellido VARCHAR(100) NOT NULL,
  ruc VARCHAR(30) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ventas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  total DECIMAL(10,2) NOT NULL,
  monto_pagado DECIMAL(10,2) DEFAULT 0,
  vuelto DECIMAL(10,2) DEFAULT 0,
  metodo_pago ENUM('efectivo', 'qr', 'transferencia') NOT NULL DEFAULT 'efectivo',
  banco_pago VARCHAR(100) NULL,
  tipo_entrega ENUM('envio', 'retiro_tienda') NOT NULL DEFAULT 'retiro_tienda',
  cliente_id INT NULL,
  usuario_id INT NOT NULL,
  CONSTRAINT fk_ventas_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
  CONSTRAINT fk_ventas_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS detalle_ventas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  venta_id INT NOT NULL,
  producto_id INT NOT NULL,
  cantidad INT NOT NULL,
  precio_unitario DECIMAL(10,2) NOT NULL,
  subtotal DECIMAL(10,2) NOT NULL,
  CONSTRAINT fk_detalle_venta FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE CASCADE,
  CONSTRAINT fk_detalle_producto FOREIGN KEY (producto_id) REFERENCES productos(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS movimientos_stock (
  id INT AUTO_INCREMENT PRIMARY KEY,
  producto_id INT NULL,
  tipo ENUM('entrada', 'salida', 'ingreso') NOT NULL,
  cantidad INT NOT NULL,
  motivo VARCHAR(255) NULL,
  monto DECIMAL(12,2) NOT NULL DEFAULT 0,
  fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_mov_producto FOREIGN KEY (producto_id) REFERENCES productos(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cotizaciones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  total DECIMAL(10,2) NOT NULL,
  metodo_pago ENUM('efectivo', 'qr', 'transferencia') NOT NULL DEFAULT 'efectivo',
  banco_pago VARCHAR(100) NULL,
  tipo_entrega ENUM('envio', 'retiro_tienda') NOT NULL DEFAULT 'retiro_tienda',
  cliente_id INT NULL,
  usuario_id INT NOT NULL,
  CONSTRAINT fk_cotizaciones_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
  CONSTRAINT fk_cotizaciones_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS detalle_cotizaciones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cotizacion_id INT NOT NULL,
  producto_id INT NOT NULL,
  cantidad INT NOT NULL,
  precio_unitario DECIMAL(10,2) NOT NULL,
  subtotal DECIMAL(10,2) NOT NULL,
  CONSTRAINT fk_detalle_cotizacion FOREIGN KEY (cotizacion_id) REFERENCES cotizaciones(id) ON DELETE CASCADE,
  CONSTRAINT fk_detalle_cotizacion_producto FOREIGN KEY (producto_id) REFERENCES productos(id)
) ENGINE=InnoDB;

INSERT INTO usuarios (nombre, usuario, password, rol)
VALUES
  ('Administrador', 'admin', '$2b$12$IOqbkddixlbHaQcXJhKq2OG4R12QGYzuVJCuf0ufaXKQeAx7O6xwW', 'admin'),
  ('Cajero', 'cajero', '$2b$12$9FCPHV30tQAE.ZVddl5UKuRmcVOnRoooFXFkbvvaprSoSl.n.PXeG', 'cajero')
ON DUPLICATE KEY UPDATE
  nombre = VALUES(nombre),
  password = VALUES(password),
  rol = VALUES(rol);

INSERT INTO categorias (nombre, descripcion)
VALUES
  ('Bebidas', 'Gaseosas, jugos, refrescos'),
  ('Dulces', 'Caramelos, chocolates, golosinas'),
  ('Alfajores', 'Alfajores de diversos sabores'),
  ('Aguas', 'Agua mineral, agua potable'),
  ('Granos', 'Arroz, frijol, lentejas'),
  ('Aceites', 'Aceites comestibles'),
  ('Otros', 'Otros productos diversos')
ON DUPLICATE KEY UPDATE nombre = nombre;

INSERT INTO productos (nombre, codigo_barras, precio_compra, precio_venta, stock, stock_minimo, categoria_id)
VALUES
  ('Arroz 1kg', '750100000001', 3500.00, 5000.00, 30, 8, 6),
  ('Aceite 900ml', '750100000002', 12000.00, 16500.00, 20, 5, 7),
  ('Frijol 1kg', NULL, 5500.00, 8000.00, 15, 6, 6)
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);
