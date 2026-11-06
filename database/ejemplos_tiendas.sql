-- Minimarket Mass Arequipa · datos de ejemplo en las 3 tiendas · MySQL 8 (Laragon)
-- Para que Reportes, Caja (dinero en cada caja) e Historial muestren movimiento en Cayma, Cerro Colorado y Yanahuara:
--   · 22/10: reposición de mercadería (lotes recibidos)
--   · 23/10 al 05/11: ventas todos los días en las 3 tiendas, una anulación, una merma y cierres con faltante y sobrante
--   · Al final, TODAS las cajas quedan cerradas: cada cajero abre la suya al entrar
-- Correr después de admin.sql y productos_nuevos.sql. Se puede volver a correr: lo que ya se cargó no se repite.
--   mysql -u root -P 3306 --default-character-set=utf8mb4 minimarket_mass -e "source database/ejemplos_tiendas.sql"
-- Todo pasa por las reglas del sistema: se vende con la caja ABIERTA (trigger R7), el stock baja solo (R5),
-- la venta anulada devuelve su stock (R4) y la merma descuenta stock. Si algo falla, no se guarda nada.
-- Archivo generado por un script: ventas, cierres e historial cuadran entre sí.

SET NAMES utf8mb4;
USE minimarket_mass;

DROP PROCEDURE IF EXISTS ej_sesion;
DROP PROCEDURE IF EXISTS ej_venta;
DROP PROCEDURE IF EXISTS ej_detalle;
DROP PROCEDURE IF EXISTS ej_cerrar;
DROP PROCEDURE IF EXISTS ej_compra;
DROP PROCEDURE IF EXISTS ej_compra_detalle;
DROP PROCEDURE IF EXISTS ej_lote;
DROP PROCEDURE IF EXISTS ej_historial;
DROP PROCEDURE IF EXISTS cargar_ejemplos;

DELIMITER $$

-- Abre una sesión de caja (queda en @sesion)
CREATE PROCEDURE ej_sesion(IN p_caja INT, IN p_cajero INT, IN p_turno INT, IN p_sup INT, IN p_apertura DATETIME, IN p_monto DECIMAL(10,2))
BEGIN
  SET @sesion := (SELECT COALESCE(MAX(ID_SESION), 0) + 1 FROM SESION_CAJA);
  INSERT INTO SESION_CAJA (ID_SESION, ID_CAJA, ID_CAJERO, ID_TURNO, ID_SUPERVISOR, FECHA_APERTURA, MONTO_APERTURA, ESTADO)
  VALUES (@sesion, p_caja, p_cajero, p_turno,
          COALESCE(p_sup, (SELECT ID_SUPERVISOR FROM EMPLEADO WHERE ID_EMPLEADO = p_cajero)), p_apertura, p_monto, 'ABIERTA');
END$$

-- Registra la venta y su pago (queda en @venta). La serie es B00<tienda> o F00<tienda>, como en el sistema.
CREATE PROCEDURE ej_venta(IN p_tipo VARCHAR(7), IN p_cliente INT, IN p_fecha DATETIME, IN p_total DECIMAL(12,2),
                          IN p_metodo INT, IN p_recibido DECIMAL(12,2))
BEGIN
  DECLARE v_serie VARCHAR(4);
  DECLARE v_igv DECIMAL(12,2);
  SET v_serie := CONCAT(IF(p_tipo = 'BOLETA', 'B', 'F'), '00',
                        (SELECT c.ID_TIENDA FROM SESION_CAJA s JOIN CAJA c ON c.ID_CAJA = s.ID_CAJA WHERE s.ID_SESION = @sesion));
  SET v_igv := ROUND(p_total * 18 / 118, 2);
  SET @venta := (SELECT COALESCE(MAX(ID_VENTA), 0) + 1 FROM VENTA);
  SET @numero := (SELECT COALESCE(MAX(NUMERO), 0) + 1 FROM VENTA WHERE SERIE = v_serie);
  INSERT INTO VENTA (ID_VENTA, ID_SESION, ID_CLIENTE, TIPO_COMPROBANTE, SERIE, NUMERO, FECHA_HORA, SUBTOTAL, IGV, TOTAL, ESTADO)
  VALUES (@venta, @sesion, p_cliente, p_tipo, v_serie, @numero, p_fecha, p_total - v_igv, v_igv, p_total, 'EMITIDA');
  SET @pago := (SELECT COALESCE(MAX(ID_PAGO), 0) + 1 FROM PAGO);
  INSERT INTO PAGO (ID_PAGO, ID_VENTA, ID_METODO_PAGO, MONTO, NRO_OPERACION, MONTO_RECIBIDO, VUELTO)
  VALUES (@pago, @venta, p_metodo, p_total, IF(p_metodo = 1, NULL, CONCAT('OP', LPAD(@venta, 6, '0'))),
          p_recibido, IF(p_recibido IS NULL, NULL, p_recibido - p_total));
END$$

-- Un producto de la venta (el trigger TRG_DETALLE_DESCUENTA_STOCK baja el stock)
CREATE PROCEDURE ej_detalle(IN p_sku VARCHAR(20), IN p_cant DECIMAL(10,3), IN p_precio DECIMAL(10,2))
BEGIN
  INSERT INTO DETALLE_VENTA (ID_VENTA, ID_PRODUCTO, CANTIDAD, PRECIO_UNITARIO, DESCUENTO, IMPORTE)
  VALUES (@venta, (SELECT ID_PRODUCTO FROM PRODUCTO WHERE CODIGO_SKU = p_sku), p_cant, p_precio, 0, ROUND(p_cant * p_precio, 2));
END$$

-- Cierre: efectivo del sistema = apertura + pagos en efectivo de ventas emitidas; contado = sistema + diferencia
CREATE PROCEDURE ej_cerrar(IN p_cierre DATETIME, IN p_dif DECIMAL(10,2), IN p_sup INT)
BEGIN
  DECLARE v_sis DECIMAL(10,2);
  SET v_sis := (SELECT s.MONTO_APERTURA + COALESCE((SELECT SUM(p.MONTO) FROM PAGO p JOIN VENTA v ON v.ID_VENTA = p.ID_VENTA
                 WHERE v.ID_SESION = s.ID_SESION AND v.ESTADO = 'EMITIDA' AND p.ID_METODO_PAGO = 1), 0)
                FROM SESION_CAJA s WHERE s.ID_SESION = @sesion);
  UPDATE SESION_CAJA SET FECHA_CIERRE = p_cierre, EFECTIVO_SISTEMA = v_sis, EFECTIVO_CONTADO = v_sis + p_dif,
         DIFERENCIA = p_dif, ESTADO = 'CERRADA', ID_SUPERVISOR = p_sup
  WHERE ID_SESION = @sesion;
END$$

-- Lote recibido: entra al inventario (suma el stock)
CREATE PROCEDURE ej_lote(IN p_sku VARCHAR(20), IN p_cant INT, IN p_costo DECIMAL(10,2), IN p_vence DATE, IN p_lote VARCHAR(20), IN p_fecha DATETIME)
BEGIN
  DECLARE v_prod INT;
  SET v_prod := (SELECT ID_PRODUCTO FROM PRODUCTO WHERE CODIGO_SKU = p_sku);
  SET @lote := (SELECT COALESCE(MAX(ID_LOTE), 0) + 1 FROM LOTE);
  INSERT INTO LOTE (ID_LOTE, ID_PRODUCTO, CODIGO_LOTE, FECHA_VENCIMIENTO, CANTIDAD_RECIBIDA, COSTO_UNITARIO, FECHA_RECEPCION)
  VALUES (@lote, v_prod, p_lote, p_vence, p_cant, p_costo, p_fecha);
  UPDATE INVENTARIO SET STOCK_ACTUAL = STOCK_ACTUAL + p_cant WHERE ID_PRODUCTO = v_prod;
END$$

CREATE PROCEDURE ej_historial(IN p_emp INT, IN p_fecha DATETIME, IN p_accion VARCHAR(30), IN p_detalle VARCHAR(255))
BEGIN
  SET @hist := (SELECT COALESCE(MAX(ID_HISTORIAL), 0) + 1 FROM HISTORIAL);
  INSERT INTO HISTORIAL (ID_HISTORIAL, ID_EMPLEADO, FECHA_HORA, ACCION, DETALLE) VALUES (@hist, p_emp, p_fecha, p_accion, p_detalle);
END$$

CREATE PROCEDURE cargar_ejemplos()
BEGIN
  -- Si algo falla, no queda nada a medias
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;

  -- B. Reposición del 22/10 y ventas diarias del 23/10 al 01/11 en las 3 tiendas
  IF (SELECT COUNT(*) FROM LOTE WHERE CODIGO_LOTE = 'ABA-0001-2610R') = 0 THEN
    CALL ej_lote('ABA-0001', 60, 14.50, NULL, 'ABA-0001-2610R', '2026-10-22 09:00:00');
    CALL ej_lote('ABA-0002', 48, 3.40, NULL, 'ABA-0002-2610R', '2026-10-22 09:00:00');
    CALL ej_lote('ABA-0003', 48, 8.90, '2027-09-30', 'ABA-0003-2610R', '2026-10-22 09:00:00');
    CALL ej_lote('ABA-0004', 40, 3.60, '2027-05-31', 'ABA-0004-2610R', '2026-10-22 09:00:00');
    CALL ej_lote('LAC-0001', 120, 3.10, '2027-04-30', 'LAC-0001-2610R', '2026-10-22 10:30:00');
    CALL ej_lote('BEB-0002', 96, 2.40, '2027-10-31', 'BEB-0002-2610R', '2026-10-22 11:15:00');
    CALL ej_lote('LIM-0001', 24, 19.00, NULL, 'LIM-0001-2610R', '2026-10-22 12:00:00');
    -- Mass Cayma · Caja N° 3 · Jorge Ccama
    CALL ej_sesion(6, 6, 2, 2, '2026-10-23 15:01:00', 200.00);
    CALL ej_venta('BOLETA', NULL, '2026-10-23 19:46:00', 27.60, 1, 50.00);
    CALL ej_detalle('ABA-0003', 2, 11.90);
    CALL ej_detalle('BEB-0002', 1, 3.80);
    CALL ej_venta('BOLETA', NULL, '2026-10-23 21:16:00', 56.60, 1, 100.00);
    CALL ej_detalle('LAC-0001', 4, 4.20);
    CALL ej_detalle('ABA-0001', 2, 19.90);
    CALL ej_venta('BOLETA', NULL, '2026-10-23 21:41:00', 11.40, 1, 11.40);
    CALL ej_detalle('BEB-0002', 3, 3.80);
    CALL ej_venta('BOLETA', NULL, '2026-10-23 21:56:00', 23.80, 1, 50.00);
    CALL ej_detalle('ABA-0003', 2, 11.90);
    CALL ej_cerrar('2026-10-23 23:03:00', 0.00, 2);
    -- Mass Cerro Colorado · Caja N° 2 · Milagros Pari
    CALL ej_sesion(4, 7, 1, 3, '2026-10-23 07:07:00', 150.00);
    CALL ej_venta('BOLETA', NULL, '2026-10-23 11:37:00', 9.00, 2, NULL);
    CALL ej_detalle('ABA-0002', 2, 4.50);
    CALL ej_venta('BOLETA', NULL, '2026-10-23 12:37:00', 41.30, 4, NULL);
    CALL ej_detalle('BEB-0002', 3, 3.80);
    CALL ej_detalle('LIM-0001', 1, 29.90);
    CALL ej_venta('BOLETA', NULL, '2026-10-23 13:47:00', 32.40, 4, NULL);
    CALL ej_detalle('ABA-0004', 3, 5.20);
    CALL ej_detalle('LAC-0001', 4, 4.20);
    CALL ej_cerrar('2026-10-23 15:09:00', 0.50, 3);
    -- Mass Yanahuara · Caja N° 2 · Kevin Turpo
    CALL ej_sesion(8, 8, 2, 4, '2026-10-23 15:04:00', 150.00);
    CALL ej_venta('BOLETA', NULL, '2026-10-23 20:29:00', 9.00, 1, 9.00);
    CALL ej_detalle('ABA-0002', 2, 4.50);
    CALL ej_venta('BOLETA', NULL, '2026-10-23 20:44:00', 20.40, 2, NULL);
    CALL ej_detalle('BEB-0002', 3, 3.80);
    CALL ej_detalle('ABA-0002', 2, 4.50);
    CALL ej_venta('BOLETA', NULL, '2026-10-23 22:14:00', 44.00, 2, NULL);
    CALL ej_detalle('ABA-0001', 2, 19.90);
    CALL ej_detalle('LAC-0001', 1, 4.20);
    CALL ej_cerrar('2026-10-23 23:06:00', 0.50, 4);
    -- Mass Cayma · Caja N° 2 · Jorge Ccama
    CALL ej_sesion(2, 6, 1, 2, '2026-10-24 07:04:00', 200.00);
    CALL ej_venta('BOLETA', NULL, '2026-10-24 08:04:00', 9.00, 1, 10.00);
    CALL ej_detalle('ABA-0002', 2, 4.50);
    CALL ej_venta('BOLETA', NULL, '2026-10-24 10:14:00', 11.90, 3, NULL);
    CALL ej_detalle('ABA-0003', 1, 11.90);
    CALL ej_venta('BOLETA', NULL, '2026-10-24 12:29:00', 28.20, 4, NULL);
    CALL ej_detalle('BEB-0002', 3, 3.80);
    CALL ej_detalle('LAC-0001', 4, 4.20);
    CALL ej_venta('BOLETA', NULL, '2026-10-24 14:19:00', 39.80, 1, 39.80);
    CALL ej_detalle('ABA-0001', 2, 19.90);
    CALL ej_cerrar('2026-10-24 15:06:00', 0.00, 2);
    -- Mass Cerro Colorado · Caja N° 1 · Milagros Pari
    CALL ej_sesion(3, 7, 2, 3, '2026-10-24 15:09:00', 150.00);
    CALL ej_venta('BOLETA', NULL, '2026-10-24 17:04:00', 29.90, 1, 50.00);
    CALL ej_detalle('LIM-0001', 1, 29.90);
    CALL ej_venta('BOLETA', NULL, '2026-10-24 17:34:00', 12.60, 4, NULL);
    CALL ej_detalle('LAC-0001', 3, 4.20);
    CALL ej_venta('BOLETA', NULL, '2026-10-24 20:59:00', 19.90, 1, 20.00);
    CALL ej_detalle('ABA-0001', 1, 19.90);
    CALL ej_cerrar('2026-10-24 23:11:00', 0.00, 3);
    -- Mass Yanahuara · Caja N° 1 · Kevin Turpo
    CALL ej_sesion(5, 8, 1, 4, '2026-10-24 07:09:00', 150.00);
    CALL ej_venta('BOLETA', NULL, '2026-10-24 07:49:00', 18.80, 4, NULL);
    CALL ej_detalle('LAC-0001', 2, 4.20);
    CALL ej_detalle('ABA-0004', 2, 5.20);
    CALL ej_venta('BOLETA', NULL, '2026-10-24 09:34:00', 52.40, 2, NULL);
    CALL ej_detalle('ABA-0001', 2, 19.90);
    CALL ej_detalle('LAC-0001', 3, 4.20);
    CALL ej_venta('BOLETA', NULL, '2026-10-24 11:44:00', 9.00, 2, NULL);
    CALL ej_detalle('ABA-0002', 2, 4.50);
    CALL ej_cerrar('2026-10-24 15:11:00', 0.00, 4);
    -- Mass Cayma · Caja N° 3 · Jorge Ccama
    CALL ej_sesion(6, 6, 2, 2, '2026-10-25 15:04:00', 200.00);
    CALL ej_venta('BOLETA', NULL, '2026-10-25 16:39:00', 19.90, 4, NULL);
    CALL ej_detalle('ABA-0001', 1, 19.90);
    CALL ej_venta('BOLETA', NULL, '2026-10-25 20:04:00', 20.10, 5, NULL);
    CALL ej_detalle('ABA-0004', 3, 5.20);
    CALL ej_detalle('ABA-0002', 1, 4.50);
    CALL ej_venta('BOLETA', NULL, '2026-10-25 20:29:00', 43.70, 2, NULL);
    CALL ej_detalle('ABA-0003', 2, 11.90);
    CALL ej_detalle('ABA-0001', 1, 19.90);
    CALL ej_venta('BOLETA', NULL, '2026-10-25 20:54:00', 4.20, 4, NULL);
    CALL ej_detalle('LAC-0001', 1, 4.20);
    CALL ej_cerrar('2026-10-25 23:06:00', 0.00, 2);
    -- Mass Cerro Colorado · Caja N° 2 · Milagros Pari
    CALL ej_sesion(4, 7, 1, 3, '2026-10-25 07:02:00', 150.00);
    CALL ej_venta('BOLETA', NULL, '2026-10-25 08:02:00', 4.20, 1, 4.20);
    CALL ej_detalle('LAC-0001', 1, 4.20);
    CALL ej_venta('BOLETA', NULL, '2026-10-25 10:27:00', 4.20, 1, 10.00);
    CALL ej_detalle('LAC-0001', 1, 4.20);
    CALL ej_venta('BOLETA', NULL, '2026-10-25 13:37:00', 10.40, 3, NULL);
    CALL ej_detalle('ABA-0004', 2, 5.20);
    CALL ej_cerrar('2026-10-25 15:04:00', 0.00, 3);
    -- Mass Yanahuara · Caja N° 2 · Kevin Turpo
    CALL ej_sesion(8, 8, 2, 4, '2026-10-25 15:06:00', 150.00);
    CALL ej_venta('BOLETA', NULL, '2026-10-25 19:56:00', 12.10, 4, NULL);
    CALL ej_detalle('BEB-0002', 2, 3.80);
    CALL ej_detalle('ABA-0002', 1, 4.50);
    CALL ej_venta('BOLETA', NULL, '2026-10-25 20:21:00', 3.80, 1, 10.00);
    CALL ej_detalle('BEB-0002', 1, 3.80);
    CALL ej_venta('BOLETA', NULL, '2026-10-25 22:36:00', 5.20, 4, NULL);
    CALL ej_detalle('ABA-0004', 1, 5.20);
    CALL ej_cerrar('2026-10-25 23:08:00', 0.00, 4);
    -- Mass Cayma · Caja N° 2 · Jorge Ccama
    CALL ej_sesion(2, 6, 1, 2, '2026-10-26 07:01:00', 200.00);
    CALL ej_venta('BOLETA', NULL, '2026-10-26 11:06:00', 4.50, 1, 10.00);
    CALL ej_detalle('ABA-0002', 1, 4.50);
    CALL ej_venta('BOLETA', NULL, '2026-10-26 13:41:00', 4.20, 3, NULL);
    CALL ej_detalle('LAC-0001', 1, 4.20);
    CALL ej_venta('FACTURA', 4, '2026-10-26 14:11:00', 4.50, 1, 10.00);
    CALL ej_detalle('ABA-0002', 1, 4.50);
    CALL ej_cerrar('2026-10-26 15:03:00', 0.50, 2);
    -- Mass Cerro Colorado · Caja N° 1 · Milagros Pari
    CALL ej_sesion(3, 7, 2, 3, '2026-10-26 15:02:00', 150.00);
    CALL ej_venta('BOLETA', NULL, '2026-10-26 15:42:00', 20.40, 1, 20.40);
    CALL ej_detalle('BEB-0002', 3, 3.80);
    CALL ej_detalle('ABA-0002', 2, 4.50);
    CALL ej_venta('BOLETA', NULL, '2026-10-26 22:17:00', 8.40, 5, NULL);
    CALL ej_detalle('LAC-0001', 2, 4.20);
    CALL ej_cerrar('2026-10-26 23:04:00', 0.00, 3);
    -- Mass Yanahuara · Caja N° 1 · Kevin Turpo
    CALL ej_sesion(5, 8, 1, 4, '2026-10-26 07:05:00', 150.00);
    CALL ej_venta('BOLETA', NULL, '2026-10-26 09:30:00', 23.80, 4, NULL);
    CALL ej_detalle('ABA-0003', 2, 11.90);
    CALL ej_venta('BOLETA', NULL, '2026-10-26 11:20:00', 16.80, 4, NULL);
    CALL ej_detalle('LAC-0001', 4, 4.20);
    CALL ej_cerrar('2026-10-26 15:07:00', 0.00, 4);
    -- Mass Cayma · Caja N° 3 · Jorge Ccama
    CALL ej_sesion(6, 6, 2, 2, '2026-10-27 15:08:00', 200.00);
    CALL ej_venta('BOLETA', NULL, '2026-10-27 16:18:00', 21.90, 4, NULL);
    CALL ej_detalle('ABA-0002', 3, 4.50);
    CALL ej_detalle('LAC-0001', 2, 4.20);
    CALL ej_venta('BOLETA', NULL, '2026-10-27 17:18:00', 8.40, 5, NULL);
    CALL ej_detalle('LAC-0001', 2, 4.20);
    CALL ej_venta('BOLETA', NULL, '2026-10-27 18:38:00', 9.00, 1, 9.00);
    CALL ej_detalle('ABA-0002', 2, 4.50);
    CALL ej_venta('BOLETA', NULL, '2026-10-27 19:23:00', 9.00, 1, 9.00);
    CALL ej_detalle('ABA-0002', 2, 4.50);
    CALL ej_cerrar('2026-10-27 23:10:00', 0.00, 2);
    -- Mass Cerro Colorado · Caja N° 2 · Milagros Pari
    CALL ej_sesion(4, 7, 1, 3, '2026-10-27 07:09:00', 150.00);
    CALL ej_venta('BOLETA', NULL, '2026-10-27 09:14:00', 4.50, 4, NULL);
    CALL ej_detalle('ABA-0002', 1, 4.50);
    CALL ej_venta('BOLETA', NULL, '2026-10-27 12:59:00', 23.80, 1, 50.00);
    CALL ej_detalle('ABA-0003', 2, 11.90);
    CALL ej_venta('BOLETA', NULL, '2026-10-27 13:09:00', 12.60, 2, NULL);
    CALL ej_detalle('LAC-0001', 3, 4.20);
    CALL ej_cerrar('2026-10-27 15:11:00', 0.00, 3);
    -- Mass Yanahuara · Caja N° 2 · Kevin Turpo
    CALL ej_sesion(8, 8, 2, 4, '2026-10-27 15:06:00', 150.00);
    CALL ej_venta('BOLETA', NULL, '2026-10-27 16:41:00', 12.60, 4, NULL);
    CALL ej_detalle('LAC-0001', 3, 4.20);
    CALL ej_venta('BOLETA', NULL, '2026-10-27 19:06:00', 12.60, 1, 20.00);
    CALL ej_detalle('LAC-0001', 3, 4.20);
    CALL ej_venta('BOLETA', NULL, '2026-10-27 20:26:00', 23.80, 1, 23.80);
    CALL ej_detalle('ABA-0003', 2, 11.90);
    CALL ej_cerrar('2026-10-27 23:08:00', 0.00, 4);
    -- Mass Cayma · Caja N° 2 · Jorge Ccama
    CALL ej_sesion(2, 6, 1, 2, '2026-10-28 07:05:00', 200.00);
    CALL ej_venta('BOLETA', NULL, '2026-10-28 09:00:00', 23.80, 1, 50.00);
    CALL ej_detalle('ABA-0003', 2, 11.90);
    CALL ej_venta('BOLETA', NULL, '2026-10-28 09:40:00', 9.00, 1, 10.00);
    CALL ej_detalle('ABA-0002', 2, 4.50);
    CALL ej_venta('BOLETA', NULL, '2026-10-28 11:25:00', 9.00, 1, 10.00);
    CALL ej_detalle('ABA-0002', 2, 4.50);
    CALL ej_venta('BOLETA', NULL, '2026-10-28 12:05:00', 48.20, 1, 48.20);
    CALL ej_detalle('LAC-0001', 2, 4.20);
    CALL ej_detalle('ABA-0001', 2, 19.90);
    CALL ej_cerrar('2026-10-28 15:07:00', 0.00, 2);
    -- Mass Cerro Colorado · Caja N° 1 · Milagros Pari
    CALL ej_sesion(3, 7, 2, 3, '2026-10-28 15:00:00', 150.00);
    CALL ej_venta('BOLETA', NULL, '2026-10-28 18:50:00', 9.00, 4, NULL);
    CALL ej_detalle('BEB-0002', 1, 3.80);
    CALL ej_detalle('ABA-0004', 1, 5.20);
    CALL ej_venta('BOLETA', NULL, '2026-10-28 21:30:00', 5.20, 4, NULL);
    CALL ej_detalle('ABA-0004', 1, 5.20);
    CALL ej_venta('BOLETA', NULL, '2026-10-28 22:10:00', 12.90, 1, 20.00);
    CALL ej_detalle('ABA-0002', 1, 4.50);
    CALL ej_detalle('LAC-0001', 2, 4.20);
    CALL ej_cerrar('2026-10-28 23:02:00', 0.00, 3);
    -- Mass Yanahuara · Caja N° 1 · Kevin Turpo
    CALL ej_sesion(5, 8, 1, 4, '2026-10-28 07:02:00', 150.00);
    CALL ej_venta('BOLETA', NULL, '2026-10-28 11:47:00', 16.80, 1, 20.00);
    CALL ej_detalle('LAC-0001', 4, 4.20);
    CALL ej_venta('BOLETA', NULL, '2026-10-28 12:52:00', 15.60, 1, 15.60);
    CALL ej_detalle('ABA-0004', 3, 5.20);
    CALL ej_venta('BOLETA', NULL, '2026-10-28 14:37:00', 22.00, 4, NULL);
    CALL ej_detalle('ABA-0004', 1, 5.20);
    CALL ej_detalle('LAC-0001', 4, 4.20);
    CALL ej_cerrar('2026-10-28 15:04:00', 0.00, 4);
    -- Mass Cayma · Caja N° 3 · Jorge Ccama
    CALL ej_sesion(6, 6, 2, 2, '2026-10-29 15:03:00', 200.00);
    CALL ej_venta('BOLETA', NULL, '2026-10-29 17:38:00', 13.50, 3, NULL);
    CALL ej_detalle('ABA-0002', 3, 4.50);
    CALL ej_venta('BOLETA', NULL, '2026-10-29 18:33:00', 39.40, 2, NULL);
    CALL ej_detalle('ABA-0004', 3, 5.20);
    CALL ej_detalle('ABA-0003', 2, 11.90);
    CALL ej_venta('BOLETA', NULL, '2026-10-29 19:38:00', 28.20, 1, 50.00);
    CALL ej_detalle('LAC-0001', 4, 4.20);
    CALL ej_detalle('BEB-0002', 3, 3.80);
    CALL ej_venta('BOLETA', NULL, '2026-10-29 21:33:00', 8.40, 2, NULL);
    CALL ej_detalle('LAC-0001', 2, 4.20);
    CALL ej_cerrar('2026-10-29 23:05:00', 0.00, 2);
    -- Mass Cerro Colorado · Caja N° 2 · Milagros Pari
    CALL ej_sesion(4, 7, 1, 3, '2026-10-29 07:07:00', 150.00);
    CALL ej_venta('BOLETA', NULL, '2026-10-29 11:37:00', 19.90, 4, NULL);
    CALL ej_detalle('ABA-0001', 1, 19.90);
    CALL ej_venta('BOLETA', NULL, '2026-10-29 13:07:00', 11.90, 1, 11.90);
    CALL ej_detalle('ABA-0003', 1, 11.90);
    CALL ej_venta('BOLETA', NULL, '2026-10-29 14:22:00', 38.30, 4, NULL);
    CALL ej_detalle('LIM-0001', 1, 29.90);
    CALL ej_detalle('LAC-0001', 2, 4.20);
    CALL ej_cerrar('2026-10-29 15:09:00', 0.00, 3);
    -- Mass Yanahuara · Caja N° 2 · Kevin Turpo
    CALL ej_sesion(8, 8, 2, 4, '2026-10-29 15:08:00', 150.00);
    CALL ej_venta('BOLETA', NULL, '2026-10-29 20:53:00', 23.80, 1, 23.80);
    CALL ej_detalle('ABA-0003', 2, 11.90);
    CALL ej_cerrar('2026-10-29 23:10:00', 0.00, 4);
    -- Mass Cayma · Caja N° 2 · Jorge Ccama
    CALL ej_sesion(2, 6, 1, 2, '2026-10-30 07:00:00', 200.00);
    CALL ej_venta('BOLETA', NULL, '2026-10-30 10:00:00', 4.20, 2, NULL);
    CALL ej_detalle('LAC-0001', 1, 4.20);
    CALL ej_venta('BOLETA', NULL, '2026-10-30 10:50:00', 16.80, 1, 16.80);
    CALL ej_detalle('LAC-0001', 4, 4.20);
    CALL ej_venta('BOLETA', NULL, '2026-10-30 13:35:00', 51.20, 3, NULL);
    CALL ej_detalle('ABA-0001', 2, 19.90);
    CALL ej_detalle('BEB-0002', 3, 3.80);
    CALL ej_cerrar('2026-10-30 15:02:00', -2.00, 2);
    -- Mass Cerro Colorado · Caja N° 1 · Milagros Pari
    CALL ej_sesion(3, 7, 2, 3, '2026-10-30 15:03:00', 150.00);
    CALL ej_venta('BOLETA', NULL, '2026-10-30 21:58:00', 16.80, 1, 16.80);
    CALL ej_detalle('LAC-0001', 4, 4.20);
    CALL ej_venta('FACTURA', 4, '2026-10-30 22:33:00', 45.50, 1, 45.50);
    CALL ej_detalle('LIM-0001', 1, 29.90);
    CALL ej_detalle('ABA-0004', 3, 5.20);
    CALL ej_cerrar('2026-10-30 23:05:00', -1.50, 3);
    -- Mass Yanahuara · Caja N° 1 · Kevin Turpo
    CALL ej_sesion(5, 8, 1, 4, '2026-10-30 07:07:00', 150.00);
    CALL ej_venta('BOLETA', NULL, '2026-10-30 13:22:00', 13.50, 3, NULL);
    CALL ej_detalle('ABA-0002', 3, 4.50);
    CALL ej_cerrar('2026-10-30 15:09:00', 0.00, 4);
    -- Mass Cayma · Caja N° 3 · Jorge Ccama
    CALL ej_sesion(6, 6, 2, 2, '2026-10-31 15:05:00', 200.00);
    CALL ej_venta('BOLETA', NULL, '2026-10-31 19:05:00', 9.70, 2, NULL);
    CALL ej_detalle('ABA-0004', 1, 5.20);
    CALL ej_detalle('ABA-0002', 1, 4.50);
    CALL ej_venta('BOLETA', NULL, '2026-10-31 20:20:00', 41.30, 1, 50.00);
    CALL ej_detalle('BEB-0002', 3, 3.80);
    CALL ej_detalle('LIM-0001', 1, 29.90);
    CALL ej_venta('BOLETA', NULL, '2026-10-31 21:00:00', 4.20, 4, NULL);
    CALL ej_detalle('LAC-0001', 1, 4.20);
    CALL ej_venta('BOLETA', NULL, '2026-10-31 21:10:00', 5.20, 1, 10.00);
    CALL ej_detalle('ABA-0004', 1, 5.20);
    CALL ej_cerrar('2026-10-31 23:07:00', -2.00, 2);
    -- Mass Cerro Colorado · Caja N° 2 · Milagros Pari
    CALL ej_sesion(4, 7, 1, 3, '2026-10-31 07:03:00', 150.00);
    CALL ej_venta('BOLETA', NULL, '2026-10-31 10:43:00', 8.40, 4, NULL);
    CALL ej_detalle('LAC-0001', 2, 4.20);
    CALL ej_venta('FACTURA', 4, '2026-10-31 11:48:00', 19.90, 1, 19.90);
    CALL ej_detalle('ABA-0001', 1, 19.90);
    CALL ej_cerrar('2026-10-31 15:05:00', 0.00, 3);
    -- Mass Yanahuara · Caja N° 2 · Kevin Turpo
    CALL ej_sesion(8, 8, 2, 4, '2026-10-31 15:08:00', 150.00);
    CALL ej_venta('BOLETA', NULL, '2026-10-31 15:43:00', 15.60, 1, 20.00);
    CALL ej_detalle('ABA-0004', 3, 5.20);
    CALL ej_venta('BOLETA', NULL, '2026-10-31 21:38:00', 4.20, 4, NULL);
    CALL ej_detalle('LAC-0001', 1, 4.20);
    CALL ej_cerrar('2026-10-31 23:10:00', 0.00, 4);
    -- Mass Cayma · Caja N° 3 · Jorge Ccama
    CALL ej_sesion(6, 6, 2, 2, '2026-11-01 15:00:00', 200.00);
    CALL ej_venta('BOLETA', NULL, '2026-11-01 17:05:00', 4.50, 1, 4.50);
    CALL ej_detalle('ABA-0002', 1, 4.50);
    CALL ej_venta('BOLETA', NULL, '2026-11-01 18:05:00', 5.20, 1, 5.20);
    CALL ej_detalle('ABA-0004', 1, 5.20);
    CALL ej_venta('BOLETA', NULL, '2026-11-01 20:55:00', 22.00, 1, 22.00);
    CALL ej_detalle('ABA-0004', 1, 5.20);
    CALL ej_detalle('LAC-0001', 4, 4.20);
    CALL ej_cerrar('2026-11-01 23:02:00', 0.00, 2);
    -- Mass Cerro Colorado · Caja N° 2 · Milagros Pari
    CALL ej_sesion(4, 7, 1, 3, '2026-11-01 07:00:00', 150.00);
    CALL ej_venta('BOLETA', NULL, '2026-11-01 08:05:00', 11.40, 2, NULL);
    CALL ej_detalle('BEB-0002', 3, 3.80);
    CALL ej_venta('BOLETA', NULL, '2026-11-01 10:05:00', 16.80, 2, NULL);
    CALL ej_detalle('LAC-0001', 4, 4.20);
    CALL ej_cerrar('2026-11-01 15:02:00', 0.00, 3);
    -- Mass Yanahuara · Caja N° 2 · Kevin Turpo
    CALL ej_sesion(8, 8, 2, 4, '2026-11-01 15:06:00', 150.00);
    CALL ej_venta('BOLETA', NULL, '2026-11-01 17:11:00', 23.80, 1, 23.80);
    CALL ej_detalle('ABA-0003', 2, 11.90);
    CALL ej_venta('BOLETA', NULL, '2026-11-01 17:16:00', 8.40, 5, NULL);
    CALL ej_detalle('LAC-0001', 2, 4.20);
    CALL ej_venta('BOLETA', NULL, '2026-11-01 22:01:00', 17.80, 2, NULL);
    CALL ej_detalle('LAC-0001', 3, 4.20);
    CALL ej_detalle('ABA-0004', 1, 5.20);
    CALL ej_cerrar('2026-11-01 23:08:00', 0.00, 4);
    CALL ej_historial(1, '2026-10-22 09:00:00', 'RECIBIR_LOTE', 'Lotes recibidos (factura F003-001098) · 4 productos · S/ 1604.40');
    CALL ej_historial(1, '2026-10-22 10:30:00', 'RECIBIR_LOTE', 'Lotes recibidos (factura F001-000301) · 1 producto · S/ 372.00');
    CALL ej_historial(1, '2026-10-22 11:15:00', 'RECIBIR_LOTE', 'Lotes recibidos (factura F002-004410) · 1 producto · S/ 230.40');
    CALL ej_historial(1, '2026-10-22 12:00:00', 'RECIBIR_LOTE', 'Lotes recibidos (factura F001-000077) · 1 producto · S/ 456.00');
    CALL ej_historial(3, '2026-10-23 15:09:00', 'CERRAR_CAJA', 'Cerró Mass Cerro Colorado · Caja N° 2 · sistema S/ 150.00 · contado S/ 150.50 · diferencia S/ 0.50 (sobrante)');
    CALL ej_historial(4, '2026-10-23 23:06:00', 'CERRAR_CAJA', 'Cerró Mass Yanahuara · Caja N° 2 · sistema S/ 159.00 · contado S/ 159.50 · diferencia S/ 0.50 (sobrante)');
    CALL ej_historial(2, '2026-10-26 15:03:00', 'CERRAR_CAJA', 'Cerró Mass Cayma · Caja N° 2 · sistema S/ 209.00 · contado S/ 209.50 · diferencia S/ 0.50 (sobrante)');
    CALL ej_historial(2, '2026-10-30 15:02:00', 'CERRAR_CAJA', 'Cerró Mass Cayma · Caja N° 2 · sistema S/ 216.80 · contado S/ 214.80 · diferencia S/ -2.00 (faltante)');
    CALL ej_historial(3, '2026-10-30 23:05:00', 'CERRAR_CAJA', 'Cerró Mass Cerro Colorado · Caja N° 1 · sistema S/ 212.30 · contado S/ 210.80 · diferencia S/ -1.50 (faltante)');
    CALL ej_historial(2, '2026-10-31 23:07:00', 'CERRAR_CAJA', 'Cerró Mass Cayma · Caja N° 3 · sistema S/ 246.50 · contado S/ 244.50 · diferencia S/ -2.00 (faltante)');
  END IF;

  -- A. 2 al 5 de noviembre: ventas, una anulación, una merma y cierres con faltante y sobrante
  IF (SELECT COUNT(*) FROM VENTA WHERE FECHA_HORA = '2026-11-02 08:20:00') = 0 THEN
    -- Mass Yanahuara · Caja N° 1 · Kevin Turpo
    CALL ej_sesion(5, 8, 1, 4, '2026-11-02 07:05:00', 150.00);
    CALL ej_venta('BOLETA', NULL, '2026-11-02 08:20:00', 21.60, 1, 30.00);
    CALL ej_detalle('LAC-0001', 3, 4.20);
    CALL ej_detalle('ABA-0002', 2, 4.50);
    CALL ej_venta('BOLETA', NULL, '2026-11-02 10:45:00', 31.80, 4, NULL);
    CALL ej_detalle('ABA-0001', 1, 19.90);
    CALL ej_detalle('ABA-0003', 1, 11.90);
    CALL ej_venta('BOLETA', NULL, '2026-11-02 12:30:00', 15.20, 1, 20.00);
    CALL ej_detalle('BEB-0002', 4, 3.80);
    CALL ej_cerrar('2026-11-02 15:05:00', 0.00, 4);
    -- Mass Cerro Colorado · Caja N° 1 · Milagros Pari
    CALL ej_sesion(3, 7, 1, 3, '2026-11-03 07:02:00', 150.00);
    CALL ej_venta('BOLETA', NULL, '2026-11-03 09:00:00', 25.20, 1, 50.00);
    CALL ej_detalle('LAC-0001', 6, 4.20);
    CALL ej_venta('BOLETA', NULL, '2026-11-03 11:20:00', 48.80, 4, NULL);
    CALL ej_detalle('ABA-0001', 2, 19.90);
    CALL ej_detalle('ABA-0002', 2, 4.50);
    CALL ej_venta('BOLETA', NULL, '2026-11-03 13:40:00', 7.60, 1, 10.00);
    CALL ej_detalle('BEB-0002', 2, 3.80);
    UPDATE VENTA SET ESTADO = 'ANULADA' WHERE ID_VENTA = @venta;   -- R4: se anula y el stock vuelve
    CALL ej_cerrar('2026-11-03 15:08:00', 0.00, 3);
    -- Mass Yanahuara · Caja N° 2 · Kevin Turpo
    CALL ej_sesion(8, 8, 2, 4, '2026-11-04 15:03:00', 150.00);
    CALL ej_venta('BOLETA', NULL, '2026-11-04 16:10:00', 24.00, 2, NULL);
    CALL ej_detalle('ABA-0004', 3, 5.20);
    CALL ej_detalle('LAC-0001', 2, 4.20);
    CALL ej_venta('FACTURA', 4, '2026-11-04 18:30:00', 50.80, 1, 60.00);
    CALL ej_detalle('ABA-0002', 6, 4.50);
    CALL ej_detalle('ABA-0003', 2, 11.90);
    CALL ej_venta('BOLETA', NULL, '2026-11-04 20:05:00', 29.90, 5, NULL);
    CALL ej_detalle('LIM-0001', 1, 29.90);
    CALL ej_cerrar('2026-11-04 23:04:00', -5.00, 4);
    -- Mass Cerro Colorado · Caja N° 2 · Milagros Pari
    CALL ej_sesion(4, 7, 1, 3, '2026-11-05 07:04:00', 150.00);
    CALL ej_venta('BOLETA', NULL, '2026-11-05 08:50:00', 22.30, 1, 30.00);
    CALL ej_detalle('ABA-0003', 1, 11.90);
    CALL ej_detalle('ABA-0004', 2, 5.20);
    CALL ej_venta('BOLETA', NULL, '2026-11-05 10:15:00', 16.80, 3, NULL);
    CALL ej_detalle('LAC-0001', 4, 4.20);
    CALL ej_cerrar('2026-11-05 15:06:00', 1.00, 3);
    -- Mass Cayma · Caja N° 2 · Jorge Ccama
    CALL ej_sesion(2, 6, 1, 2, '2026-11-05 07:00:00', 200.00);
    CALL ej_venta('BOLETA', NULL, '2026-11-05 07:40:00', 28.30, 1, 50.00);
    CALL ej_detalle('ABA-0001', 1, 19.90);
    CALL ej_detalle('LAC-0001', 2, 4.20);
    CALL ej_venta('FACTURA', 3, '2026-11-05 09:30:00', 83.50, 2, NULL);
    CALL ej_detalle('ABA-0001', 3, 19.90);
    CALL ej_detalle('ABA-0003', 2, 11.90);
    CALL ej_cerrar('2026-11-05 15:02:00', 0.00, 2);
    INSERT INTO MERMA (ID_MERMA, ID_PRODUCTO, ID_LOTE, ID_ZONA, ID_REGISTRA, ID_AUTORIZA, ID_INCIDENTE, MOTIVO, FECHA, CANTIDAD, COSTO_UNITARIO, VALOR_TOTAL, ESTADO, OBSERVACION)
    VALUES ((SELECT n FROM (SELECT COALESCE(MAX(ID_MERMA), 0) + 1 AS n FROM MERMA) x), (SELECT ID_PRODUCTO FROM PRODUCTO WHERE CODIGO_SKU = 'LAC-0001'), NULL,
            10, 8, 4, NULL, 'DETERIORO', '2026-11-04', 2, 3.10, 6.20, 'APROBADA', 'Latas abolladas al descargar');
    CALL ej_historial(8, '2026-11-02 07:05:00', 'ABRIR_CAJA', 'Abrió Mass Yanahuara · Caja N° 1 con S/ 150.00');
    CALL ej_historial(4, '2026-11-02 15:05:00', 'CERRAR_CAJA', 'Cerró Mass Yanahuara · Caja N° 1 · sistema S/ 186.80 · contado S/ 186.80 · diferencia S/ 0.00');
    CALL ej_historial(7, '2026-11-03 07:02:00', 'ABRIR_CAJA', 'Abrió Mass Cerro Colorado · Caja N° 1 con S/ 150.00');
    CALL ej_historial(3, '2026-11-03 13:55:00', 'ANULAR_VENTA', 'Anuló una boleta de Mass Cerro Colorado (S/ 7.60): el cliente devolvió el producto');
    CALL ej_historial(3, '2026-11-03 15:08:00', 'CERRAR_CAJA', 'Cerró Mass Cerro Colorado · Caja N° 1 · sistema S/ 175.20 · contado S/ 175.20 · diferencia S/ 0.00');
    CALL ej_historial(8, '2026-11-04 15:03:00', 'ABRIR_CAJA', 'Abrió Mass Yanahuara · Caja N° 2 con S/ 150.00');
    CALL ej_historial(8, '2026-11-04 15:30:00', 'REGISTRAR_MERMA', 'Merma: 2 × Leche Gloria azul 400 g (deterioro) · S/ 6.20 · Mass Yanahuara');
    CALL ej_historial(4, '2026-11-04 16:00:00', 'AUTORIZAR_MERMA', 'Aprobó la merma de Leche Gloria azul 400 g · Mass Yanahuara');
    CALL ej_historial(4, '2026-11-04 23:04:00', 'CERRAR_CAJA', 'Cerró Mass Yanahuara · Caja N° 2 · sistema S/ 200.80 · contado S/ 195.80 · diferencia S/ -5.00 (faltante)');
    CALL ej_historial(6, '2026-11-05 07:00:00', 'ABRIR_CAJA', 'Abrió Mass Cayma · Caja N° 2 con S/ 200.00');
    CALL ej_historial(7, '2026-11-05 07:04:00', 'ABRIR_CAJA', 'Abrió Mass Cerro Colorado · Caja N° 2 con S/ 150.00');
    CALL ej_historial(2, '2026-11-05 15:02:00', 'CERRAR_CAJA', 'Cerró Mass Cayma · Caja N° 2 · sistema S/ 228.30 · contado S/ 228.30 · diferencia S/ 0.00');
    CALL ej_historial(3, '2026-11-05 15:06:00', 'CERRAR_CAJA', 'Cerró Mass Cerro Colorado · Caja N° 2 · sistema S/ 172.30 · contado S/ 173.30 · diferencia S/ 1.00 (sobrante)');
  END IF;

  -- Todas las cajas quedan CERRADAS: al entrar, cada cajero elige tienda y caja y la abre él mismo
  UPDATE SESION_CAJA s
     SET EFECTIVO_SISTEMA = s.MONTO_APERTURA + COALESCE((SELECT SUM(p.MONTO) FROM PAGO p JOIN VENTA v ON v.ID_VENTA = p.ID_VENTA
                            WHERE v.ID_SESION = s.ID_SESION AND v.ESTADO = 'EMITIDA' AND p.ID_METODO_PAGO = 1), 0)
   WHERE s.ESTADO = 'ABIERTA';
  UPDATE SESION_CAJA
     SET EFECTIVO_CONTADO = EFECTIVO_SISTEMA, DIFERENCIA = 0, ESTADO = 'CERRADA',
         FECHA_CIERRE = GREATEST(NOW(), FECHA_APERTURA + INTERVAL 1 MINUTE)
   WHERE ESTADO = 'ABIERTA';

  COMMIT;
END$$

DELIMITER ;

CALL cargar_ejemplos();

DROP PROCEDURE ej_sesion;
DROP PROCEDURE ej_venta;
DROP PROCEDURE ej_detalle;
DROP PROCEDURE ej_cerrar;
DROP PROCEDURE ej_lote;
DROP PROCEDURE ej_historial;
DROP PROCEDURE cargar_ejemplos;

-- Comprobación: ventas por tienda desde el 23 de octubre y cajas abiertas ahora
SELECT t.NOMBRE AS TIENDA, COUNT(*) AS VENTAS, SUM(v.TOTAL) AS TOTAL
FROM VENTA v JOIN SESION_CAJA s ON s.ID_SESION = v.ID_SESION JOIN CAJA c ON c.ID_CAJA = s.ID_CAJA JOIN TIENDA t ON t.ID_TIENDA = c.ID_TIENDA
WHERE v.ESTADO = 'EMITIDA' AND v.FECHA_HORA >= '2026-10-23' GROUP BY t.ID_TIENDA, t.NOMBRE ORDER BY t.ID_TIENDA;
SELECT t.NOMBRE AS TIENDA, c.NUMERO_CAJA, s.FECHA_APERTURA, s.MONTO_APERTURA
FROM SESION_CAJA s JOIN CAJA c ON c.ID_CAJA = s.ID_CAJA JOIN TIENDA t ON t.ID_TIENDA = c.ID_TIENDA WHERE s.ESTADO = 'ABIERTA';
