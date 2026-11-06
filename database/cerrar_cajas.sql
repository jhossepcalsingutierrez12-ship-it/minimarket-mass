-- Minimarket Mass Arequipa · cerrar todas las cajas abiertas · MySQL 8 (Laragon)
-- Úsalo antes de una prueba o exposición: deja las 9 cajas libres para que cada cajero abra la suya al entrar.
-- Cada caja se cierra con lo que debía haber (apertura + ventas en efectivo) y diferencia 0.
--   mysql -u root -P 3306 --default-character-set=utf8mb4 minimarket_mass -e "source database/cerrar_cajas.sql"

SET NAMES utf8mb4;
USE minimarket_mass;

UPDATE SESION_CAJA s
   SET EFECTIVO_SISTEMA = s.MONTO_APERTURA + COALESCE((SELECT SUM(p.MONTO) FROM PAGO p JOIN VENTA v ON v.ID_VENTA = p.ID_VENTA
                          WHERE v.ID_SESION = s.ID_SESION AND v.ESTADO = 'EMITIDA' AND p.ID_METODO_PAGO = 1), 0)
 WHERE s.ESTADO = 'ABIERTA';

UPDATE SESION_CAJA
   SET EFECTIVO_CONTADO = EFECTIVO_SISTEMA, DIFERENCIA = 0, ESTADO = 'CERRADA',
       FECHA_CIERRE = GREATEST(NOW(), FECHA_APERTURA + INTERVAL 1 MINUTE)
 WHERE ESTADO = 'ABIERTA';

-- Comprobación: debe salir 0
SELECT COUNT(*) AS CAJAS_ABIERTAS FROM SESION_CAJA WHERE ESTADO = 'ABIERTA';
