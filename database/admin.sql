-- Minimarket Mass Arequipa · funciones del Administrador · MySQL 8 (Laragon)
-- Agrega la tabla HISTORIAL (historial de actividades: quién hizo qué y cuándo).
-- Correr después de cuadre.sql. Se puede volver a correr: no repite nada.
-- Córrelo con --default-character-set=utf8mb4 para que las tildes y la ñ se guarden bien:
--   mysql -u root -P 3306 --default-character-set=utf8mb4 minimarket_mass -e "source database/admin.sql"
--
-- Proveedores y compras se quitaron porque no están en el modelo de la base de datos:
-- si se habían creado antes, aquí se borran esas tablas (la mercadería recibida queda en LOTE).

SET NAMES utf8mb4;
USE minimarket_mass;

-- ---------------------------------------------------------
-- Quitar proveedores y compras (no están en el modelo)
-- ---------------------------------------------------------
DROP TABLE IF EXISTS DETALLE_COMPRA;
DROP TABLE IF EXISTS COMPRA;
DROP TABLE IF EXISTS PROVEEDOR;

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

-- Lo que el historial decía de proveedores y compras pasa a ser «Recibió un lote» (sin nombres de proveedores)
DELETE FROM HISTORIAL WHERE ACCION = 'REGISTRAR_PROVEEDOR';
UPDATE HISTORIAL
   SET ACCION = 'RECIBIR_LOTE',
       DETALLE = CONCAT('Lotes recibidos (factura ', SUBSTRING_INDEX(SUBSTRING_INDEX(DETALLE, ' ', 2), ' ', -1), ') · ',
                        SUBSTRING_INDEX(DETALLE, ' · ', -2))
 WHERE ACCION = 'REGISTRAR_COMPRA';

-- ---------------------------------------------------------
-- Datos de prueba (si ya estaban, se corrigen)
-- ---------------------------------------------------------
INSERT INTO HISTORIAL (ID_HISTORIAL, ID_EMPLEADO, FECHA_HORA, ACCION, DETALLE) VALUES
  (1, 1, '2026-10-15 09:00:00', 'RECIBIR_LOTE', 'Lotes recibidos (factura F001-000245) · 120 × Leche Gloria azul 400 g y 30 × Yogurt Gloria fresa 1 L'),
  (2, 9, '2026-10-19 08:40:00', 'REGISTRAR_MERMA', 'Merma: 4 × Yogurt Gloria fresa 1 L (vencimiento) · S/ 21.60'),
  (3, 2, '2026-10-19 09:05:00', 'AUTORIZAR_MERMA', 'Aprobó la merma de Yogurt Gloria fresa 1 L'),
  (4, 3, '2026-10-20 14:00:00', 'ANULAR_VENTA', 'Anuló B002-3 (S/ 18.00)'),
  (5, 2, '2026-10-20 15:05:00', 'CERRAR_CAJA', 'Cerró la sesión 1 · sistema S/ 254.70 · contado S/ 254.70 · diferencia S/ 0.00')
ON DUPLICATE KEY UPDATE ACCION = VALUES(ACCION), DETALLE = VALUES(DETALLE);

-- Comprobación
SELECT ACCION, COUNT(*) AS VECES FROM HISTORIAL GROUP BY ACCION ORDER BY ACCION;
