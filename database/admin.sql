-- Minimarket Mass Arequipa · funciones del Administrador · MySQL 8 (Laragon)
-- Agrega: proveedores, compras de mercadería (con su detalle) e historial de actividades.
-- Correr una sola vez, después de cuadre.sql (usa LOTE.FECHA_RECEPCION).
-- Se puede volver a correr: las tablas usan IF NOT EXISTS y los datos de prueba se actualizan.
-- Córrelo con --default-character-set=utf8mb4 para que las tildes y la ñ se guarden bien:
--   mysql -u root -P 3306 --default-character-set=utf8mb4 minimarket_mass -e "source database/admin.sql"

SET NAMES utf8mb4;
USE minimarket_mass;

-- ---------------------------------------------------------
-- PROVEEDOR: a quién se le compra la mercadería
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS PROVEEDOR (
  ID_PROVEEDOR INT NOT NULL,
  RUC CHAR(11) NOT NULL,
  RAZON_SOCIAL VARCHAR(120) NOT NULL,
  RUBRO VARCHAR(150) NULL,                  -- qué le vende a Mass (lácteos, abarrotes...)
  CATEGORIA_SKU CHAR(3) NULL,               -- de qué categoría son sus productos: ABA, LAC, BEB o LIM (NULL = de todo)
  CONTACTO VARCHAR(80) NULL,
  TELEFONO VARCHAR(15) NULL,
  CORREO VARCHAR(120) NULL,
  ESTADO CHAR(1) NOT NULL DEFAULT 'A',
  FECHA_REGISTRO DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT PK_PROVEEDOR PRIMARY KEY (ID_PROVEEDOR),
  CONSTRAINT UQ_PRV_RUC UNIQUE (RUC),
  CONSTRAINT CK_PRV_1 CHECK (RUC REGEXP '^(10|20)[0-9]{9}$'),
  CONSTRAINT CK_PRV_2 CHECK (ESTADO IN ('A','I'))
) ENGINE = InnoDB;

-- Si PROVEEDOR ya existía de antes (sin la columna RUBRO), se la agrega
SET @hay_rubro := (SELECT COUNT(*) FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'PROVEEDOR' AND COLUMN_NAME = 'RUBRO');
SET @sql_rubro := IF(@hay_rubro = 0, 'ALTER TABLE PROVEEDOR ADD COLUMN RUBRO VARCHAR(150) NULL AFTER RAZON_SOCIAL', 'DO 0');
PREPARE agregar_rubro FROM @sql_rubro;
EXECUTE agregar_rubro;
DEALLOCATE PREPARE agregar_rubro;

-- Y la columna CATEGORIA_SKU (para que al comprar solo salgan sus productos)
SET @hay_cat := (SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'PROVEEDOR' AND COLUMN_NAME = 'CATEGORIA_SKU');
SET @sql_cat := IF(@hay_cat = 0, 'ALTER TABLE PROVEEDOR ADD COLUMN CATEGORIA_SKU CHAR(3) NULL AFTER RUBRO', 'DO 0');
PREPARE agregar_cat FROM @sql_cat;
EXECUTE agregar_cat;
DEALLOCATE PREPARE agregar_cat;

-- ---------------------------------------------------------
-- COMPRA: mercadería que llegó de un proveedor a una tienda
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS COMPRA (
  ID_COMPRA INT NOT NULL,
  ID_PROVEEDOR INT NOT NULL,
  ID_TIENDA INT NOT NULL,
  ID_EMPLEADO INT NOT NULL,                 -- quién registró la compra
  NUMERO_DOC VARCHAR(20) NULL,              -- factura o guía del proveedor
  FECHA_RECEPCION DATETIME NOT NULL,
  TOTAL DECIMAL(12,2) NOT NULL,
  CONSTRAINT PK_COMPRA PRIMARY KEY (ID_COMPRA),
  CONSTRAINT FK_COM_PROVEEDOR FOREIGN KEY (ID_PROVEEDOR) REFERENCES PROVEEDOR (ID_PROVEEDOR),
  CONSTRAINT FK_COM_TIENDA FOREIGN KEY (ID_TIENDA) REFERENCES TIENDA (ID_TIENDA),
  CONSTRAINT FK_COM_EMPLEADO FOREIGN KEY (ID_EMPLEADO) REFERENCES EMPLEADO (ID_EMPLEADO),
  CONSTRAINT CK_COM_1 CHECK (TOTAL >= 0)
) ENGINE = InnoDB;

-- Cada producto comprado crea un LOTE (y su cantidad entra al inventario)
CREATE TABLE IF NOT EXISTS DETALLE_COMPRA (
  ID_COMPRA INT NOT NULL,
  ID_PRODUCTO INT NOT NULL,
  ID_LOTE INT NOT NULL,
  CANTIDAD INT NOT NULL,
  COSTO_UNITARIO DECIMAL(10,2) NOT NULL,
  IMPORTE DECIMAL(12,2) NOT NULL,
  CONSTRAINT PK_DETALLE_COMPRA PRIMARY KEY (ID_COMPRA, ID_PRODUCTO),
  CONSTRAINT FK_DCO_COMPRA FOREIGN KEY (ID_COMPRA) REFERENCES COMPRA (ID_COMPRA),
  CONSTRAINT FK_DCO_PRODUCTO FOREIGN KEY (ID_PRODUCTO) REFERENCES PRODUCTO (ID_PRODUCTO),
  CONSTRAINT FK_DCO_LOTE FOREIGN KEY (ID_LOTE) REFERENCES LOTE (ID_LOTE),
  CONSTRAINT CK_DCO_1 CHECK (CANTIDAD > 0),
  CONSTRAINT CK_DCO_2 CHECK (COSTO_UNITARIO >= 0),
  CONSTRAINT CK_DCO_3 CHECK (IMPORTE = ROUND(CANTIDAD * COSTO_UNITARIO, 2))
) ENGINE = InnoDB;

-- ---------------------------------------------------------
-- HISTORIAL: quién hizo qué y cuándo
-- (agregó un producto, cambió un precio, anuló una venta, registró una merma...)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS HISTORIAL (
  ID_HISTORIAL INT NOT NULL,
  ID_EMPLEADO INT NOT NULL,
  FECHA_HORA DATETIME NOT NULL,
  ACCION VARCHAR(30) NOT NULL,
  DETALLE VARCHAR(255) NOT NULL,
  CONSTRAINT PK_HISTORIAL PRIMARY KEY (ID_HISTORIAL),
  CONSTRAINT FK_HIS_EMPLEADO FOREIGN KEY (ID_EMPLEADO) REFERENCES EMPLEADO (ID_EMPLEADO)
) ENGINE = InnoDB;

-- ---------------------------------------------------------
-- Datos de prueba
-- ---------------------------------------------------------
-- Si ya estaban (por ejemplo, con las tildes mal guardadas), se corrigen sus datos
INSERT INTO PROVEEDOR (ID_PROVEEDOR, RUC, RAZON_SOCIAL, RUBRO, CATEGORIA_SKU, CONTACTO, TELEFONO, CORREO, ESTADO, FECHA_REGISTRO) VALUES
  (1, '20601111111', 'Distribuidora Lácteos del Sur S.A.C.', 'Leche, yogurt, mantequilla y otros lácteos', 'LAC', 'Marco Linares', '054-231100', 'ventas@lacteosdelsur.pe', 'A', '2026-01-10 09:00:00'),
  (2, '20602222222', 'Abarrotes Mayoristas Arequipa E.I.R.L.', 'Arroz, azúcar, aceite, menestras, fideos, conservas y sal', 'ABA', 'Rocío Zegarra', '054-244500', NULL, 'A', '2026-01-10 09:30:00'),
  (3, '20603333333', 'Bebidas y Refrescos Characato S.A.C.', 'Gaseosas, agua y refrescos', 'BEB', 'Iván Chávez', '959111222', NULL, 'A', '2026-02-02 10:00:00'),
  (4, '20604444444', 'Limpieza Total Perú S.A.C.', 'Detergentes, lejía, papel higiénico y limpieza', 'LIM', NULL, NULL, 'pedidos@limpiezatotal.pe', 'A', '2026-03-15 11:00:00')
ON DUPLICATE KEY UPDATE RAZON_SOCIAL = VALUES(RAZON_SOCIAL), RUBRO = VALUES(RUBRO), CATEGORIA_SKU = VALUES(CATEGORIA_SKU), CONTACTO = VALUES(CONTACTO);

-- Una compra de ejemplo: los lotes 1 y 2 de leche y yogurt
INSERT IGNORE INTO COMPRA (ID_COMPRA, ID_PROVEEDOR, ID_TIENDA, ID_EMPLEADO, NUMERO_DOC, FECHA_RECEPCION, TOTAL) VALUES
  (1, 1, 1, 1, 'F001-000245', '2026-10-15 09:00:00', 534.00);
INSERT IGNORE INTO DETALLE_COMPRA (ID_COMPRA, ID_PRODUCTO, ID_LOTE, CANTIDAD, COSTO_UNITARIO, IMPORTE) VALUES
  (1, 2, 1, 120, 3.10, 372.00),
  (1, 3, 2, 30, 5.40, 162.00);

INSERT INTO HISTORIAL (ID_HISTORIAL, ID_EMPLEADO, FECHA_HORA, ACCION, DETALLE) VALUES
  (1, 1, '2026-10-15 09:00:00', 'REGISTRAR_COMPRA', 'Compra F001-000245 a Distribuidora Lácteos del Sur S.A.C. · 2 productos · S/ 534.00'),
  (2, 9, '2026-10-19 08:40:00', 'REGISTRAR_MERMA', 'Merma: 4 × Yogurt Gloria fresa 1 L (vencimiento) · S/ 21.60'),
  (3, 2, '2026-10-19 09:05:00', 'AUTORIZAR_MERMA', 'Aprobó la merma de Yogurt Gloria fresa 1 L'),
  (4, 3, '2026-10-20 14:00:00', 'ANULAR_VENTA', 'Anuló B002-3 (S/ 18.00)'),
  (5, 2, '2026-10-20 15:05:00', 'CERRAR_CAJA', 'Cerró la sesión 1 · sistema S/ 254.70 · contado S/ 254.70 · diferencia S/ 0.00')
ON DUPLICATE KEY UPDATE DETALLE = VALUES(DETALLE);

-- Comprobación
SELECT 'PROVEEDOR' AS TABLA, COUNT(*) AS FILAS FROM PROVEEDOR
UNION ALL SELECT 'COMPRA', COUNT(*) FROM COMPRA
UNION ALL SELECT 'DETALLE_COMPRA', COUNT(*) FROM DETALLE_COMPRA
UNION ALL SELECT 'HISTORIAL', COUNT(*) FROM HISTORIAL;
