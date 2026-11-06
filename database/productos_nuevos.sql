-- Minimarket Mass Arequipa · productos nuevos, precio del arroz y ejemplos · MySQL 8 (Laragon)
-- Correr después de admin.sql. Se puede volver a correr: no repite nada.
--   mysql -u root -P 3306 --default-character-set=utf8mb4 minimarket_mass -e "source database/productos_nuevos.sql"
--
-- 1. Marcas y subcategorías de los 9 productos nuevos (para elegirlos en «Agregar producto»)
-- 2. Baja el precio del arroz Costeño 5 kg a S/ 19.90 y lo anota en el HISTORIAL
-- 3. Una compra de ejemplo a Abarrotes Mayoristas (crea lotes, sube el stock y queda en el historial)

SET NAMES utf8mb4;
USE minimarket_mass;

-- ---------------------------------------------------------
-- 1. Marcas y subcategorías nuevas
-- ---------------------------------------------------------
INSERT IGNORE INTO MARCA (ID_MARCA, NOMBRE, ES_PROPIA) VALUES
  (9, 'Don Vittorio', 'N'),
  (10, 'Florida', 'N'),
  (11, 'Emsal', 'N'),
  (12, 'Coca-Cola', 'N'),
  (13, 'Cielo', 'N'),
  (14, 'Laive', 'N'),
  (15, 'Clorox', 'N'),
  (16, 'Elite', 'N');

INSERT IGNORE INTO CATEGORIA (ID_CATEGORIA, ID_CATEGORIA_PADRE, NOMBRE) VALUES
  (13, 1, 'Fideos y pastas'),
  (14, 1, 'Conservas'),
  (15, 1, 'Sal y condimentos'),
  (16, 2, 'Mantequillas'),
  (17, 4, 'Lejías y desinfectantes'),
  (18, 4, 'Papel higiénico');

-- ---------------------------------------------------------
-- 2. Precio del arroz: de S/ 24.90 a S/ 19.90
-- ---------------------------------------------------------
SET @antes := (SELECT PRECIO_VENTA FROM PRODUCTO WHERE CODIGO_SKU = 'ABA-0001');
SET @cambia := (@antes IS NOT NULL AND @antes <> 19.90);
UPDATE PRODUCTO SET PRECIO_VENTA = 19.90 WHERE CODIGO_SKU = 'ABA-0001';
SET @h := (SELECT COALESCE(MAX(ID_HISTORIAL), 0) + 1 FROM HISTORIAL);
INSERT INTO HISTORIAL (ID_HISTORIAL, ID_EMPLEADO, FECHA_HORA, ACCION, DETALLE)
SELECT @h, 1, NOW(), 'CAMBIAR_PRECIO', CONCAT('ABA-0001 · Arroz Costeño extra 5 kg: S/ ', FORMAT(@antes, 2), ' → S/ 19.90 (estaba muy caro)')
FROM DUAL WHERE @cambia = 1;

-- ---------------------------------------------------------
-- 3. Compra de ejemplo: azúcar y lentejas a Abarrotes Mayoristas Arequipa (tienda Cerro Colorado)
-- ---------------------------------------------------------
SET @nueva := (SELECT COUNT(*) = 0 FROM COMPRA WHERE NUMERO_DOC = 'F003-001120');
SET @c := (SELECT COALESCE(MAX(ID_COMPRA), 0) + 1 FROM COMPRA);
SET @l := (SELECT COALESCE(MAX(ID_LOTE), 0) + 1 FROM LOTE);
SET @azucar := (SELECT ID_PRODUCTO FROM PRODUCTO WHERE CODIGO_SKU = 'ABA-0002');
SET @lentejas := (SELECT ID_PRODUCTO FROM PRODUCTO WHERE CODIGO_SKU = 'ABA-0004');

INSERT INTO COMPRA (ID_COMPRA, ID_PROVEEDOR, ID_TIENDA, ID_EMPLEADO, NUMERO_DOC, FECHA_RECEPCION, TOTAL)
SELECT @c, 2, 2, 1, 'F003-001120', '2026-11-04 10:30:00', 153.60 FROM DUAL WHERE @nueva = 1;

INSERT INTO LOTE (ID_LOTE, ID_PRODUCTO, CODIGO_LOTE, FECHA_VENCIMIENTO, CANTIDAD_RECIBIDA, COSTO_UNITARIO, FECHA_RECEPCION)
SELECT @l, @azucar, 'ABA-0002-2611A', NULL, 24, 3.40, '2026-11-04 10:30:00' FROM DUAL WHERE @nueva = 1;
INSERT INTO LOTE (ID_LOTE, ID_PRODUCTO, CODIGO_LOTE, FECHA_VENCIMIENTO, CANTIDAD_RECIBIDA, COSTO_UNITARIO, FECHA_RECEPCION)
SELECT @l + 1, @lentejas, 'ABA-0004-2611A', '2027-06-30', 20, 3.60, '2026-11-04 10:30:00' FROM DUAL WHERE @nueva = 1;

INSERT INTO DETALLE_COMPRA (ID_COMPRA, ID_PRODUCTO, ID_LOTE, CANTIDAD, COSTO_UNITARIO, IMPORTE)
SELECT @c, @azucar, @l, 24, 3.40, 81.60 FROM DUAL WHERE @nueva = 1;
INSERT INTO DETALLE_COMPRA (ID_COMPRA, ID_PRODUCTO, ID_LOTE, CANTIDAD, COSTO_UNITARIO, IMPORTE)
SELECT @c, @lentejas, @l + 1, 20, 3.60, 72.00 FROM DUAL WHERE @nueva = 1;

-- La mercadería entra al inventario
UPDATE INVENTARIO SET STOCK_ACTUAL = STOCK_ACTUAL + 24 WHERE ID_PRODUCTO = @azucar AND @nueva = 1;
UPDATE INVENTARIO SET STOCK_ACTUAL = STOCK_ACTUAL + 20 WHERE ID_PRODUCTO = @lentejas AND @nueva = 1;

SET @h := (SELECT COALESCE(MAX(ID_HISTORIAL), 0) + 1 FROM HISTORIAL);
INSERT INTO HISTORIAL (ID_HISTORIAL, ID_EMPLEADO, FECHA_HORA, ACCION, DETALLE)
SELECT @h, 1, '2026-11-04 10:30:00', 'REGISTRAR_COMPRA', 'Compra F003-001120 a Abarrotes Mayoristas Arequipa E.I.R.L. · 2 productos · S/ 153.60'
FROM DUAL WHERE @nueva = 1;

-- Comprobación
SELECT CODIGO_SKU, NOMBRE, PRECIO_VENTA FROM PRODUCTO WHERE CODIGO_SKU = 'ABA-0001';
SELECT ID_HISTORIAL, FECHA_HORA, ACCION, DETALLE FROM HISTORIAL ORDER BY ID_HISTORIAL DESC LIMIT 3;
