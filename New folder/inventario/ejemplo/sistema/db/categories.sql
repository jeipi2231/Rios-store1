CREATE TABLE IF NOT EXISTS categorias (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL UNIQUE,
  descripcion VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

ALTER TABLE productos ADD COLUMN categoria_id INT AFTER imagen, ADD CONSTRAINT fk_productos_categoria FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL;

INSERT INTO categorias (nombre, descripcion) VALUES
  ('Bebidas', 'Gaseosas, jugos, refrescos'),
  ('Dulces', 'Caramelos, chocolates, golosinas'),
  ('Alfajores', 'Alfajores de diversos sabores'),
  ('Aguas', 'Agua mineral, agua potable'),
  ('Granos', 'Arroz, frijol, lentejas'),
  ('Aceites', 'Aceites comestibles'),
  ('Otros', 'Otros productos diversos')
ON DUPLICATE KEY UPDATE nombre = nombre;

UPDATE productos SET categoria_id = 6 WHERE nombre = 'Arroz 1kg';
UPDATE productos SET categoria_id = 7 WHERE nombre = 'Aceite 900ml';
UPDATE productos SET categoria_id = 6 WHERE nombre = 'Frijol 1kg';
