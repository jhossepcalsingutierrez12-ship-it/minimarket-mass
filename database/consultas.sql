-- Minimarket Mass Arequipa · consultas de reporte · MySQL 8 (Laragon)
-- Ejecutar DESPUÉS de schema.sql y datos.sql.
-- Cada consulta responde una pregunta del negocio (ver el comentario de arriba).

SET NAMES utf8mb4;
USE minimarket_mass;

-- ---------------------------------------------------------
-- 1. Catálogo: ¿qué vendemos, de qué categoría y marca, y cuánto hay?
-- ---------------------------------------------------------
SELECT p.CODIGO_SKU,
       p.NOMBRE,
       COALESCE(cp.NOMBRE, c.NOMBRE) AS CATEGORIA,
       c.NOMBRE                      AS SUBCATEGORIA,
       m.NOMBRE                      AS MARCA,
       p.PRECIO_VENTA,
       i.STOCK_ACTUAL
  FROM PRODUCTO p
  JOIN CATEGORIA c       ON c.ID_CATEGORIA = p.ID_CATEGORIA
  LEFT JOIN CATEGORIA cp ON cp.ID_CATEGORIA = c.ID_CATEGORIA_PADRE
  JOIN MARCA m           ON m.ID_MARCA = p.ID_MARCA
  JOIN INVENTARIO i      ON i.ID_PRODUCTO = p.ID_PRODUCTO
 WHERE p.ESTADO = 'A'
 ORDER BY CATEGORIA, p.NOMBRE;

-- ---------------------------------------------------------
-- 2. Reposición: ¿qué productos están en o bajo su stock mínimo?
--    (es la misma regla que muestra "Reponer" / "Agotado" en la web)
-- ---------------------------------------------------------
SELECT p.CODIGO_SKU,
       p.NOMBRE,
       i.STOCK_ACTUAL,
       i.STOCK_MINIMO,
       i.STOCK_MAXIMO - i.STOCK_ACTUAL AS UNIDADES_A_PEDIR,
       CASE WHEN i.STOCK_ACTUAL = 0 THEN 'Agotado' ELSE 'Reponer' END AS ESTADO_STOCK
  FROM PRODUCTO p
  JOIN INVENTARIO i ON i.ID_PRODUCTO = p.ID_PRODUCTO
 WHERE p.ESTADO = 'A'
   AND i.STOCK_ACTUAL <= i.STOCK_MINIMO
 ORDER BY i.STOCK_ACTUAL;

-- ---------------------------------------------------------
-- 3. Ventas por día y tienda (solo comprobantes emitidos)
-- ---------------------------------------------------------
SELECT DATE(v.FECHA_HORA) AS DIA,
       t.NOMBRE           AS TIENDA,
       COUNT(*)           AS NRO_VENTAS,
       SUM(v.TOTAL)       AS TOTAL_VENDIDO,
       SUM(v.IGV)         AS IGV
  FROM VENTA v
  JOIN SESION_CAJA s ON s.ID_SESION = v.ID_SESION
  JOIN CAJA ca       ON ca.ID_CAJA = s.ID_CAJA
  JOIN TIENDA t      ON t.ID_TIENDA = ca.ID_TIENDA
 WHERE v.ESTADO = 'EMITIDA'
 GROUP BY DATE(v.FECHA_HORA), t.NOMBRE
 ORDER BY DIA, TIENDA;

-- ---------------------------------------------------------
-- 4. Detalle de un comprobante: ¿qué se vendió en la factura F001-1?
-- ---------------------------------------------------------
SELECT v.SERIE, v.NUMERO, cl.NOMBRE_RAZON_SOCIAL AS CLIENTE,
       p.NOMBRE AS PRODUCTO, d.CANTIDAD, d.PRECIO_UNITARIO, d.DESCUENTO, d.IMPORTE
  FROM VENTA v
  JOIN DETALLE_VENTA d ON d.ID_VENTA = v.ID_VENTA
  JOIN PRODUCTO p      ON p.ID_PRODUCTO = d.ID_PRODUCTO
  LEFT JOIN CLIENTE cl ON cl.ID_CLIENTE = v.ID_CLIENTE
 WHERE v.SERIE = 'F001' AND v.NUMERO = 1;

-- ---------------------------------------------------------
-- 5. Productos más vendidos (unidades y soles), sin contar anuladas
-- ---------------------------------------------------------
SELECT p.CODIGO_SKU,
       p.NOMBRE,
       SUM(d.CANTIDAD) AS UNIDADES,
       SUM(d.IMPORTE)  AS SOLES
  FROM DETALLE_VENTA d
  JOIN VENTA v    ON v.ID_VENTA = d.ID_VENTA
  JOIN PRODUCTO p ON p.ID_PRODUCTO = d.ID_PRODUCTO
 WHERE v.ESTADO = 'EMITIDA'
 GROUP BY p.CODIGO_SKU, p.NOMBRE
 ORDER BY SOLES DESC
 LIMIT 5;

-- ---------------------------------------------------------
-- 6. ¿Cuánto se cobró con cada método de pago?
-- ---------------------------------------------------------
SELECT mp.NOMBRE      AS METODO,
       COUNT(*)       AS NRO_PAGOS,
       SUM(pa.MONTO)  AS TOTAL
  FROM PAGO pa
  JOIN METODO_PAGO mp ON mp.ID_METODO_PAGO = pa.ID_METODO_PAGO
  JOIN VENTA v        ON v.ID_VENTA = pa.ID_VENTA
 WHERE v.ESTADO = 'EMITIDA'
 GROUP BY mp.NOMBRE
 ORDER BY TOTAL DESC;

-- ---------------------------------------------------------
-- 7. Cuadre de caja: lo que dice el sistema contra lo que se contó
--    (DIFERENCIA negativa = faltante; positiva = sobrante)
-- ---------------------------------------------------------
SELECT s.ID_SESION,
       t.NOMBRE AS TIENDA,
       ca.NUMERO_CAJA,
       CONCAT(e.NOMBRES, ' ', e.APELLIDOS) AS CAJERO,
       tu.NOMBRE AS TURNO,
       s.MONTO_APERTURA,
       s.EFECTIVO_SISTEMA,
       s.EFECTIVO_CONTADO,
       s.DIFERENCIA,
       CASE WHEN s.DIFERENCIA < 0 THEN 'Faltante'
            WHEN s.DIFERENCIA > 0 THEN 'Sobrante'
            WHEN s.ESTADO = 'ABIERTA' THEN 'Caja abierta'
            ELSE 'Cuadra' END AS RESULTADO
  FROM SESION_CAJA s
  JOIN CAJA ca     ON ca.ID_CAJA = s.ID_CAJA
  JOIN TIENDA t    ON t.ID_TIENDA = ca.ID_TIENDA
  JOIN EMPLEADO e  ON e.ID_EMPLEADO = s.ID_CAJERO
  JOIN TURNO tu    ON tu.ID_TURNO = s.ID_TURNO
 ORDER BY s.FECHA_APERTURA;

-- ---------------------------------------------------------
-- 8. Ventas por cajero
-- ---------------------------------------------------------
SELECT CONCAT(e.NOMBRES, ' ', e.APELLIDOS) AS CAJERO,
       COUNT(v.ID_VENTA)          AS NRO_VENTAS,
       COALESCE(SUM(v.TOTAL), 0)  AS TOTAL_VENDIDO
  FROM EMPLEADO e
  JOIN CARGO c ON c.ID_CARGO = e.ID_CARGO AND c.NOMBRE = 'Cajero'
  LEFT JOIN SESION_CAJA s ON s.ID_CAJERO = e.ID_EMPLEADO
  LEFT JOIN VENTA v       ON v.ID_SESION = s.ID_SESION AND v.ESTADO = 'EMITIDA'
 WHERE e.ESTADO = 'A'
 GROUP BY e.ID_EMPLEADO, e.NOMBRES, e.APELLIDOS
 ORDER BY TOTAL_VENDIDO DESC;

-- ---------------------------------------------------------
-- 9. Ventas anuladas (R4: no se borran) y el supervisor de esa caja
-- ---------------------------------------------------------
SELECT v.SERIE, v.NUMERO, v.FECHA_HORA, v.TOTAL,
       CONCAT(caj.NOMBRES, ' ', caj.APELLIDOS) AS CAJERO,
       CONCAT(sup.NOMBRES, ' ', sup.APELLIDOS) AS SUPERVISOR
  FROM VENTA v
  JOIN SESION_CAJA s    ON s.ID_SESION = v.ID_SESION
  JOIN EMPLEADO caj     ON caj.ID_EMPLEADO = s.ID_CAJERO
  LEFT JOIN EMPLEADO sup ON sup.ID_EMPLEADO = s.ID_SUPERVISOR
 WHERE v.ESTADO = 'ANULADA';

-- ---------------------------------------------------------
-- 10. Mermas por motivo: ¿cuánto se pierde por vencimiento, deterioro y robo?
-- ---------------------------------------------------------
SELECT MOTIVO,
       COUNT(*)          AS NRO_MERMAS,
       SUM(CANTIDAD)     AS UNIDADES,
       SUM(VALOR_TOTAL)  AS PERDIDA_SOLES
  FROM MERMA
 GROUP BY MOTIVO
 ORDER BY PERDIDA_SOLES DESC;

-- ---------------------------------------------------------
-- 11. Mermas pendientes de autorizar (estado REGISTRADA)
-- ---------------------------------------------------------
SELECT me.ID_MERMA, p.NOMBRE AS PRODUCTO, me.MOTIVO, me.FECHA, me.VALOR_TOTAL,
       CONCAT(r.NOMBRES, ' ', r.APELLIDOS) AS REGISTRADA_POR
  FROM MERMA me
  JOIN PRODUCTO p  ON p.ID_PRODUCTO = me.ID_PRODUCTO
  JOIN EMPLEADO r  ON r.ID_EMPLEADO = me.ID_REGISTRA
 WHERE me.ESTADO = 'REGISTRADA';

-- ---------------------------------------------------------
-- 12. Lotes vencidos o que vencen en los próximos 30 días
-- ---------------------------------------------------------
SELECT p.NOMBRE AS PRODUCTO,
       l.CODIGO_LOTE,
       l.FECHA_VENCIMIENTO,
       DATEDIFF(l.FECHA_VENCIMIENTO, CURDATE()) AS DIAS_PARA_VENCER
  FROM LOTE l
  JOIN PRODUCTO p ON p.ID_PRODUCTO = l.ID_PRODUCTO
 WHERE l.FECHA_VENCIMIENTO IS NOT NULL
   AND DATEDIFF(l.FECHA_VENCIMIENTO, CURDATE()) <= 30
 ORDER BY l.FECHA_VENCIMIENTO;

-- ---------------------------------------------------------
-- 13. Personal por tienda, con su cargo y su jefe directo
--     (EMPLEADO se une consigo misma: autorrelación)
-- ---------------------------------------------------------
SELECT t.NOMBRE AS TIENDA,
       CONCAT(e.NOMBRES, ' ', e.APELLIDOS) AS EMPLEADO,
       c.NOMBRE AS CARGO,
       CONCAT(j.NOMBRES, ' ', j.APELLIDOS) AS JEFE,
       CASE e.ESTADO WHEN 'A' THEN 'Activo' ELSE 'Inactivo' END AS ESTADO
  FROM EMPLEADO e
  JOIN TIENDA t        ON t.ID_TIENDA = e.ID_TIENDA
  JOIN CARGO c         ON c.ID_CARGO = e.ID_CARGO
  LEFT JOIN EMPLEADO j ON j.ID_EMPLEADO = e.ID_SUPERVISOR
 ORDER BY t.NOMBRE, c.ID_CARGO;

-- ---------------------------------------------------------
-- 14. Clientes que más compran (boletas y facturas emitidas)
-- ---------------------------------------------------------
SELECT cl.TIPO_DOCUMENTO,
       cl.NRO_DOCUMENTO,
       cl.NOMBRE_RAZON_SOCIAL,
       COUNT(v.ID_VENTA) AS NRO_COMPRAS,
       SUM(v.TOTAL)      AS TOTAL_COMPRADO
  FROM CLIENTE cl
  JOIN VENTA v ON v.ID_CLIENTE = cl.ID_CLIENTE AND v.ESTADO = 'EMITIDA'
 GROUP BY cl.ID_CLIENTE, cl.TIPO_DOCUMENTO, cl.NRO_DOCUMENTO, cl.NOMBRE_RAZON_SOCIAL
 ORDER BY TOTAL_COMPRADO DESC;

-- ---------------------------------------------------------
-- 15. Incidentes: qué pasó, dónde, con qué protocolo y quién responde
-- ---------------------------------------------------------
SELECT i.FECHA_HORA,
       pr.NOMBRE AS PROTOCOLO,
       pr.NIVEL_RIESGO,
       CONCAT(t.NOMBRE, ' · ', z.NOMBRE) AS LUGAR,
       CONCAT(res.NOMBRES, ' ', res.APELLIDOS) AS RESPONSABLE,
       i.MONTO_PERDIDA,
       i.ESTADO
  FROM INCIDENTE i
  JOIN PROTOCOLO_EMERGENCIA pr ON pr.ID_PROTOCOLO = i.ID_PROTOCOLO
  JOIN ZONA_TIENDA z           ON z.ID_ZONA = i.ID_ZONA
  JOIN TIENDA t                ON t.ID_TIENDA = z.ID_TIENDA
  JOIN EMPLEADO res            ON res.ID_EMPLEADO = i.ID_RESPONSABLE
 ORDER BY i.FECHA_HORA DESC;

-- =========================================================
-- PRUEBAS DE LOS TRIGGERS
-- Están comentadas porque deben dar ERROR a propósito.
-- Quita el "-- " de una línea y ejecútala sola para ver el mensaje.
-- =========================================================

-- R4: las ventas no se borran → "R4: las ventas no se borran..."
-- DELETE FROM VENTA WHERE ID_VENTA = 1;

-- R4: una anulada no vuelve a emitirse → "R4: una venta anulada no puede volver a emitirse."
-- UPDATE VENTA SET ESTADO = 'EMITIDA' WHERE ID_VENTA = 7;

-- R7: no se vende con la caja cerrada (la sesión 1 está CERRADA) → "R7: la sesion de caja esta cerrada..."
-- INSERT INTO VENTA (ID_VENTA, ID_SESION, TIPO_COMPROBANTE, SERIE, NUMERO, SUBTOTAL, IGV, TOTAL) VALUES (99, 1, 'BOLETA', 'B001', 99, 8.47, 1.53, 10.00);

-- CK_MER_6: nadie autoriza su propia merma → error de CHECK
-- INSERT INTO MERMA (ID_MERMA, ID_PRODUCTO, ID_ZONA, ID_REGISTRA, ID_AUTORIZA, MOTIVO, FECHA, CANTIDAD, COSTO_UNITARIO, VALOR_TOTAL, ESTADO) VALUES (99, 5, 2, 2, 2, 'DETERIORO', '2026-10-22', 1, 3.20, 3.20, 'APROBADA');

