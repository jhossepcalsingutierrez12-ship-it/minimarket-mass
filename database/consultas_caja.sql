-- Minimarket Mass Arequipa · preguntas y reportes del Administrador al cajero por un faltante de caja · MySQL 8 (Laragon)
-- Correr después de admin.sql. Se puede volver a correr: no repite nada.
--   mysql -u root -P 3306 --default-character-set=utf8mb4 minimarket_mass -e "source database/consultas_caja.sql"
--
-- Cuando una caja se cierra con faltante (SESION_CAJA.DIFERENCIA < 0), el Administrador puede:
--   PREGUNTAR al cajero qué pasó con el dinero, o
--   REPORTAR el faltante (llamada de atención).
-- El cajero lo ve al entrar al sistema y responde con su explicación.

SET NAMES utf8mb4;
USE minimarket_mass;

CREATE TABLE IF NOT EXISTS CONSULTA_CAJA (
  ID_CONSULTA INT NOT NULL,
  ID_SESION INT NOT NULL,                  -- la caja que se cerró con faltante
  ID_ADMIN INT NOT NULL,                   -- quién pregunta o reporta
  ID_CAJERO INT NOT NULL,                  -- a quién le llega
  TIPO VARCHAR(8) NOT NULL,                -- PREGUNTA o REPORTE
  MENSAJE VARCHAR(500) NOT NULL,
  FECHA_ENVIO DATETIME NOT NULL,
  RESPUESTA VARCHAR(500) NULL,
  FECHA_RESPUESTA DATETIME NULL,
  ESTADO VARCHAR(10) NOT NULL DEFAULT 'PENDIENTE',
  CONSTRAINT PK_CONSULTA_CAJA PRIMARY KEY (ID_CONSULTA),
  CONSTRAINT FK_CON_SESION FOREIGN KEY (ID_SESION) REFERENCES SESION_CAJA (ID_SESION),
  CONSTRAINT FK_CON_ADMIN FOREIGN KEY (ID_ADMIN) REFERENCES EMPLEADO (ID_EMPLEADO),
  CONSTRAINT FK_CON_CAJERO FOREIGN KEY (ID_CAJERO) REFERENCES EMPLEADO (ID_EMPLEADO),
  CONSTRAINT CK_CON_1 CHECK (TIPO IN ('PREGUNTA','REPORTE')),
  CONSTRAINT CK_CON_2 CHECK (ESTADO IN ('PENDIENTE','RESPONDIDA')),
  CONSTRAINT CK_CON_3 CHECK ((ESTADO = 'PENDIENTE' AND RESPUESTA IS NULL) OR (ESTADO = 'RESPONDIDA' AND RESPUESTA IS NOT NULL))
) ENGINE = InnoDB;

-- Ejemplo: la Administradora (1) ya preguntó por el faltante de S/ 5.00 de Yanahuara y el cajero respondió
SET @ses := (SELECT s.ID_SESION FROM SESION_CAJA s WHERE s.ESTADO = 'CERRADA' AND s.DIFERENCIA = -5.00
             ORDER BY s.FECHA_CIERRE DESC LIMIT 1);
INSERT INTO CONSULTA_CAJA (ID_CONSULTA, ID_SESION, ID_ADMIN, ID_CAJERO, TIPO, MENSAJE, FECHA_ENVIO, RESPUESTA, FECHA_RESPUESTA, ESTADO)
SELECT 1, s.ID_SESION, 1, s.ID_CAJERO, 'PREGUNTA',
       'Hola, al cerrar tu caja faltaron S/ 5.00. ¿Qué pasó con el dinero? ¿Diste algún vuelto de más?',
       DATE_ADD(s.FECHA_CIERRE, INTERVAL 10 HOUR),
       'Buenos días. Creo que di S/ 5.00 de más de vuelto a un cliente que pagó con S/ 50 a última hora. No volverá a pasar.',
       DATE_ADD(s.FECHA_CIERRE, INTERVAL 11 HOUR), 'RESPONDIDA'
FROM SESION_CAJA s
WHERE s.ID_SESION = @ses AND NOT EXISTS (SELECT 1 FROM CONSULTA_CAJA WHERE ID_CONSULTA = 1);

-- Comprobación
SELECT ID_CONSULTA, ID_SESION, TIPO, ESTADO, MENSAJE FROM CONSULTA_CAJA ORDER BY ID_CONSULTA;
