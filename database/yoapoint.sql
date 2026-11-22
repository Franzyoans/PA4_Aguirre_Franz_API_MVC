/* =====================================================================
   PROCESO DE APRENDIZAJE 3 y 4 - Programación Web Básica (ISIL)
   Base de datos : yoapoint  (tienda de tecnología y equipos de cómputo)
   Proyecto      : PA2 - Foco administrativo (productos, clientes, pedidos)
   Motor         : MySQL / MariaDB (XAMPP) - InnoDB, utf8mb4
   Autor         : Aguirre Adrianzen Franz Yoans
   Uso           : phpMyAdmin > Importar  (o)  mysql -u root < yoapoint.sql
   ===================================================================== */

-- 1. CREACIÓN DE LA BASE DE DATOS (desde cero) -------------------------
SET NAMES utf8mb4;   -- evita problemas con tildes y ñ al importar
DROP DATABASE IF EXISTS yoapoint;
CREATE DATABASE yoapoint
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE yoapoint;

-- 2. CREACIÓN DE TABLAS ------------------------------------------------

-- 2.1 Categorías de productos
CREATE TABLE categorias (
  id_categoria INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre       VARCHAR(60)  NOT NULL,
  descripcion  VARCHAR(150) NULL,
  estado       TINYINT(1)   NOT NULL DEFAULT 1,
  CONSTRAINT pk_categorias PRIMARY KEY (id_categoria),
  CONSTRAINT uq_categorias_nombre UNIQUE (nombre)
) ENGINE=InnoDB;

-- 2.2 Proveedores
CREATE TABLE proveedores (
  id_proveedor INT UNSIGNED NOT NULL AUTO_INCREMENT,
  razon_social VARCHAR(100) NOT NULL,
  ruc          CHAR(11)     NOT NULL,
  contacto     VARCHAR(80)  NULL,
  telefono     VARCHAR(15)  NULL,
  correo       VARCHAR(100) NULL,
  estado       TINYINT(1)   NOT NULL DEFAULT 1,
  CONSTRAINT pk_proveedores PRIMARY KEY (id_proveedor),
  CONSTRAINT uq_proveedores_ruc UNIQUE (ruc)
) ENGINE=InnoDB;

-- 2.3 Productos (depende de categorias y proveedores)
CREATE TABLE productos (
  id_producto    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  codigo         VARCHAR(20)   NOT NULL,
  nombre         VARCHAR(120)  NOT NULL,
  descripcion    VARCHAR(255)  NULL,
  precio_venta   DECIMAL(10,2) NOT NULL,
  stock          INT UNSIGNED  NOT NULL DEFAULT 0,
  id_categoria   INT UNSIGNED  NOT NULL,
  id_proveedor   INT UNSIGNED  NOT NULL,
  estado         TINYINT(1)    NOT NULL DEFAULT 1,
  fecha_registro TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT pk_productos PRIMARY KEY (id_producto),
  CONSTRAINT uq_productos_codigo UNIQUE (codigo),
  CONSTRAINT ck_productos_precio CHECK (precio_venta > 0),
  CONSTRAINT fk_productos_categoria FOREIGN KEY (id_categoria)
    REFERENCES categorias (id_categoria) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_productos_proveedor FOREIGN KEY (id_proveedor)
    REFERENCES proveedores (id_proveedor) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 2.4 Clientes (empresa o público en general)
CREATE TABLE clientes (
  id_cliente   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tipo_cliente ENUM('PERSONA','EMPRESA') NOT NULL DEFAULT 'PERSONA',
  nombre       VARCHAR(100) NOT NULL COMMENT 'Nombres y apellidos o razón social',
  documento    VARCHAR(11)  NOT NULL COMMENT 'DNI (8) o RUC (11)',
  correo       VARCHAR(100) NULL,
  telefono     VARCHAR(15)  NULL,
  direccion    VARCHAR(150) NULL,
  fecha_registro TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT pk_clientes PRIMARY KEY (id_cliente),
  CONSTRAINT uq_clientes_documento UNIQUE (documento)
) ENGINE=InnoDB;

-- 2.5 Usuarios del sistema administrativo
CREATE TABLE usuarios (
  id_usuario INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre     VARCHAR(100) NOT NULL,
  correo     VARCHAR(100) NOT NULL,
  clave_hash CHAR(64)     NOT NULL COMMENT 'SHA-256 (en PHP usar password_hash)',
  rol        ENUM('ADMIN','VENDEDOR','ALMACEN') NOT NULL DEFAULT 'VENDEDOR',
  estado     TINYINT(1)   NOT NULL DEFAULT 1,
  CONSTRAINT pk_usuarios PRIMARY KEY (id_usuario),
  CONSTRAINT uq_usuarios_correo UNIQUE (correo)
) ENGINE=InnoDB;

-- 2.6 Pedidos (cabecera)
CREATE TABLE pedidos (
  id_pedido  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_cliente INT UNSIGNED NOT NULL,
  id_usuario INT UNSIGNED NOT NULL COMMENT 'Usuario que registra el pedido',
  fecha      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  estado     ENUM('PENDIENTE','PAGADO','ENVIADO','ANULADO') NOT NULL DEFAULT 'PENDIENTE',
  total      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  CONSTRAINT pk_pedidos PRIMARY KEY (id_pedido),
  CONSTRAINT ck_pedidos_total CHECK (total >= 0),
  CONSTRAINT fk_pedidos_cliente FOREIGN KEY (id_cliente)
    REFERENCES clientes (id_cliente) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_pedidos_usuario FOREIGN KEY (id_usuario)
    REFERENCES usuarios (id_usuario) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 2.7 Detalle de pedidos (relación muchos a muchos pedidos-productos)
CREATE TABLE detalle_pedido (
  id_detalle      INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  id_pedido       INT UNSIGNED  NOT NULL,
  id_producto     INT UNSIGNED  NOT NULL,
  cantidad        INT UNSIGNED  NOT NULL,
  precio_unitario DECIMAL(10,2) NOT NULL,
  subtotal        DECIMAL(12,2) NOT NULL,
  CONSTRAINT pk_detalle_pedido PRIMARY KEY (id_detalle),
  CONSTRAINT uq_detalle_pedido_producto UNIQUE (id_pedido, id_producto),
  CONSTRAINT ck_detalle_cantidad CHECK (cantidad > 0),
  CONSTRAINT ck_detalle_precio CHECK (precio_unitario > 0),
  CONSTRAINT fk_detalle_pedido FOREIGN KEY (id_pedido)
    REFERENCES pedidos (id_pedido) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_detalle_producto FOREIGN KEY (id_producto)
    REFERENCES productos (id_producto) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 3. ÍNDICES (para búsquedas y reportes frecuentes) ---------------------
CREATE INDEX idx_productos_nombre   ON productos (nombre);
CREATE INDEX idx_productos_categoria ON productos (id_categoria, precio_venta);
CREATE INDEX idx_clientes_nombre    ON clientes (nombre);
CREATE INDEX idx_pedidos_fecha      ON pedidos (fecha);
CREATE INDEX idx_pedidos_cliente    ON pedidos (id_cliente, estado);

-- 4. PROCEDIMIENTOS ALMACENADOS ----------------------------------------
DELIMITER //

-- 4.1 Agrega un producto a un pedido: valida stock, guarda el detalle con el
--     precio vigente, descuenta el stock y recalcula el total del pedido.
CREATE PROCEDURE sp_agregar_detalle_pedido(
  IN p_id_pedido   INT UNSIGNED,
  IN p_id_producto INT UNSIGNED,
  IN p_cantidad    INT UNSIGNED
)
BEGIN
  DECLARE v_precio DECIMAL(10,2);
  DECLARE v_stock  INT UNSIGNED;

  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    RESIGNAL;
  END;

  START TRANSACTION;

  SELECT precio_venta, stock INTO v_precio, v_stock
  FROM productos
  WHERE id_producto = p_id_producto AND estado = 1
  FOR UPDATE;

  IF v_precio IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Producto no existe o está inactivo';
  END IF;

  IF p_cantidad = 0 OR p_cantidad > v_stock THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cantidad inválida o stock insuficiente';
  END IF;

  INSERT INTO detalle_pedido (id_pedido, id_producto, cantidad, precio_unitario, subtotal)
  VALUES (p_id_pedido, p_id_producto, p_cantidad, v_precio, v_precio * p_cantidad);

  UPDATE productos SET stock = stock - p_cantidad WHERE id_producto = p_id_producto;

  UPDATE pedidos
  SET total = (SELECT SUM(subtotal) FROM detalle_pedido WHERE id_pedido = p_id_pedido)
  WHERE id_pedido = p_id_pedido;

  COMMIT;
END //

-- 4.2 Reporte administrativo: ventas por categoría (excluye pedidos anulados)
CREATE PROCEDURE sp_ventas_por_categoria()
BEGIN
  SELECT c.nombre AS categoria,
         COALESCE(SUM(d.cantidad), 0) AS unidades_vendidas,
         COALESCE(SUM(d.subtotal), 0) AS monto_vendido
  FROM categorias c
  LEFT JOIN productos p      ON p.id_categoria = c.id_categoria
  LEFT JOIN detalle_pedido d ON d.id_producto  = p.id_producto
  LEFT JOIN pedidos ped      ON ped.id_pedido  = d.id_pedido AND ped.estado <> 'ANULADO'
  GROUP BY c.id_categoria, c.nombre
  ORDER BY monto_vendido DESC;
END //

DELIMITER ;

-- 5. VISTA (consulta administrativa lista para usar en PHP) -------------
CREATE VIEW vw_pedidos_resumen AS
SELECT ped.id_pedido, ped.fecha, cli.nombre AS cliente, cli.tipo_cliente,
       usu.nombre AS vendedor, ped.estado, ped.total
FROM pedidos ped
INNER JOIN clientes cli ON cli.id_cliente = ped.id_cliente
INNER JOIN usuarios usu ON usu.id_usuario = ped.id_usuario;

-- 6. DATOS DE PRUEBA (máximo 10 registros por tabla) --------------------
INSERT INTO categorias (nombre, descripcion) VALUES
 ('Laptops',           'Laptops para estudio, oficina y gaming'),
 ('PCs de escritorio', 'Equipos armados y all-in-one'),
 ('Monitores',         'Monitores Full HD, 2K y 4K'),
 ('Periféricos',       'Teclados, mouse y audio'),
 ('Componentes',       'Procesadores, memoria RAM, SSD y tarjetas de video'),
 ('Impresoras y redes','Impresoras, routers y switches');

INSERT INTO proveedores (razon_social, ruc, contacto, telefono, correo) VALUES
 ('Distribuidora Tecno Perú S.A.C.', '20512345678', 'Carlos Rojas',   '987111222', 'ventas@tecnoperu.pe'),
 ('Importaciones Digital Lima S.A.C.', '20523456789', 'María Salazar', '987333444', 'contacto@digitallima.pe'),
 ('Global Hardware E.I.R.L.',        '20534567890', 'Jorge Paredes',  '987555666', 'jparedes@globalhw.pe'),
 ('Redes y Conectividad S.A.',       '20545678901', 'Ana Villanueva', '987777888', 'ana@redesyconect.pe');

INSERT INTO productos (codigo, nombre, descripcion, precio_venta, stock, id_categoria, id_proveedor) VALUES
 ('LAP-001', 'Laptop 15.6" Core i5 16 GB 512 GB SSD', 'Laptop para estudio y oficina',      2799.00, 15, 1, 1),
 ('LAP-002', 'Laptop 14" Ryzen 5 8 GB 256 GB SSD',    'Laptop ligera para estudiantes',     2199.00, 12, 1, 1),
 ('PCE-001', 'PC de escritorio Ryzen 5 16 GB 1 TB SSD','Equipo armado para oficina',        2499.00, 10, 2, 2),
 ('MON-001', 'Monitor 24" Full HD IPS 75 Hz',          'Monitor para oficina y hogar',       459.00, 25, 3, 2),
 ('MON-002', 'Monitor 27" 2K 144 Hz',                  'Monitor para gaming y diseño',       999.00, 10, 3, 2),
 ('PER-001', 'Combo teclado y mouse inalámbrico',      'Combo para oficina',                  89.00, 40, 4, 3),
 ('PER-002', 'Audífonos con micrófono USB',            'Audífonos para teletrabajo',         129.00, 30, 4, 3),
 ('COM-001', 'Tarjeta de video 8 GB GDDR6',            'Tarjeta gráfica de gama media',     1350.00,  8, 5, 3),
 ('COM-002', 'SSD NVMe 1 TB',                          'Unidad de estado sólido',            329.00, 35, 5, 1),
 ('IMP-001', 'Impresora multifuncional WiFi',          'Impresora para hogar y oficina',     679.00, 14, 6, 4);

INSERT INTO clientes (tipo_cliente, nombre, documento, correo, telefono, direccion) VALUES
 ('PERSONA', 'Lucía Fernández Ríos',          '71234567',    'lucia.fernandez@mail.com', '999111001', 'Av. Arequipa 1234, Lima'),
 ('PERSONA', 'Diego Ramírez Soto',            '72345678',    'diego.ramirez@mail.com',   '999111002', 'Jr. Cusco 456, Lima'),
 ('PERSONA', 'Valeria Campos Díaz',           '73456789',    'valeria.campos@mail.com',  '999111003', 'Av. Brasil 789, Jesús María'),
 ('PERSONA', 'Renzo Huamán Lazo',             '74567890',    'renzo.huaman@mail.com',    '999111004', 'Calle Los Pinos 321, Surco'),
 ('EMPRESA', 'Colegio San Martín S.A.C.',     '20601234567', 'compras@sanmartin.edu.pe', '014445551', 'Av. La Marina 2000, San Miguel'),
 ('EMPRESA', 'Estudio Contable Vega E.I.R.L.','20602345678', 'admin@estudiovega.pe',     '014445552', 'Av. Javier Prado 1500, San Isidro'),
 ('EMPRESA', 'Clínica Santa Rosa S.A.',       '20603456789', 'logistica@clinicasr.pe',   '014445553', 'Av. Salaverry 900, Jesús María'),
 ('PERSONA', 'Camila Torres Vera',            '75678901',    'camila.torres@mail.com',   '999111005', 'Av. Universitaria 650, Los Olivos');

INSERT INTO usuarios (nombre, correo, clave_hash, rol) VALUES
 ('Franz Aguirre',   'admin@yoapoint.pe',    SHA2('Admin123', 256),    'ADMIN'),
 ('Sofía Medina',    'smedina@yoapoint.pe',  SHA2('Vendedor123', 256), 'VENDEDOR'),
 ('Marco Quispe',    'mquispe@yoapoint.pe',  SHA2('Vendedor456', 256), 'VENDEDOR'),
 ('Elena Paredes',   'eparedes@yoapoint.pe', SHA2('Almacen123', 256),  'ALMACEN');

-- Pedidos (cabecera): el total se calcula con el procedimiento almacenado
INSERT INTO pedidos (id_cliente, id_usuario, fecha, estado) VALUES
 (1, 2, '2026-10-05 10:15:00', 'PAGADO'),
 (5, 2, '2026-10-08 11:30:00', 'ENVIADO'),
 (2, 3, '2026-10-10 16:45:00', 'PAGADO'),
 (6, 3, '2026-10-14 09:20:00', 'PENDIENTE'),
 (7, 2, '2026-10-18 15:10:00', 'PAGADO'),
 (3, 3, '2026-10-22 12:00:00', 'PENDIENTE');

-- Detalle de pedidos mediante el SP (valida stock, descuenta y calcula total)
CALL sp_agregar_detalle_pedido(1, 1, 1);   -- Laptop Core i5
CALL sp_agregar_detalle_pedido(1, 6, 1);   -- Combo teclado y mouse
CALL sp_agregar_detalle_pedido(2, 3, 3);   -- 3 PCs de escritorio (colegio)
CALL sp_agregar_detalle_pedido(2, 4, 3);   -- 3 monitores 24"
CALL sp_agregar_detalle_pedido(3, 8, 1);   -- Tarjeta de video
CALL sp_agregar_detalle_pedido(4, 2, 2);   -- 2 laptops Ryzen 5 (estudio contable)
CALL sp_agregar_detalle_pedido(5, 10, 1);  -- Impresora
CALL sp_agregar_detalle_pedido(5, 9, 2);   -- 2 SSD
CALL sp_agregar_detalle_pedido(6, 5, 1);   -- Monitor 27" 2K
CALL sp_agregar_detalle_pedido(6, 7, 1);   -- Audífonos USB

-- 7. CONSULTAS DE VERIFICACIÓN (opcional) ------------------------------
-- SELECT * FROM vw_pedidos_resumen;
-- CALL sp_ventas_por_categoria();
-- SELECT p.nombre, p.stock FROM productos p ORDER BY p.stock;
