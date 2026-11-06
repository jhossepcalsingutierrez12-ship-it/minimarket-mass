-- Minimarket Mass Arequipa · ventas de ejemplo en las 3 tiendas (2 al 5 de noviembre de 2026) · MySQL 8
-- Sirve para que Reportes, Cierres de caja e Historial muestren movimiento en Cayma, Cerro Colorado y Yanahuara.
-- Correr después de admin.sql y productos_nuevos.sql. Se puede volver a correr: si ya se cargó, no hace nada.
--   mysql -u root -P 3306 --default-character-set=utf8mb4 minimarket_mass -e "source database/ejemplos_tiendas.sql"
-- Todo pasa por las mismas reglas del sistema: la venta se hace con la caja ABIERTA (trigger R7),
-- el stock baja solo (R5), la venta anulada devuelve su stock (R4) y la merma descuenta stock.
-- Archivo generado por un script: ventas, cierres e historial cuadran entre sí.

SET NAMES utf8mb4;
USE minimarket_mass;

DROP PROCEDURE IF EXISTS ej_sesion;
DROP PROCEDURE IF EXISTS ej_venta;
DROP PROCEDURE IF EXISTS ej_detalle;
DROP PROCEDURE IF EXISTS ej_cerrar;
DROP PROCEDURE IF EXISTS ej_historial;
DROP PROCEDURE IF EXISTS cargar_ejemplos;

DELIMITER $$

-- Abre una sesión de caja (queda en @sesion)
CREATE PROCEDURE ej_sesion(IN p_caja INT, IN p_cajero INT, IN p_turno INT, IN p_sup INT, IN p_apertura DATETIME, IN p_monto DECIMAL(10,2))
BEGIN
  SET @sesion := (SELECT COALESCE(MAX(ID_SESION), 0) + 1 FROM SESION_CAJA);
  INSERT INTO SESION_CAJA (ID_SESION, ID_CAJA, ID_CAJERO, ID_TURNO, ID_SUPERVISOR, FECHA_APERTURA, MONTO_APERTURA, ESTADO)
  VALUES (@sesion, p_caja, p_cajero, p_turno, p_sup, p_apertura, p_monto, 'ABIERTA');
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
  INSERT INTO VENTA (ID_VENTA, ID_SESION, ID_CLIENTE, TIPO_COMPROBANTE, SERIE, NUMERO, FECHA_HORA, SUBTOTAL, IGV, TOTAL, ESTADO)
  VALUES (@venta, @sesion, p_cliente, p_tipo, v_serie,
          (SELECT n FROM (SELECT COALESCE(MAX(NUMERO), 0) + 1 AS n FROM VENTA WHERE SERIE = v_serie) x),
          p_fecha, p_total - v_igv, v_igv, p_total, 'EMITIDA');
  INSERT INTO PAGO (ID_PAGO, ID_VENTA, ID_METODO_PAGO, MONTO, NRO_OPERACION, MONTO_RECIBIDO, VUELTO)
  VALUES ((SELECT n FROM (SELECT COALESCE(MAX(ID_PAGO), 0) + 1 AS n FROM PAGO) x), @venta, p_metodo, p_total,
          IF(p_metodo = 1, NULL, CONCAT('OP', LPAD(@venta, 6, '0'))), p_recibido, IF(p_recibido IS NULL, NULL, p_recibido - p_total));
END$$

-- Un producto de la venta (el trigger baja el stock)
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

CREATE PROCEDURE ej_historial(IN p_emp INT, IN p_fecha DATETIME, IN p_accion VARCHAR(30), IN p_detalle VARCHAR(255))
BEGIN
  INSERT INTO HISTORIAL (ID_HISTORIAL, ID_EMPLEADO, FECHA_HORA, ACCION, DETALLE)
  VALUES ((SELECT n FROM (SELECT COALESCE(MAX(ID_HISTORIAL), 0) + 1 AS n FROM HISTORIAL) x), p_emp, p_fecha, p_accion, p_detalle);
END$$

CREATE PROCEDURE cargar_ejemplos()
BEGIN
  -- Si ya se cargaron (existe la primera venta de ejemplo), no se repite
  IF (SELECT COUNT(*) FROM VENTA WHERE FECHA_HORA = '2026-11-02 08:20:00') = 0 THEN

    -- Mass Yanahuara · Caja N° 1 · Kevin Turpo · turno Mañana
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

    -- Mass Cerro Colorado · Caja N° 1 · Milagros Pari · turno Mañana
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

    -- Mass Yanahuara · Caja N° 2 · Kevin Turpo · turno Tarde
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

    -- Mass Cerro Colorado · Caja N° 2 · Milagros Pari · turno Mañana
    CALL ej_sesion(4, 7, 1, 3, '2026-11-05 07:04:00', 150.00);
    CALL ej_venta('BOLETA', NULL, '2026-11-05 08:50:00', 22.30, 1, 30.00);
    CALL ej_detalle('ABA-0003', 1, 11.90);
    CALL ej_detalle('ABA-0004', 2, 5.20);
    CALL ej_venta('BOLETA', NULL, '2026-11-05 10:15:00', 16.80, 3, NULL);
    CALL ej_detalle('LAC-0001', 4, 4.20);
    CALL ej_cerrar('2026-11-05 15:06:00', 1.00, 3);

    -- Mass Cayma · Caja N° 2 · Jorge Ccama · turno Mañana
    CALL ej_sesion(2, 6, 1, 2, '2026-11-05 07:00:00', 200.00);
    CALL ej_venta('BOLETA', NULL, '2026-11-05 07:40:00', 28.30, 1, 50.00);
    CALL ej_detalle('ABA-0001', 1, 19.90);
    CALL ej_detalle('LAC-0001', 2, 4.20);
    CALL ej_venta('FACTURA', 3, '2026-11-05 09:30:00', 83.50, 2, NULL);
    CALL ej_detalle('ABA-0001', 3, 19.90);
    CALL ej_detalle('ABA-0003', 2, 11.90);
    CALL ej_cerrar('2026-11-05 15:02:00', 0.00, 2);

    -- Merma en Mass Yanahuara (el trigger descuenta el stock)
    INSERT INTO MERMA (ID_MERMA, ID_PRODUCTO, ID_LOTE, ID_ZONA, ID_REGISTRA, ID_AUTORIZA, ID_INCIDENTE, MOTIVO, FECHA, CANTIDAD, COSTO_UNITARIO, VALOR_TOTAL, ESTADO, OBSERVACION)
    VALUES ((SELECT n FROM (SELECT COALESCE(MAX(ID_MERMA), 0) + 1 AS n FROM MERMA) x), (SELECT ID_PRODUCTO FROM PRODUCTO WHERE CODIGO_SKU = 'LAC-0001'), NULL,
            10, 8, 4, NULL, 'DETERIORO', '2026-11-04', 2, 3.10, 6.20, 'APROBADA', 'Latas abolladas al descargar');

    -- Historial: lo que el sistema anota cuando alguien hace estas acciones
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
END$$

DELIMITER ;

CALL cargar_ejemplos();

DROP PROCEDURE ej_sesion;
DROP PROCEDURE ej_venta;
DROP PROCEDURE ej_detalle;
DROP PROCEDURE ej_cerrar;
DROP PROCEDURE ej_historial;
DROP PROCEDURE cargar_ejemplos;

-- Comprobación: ventas por tienda del 2 al 5 de noviembre
SELECT t.NOMBRE AS TIENDA, COUNT(*) AS VENTAS, SUM(v.TOTAL) AS TOTAL
FROM VENTA v JOIN SESION_CAJA s ON s.ID_SESION = v.ID_SESION JOIN CAJA c ON c.ID_CAJA = s.ID_CAJA JOIN TIENDA t ON t.ID_TIENDA = c.ID_TIENDA
WHERE v.ESTADO = 'EMITIDA' AND v.FECHA_HORA >= '2026-11-02' GROUP BY t.ID_TIENDA, t.NOMBRE ORDER BY t.ID_TIENDA;
