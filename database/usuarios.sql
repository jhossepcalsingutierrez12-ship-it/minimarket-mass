-- Minimarket Mass Arequipa · cuentas para el login · MySQL 8 (Laragon)
-- Ejecutar DESPUÉS de schema.sql y datos.sql. Correrlo solo una vez:
-- si se vuelve a correr, el ALTER TABLE da error porque las columnas ya existen.
-- Las contraseñas NO se guardan: se guarda su hash, creado con password_hash() de PHP.
-- Cuentas de prueba (inventadas):
--   jmamani / Admin#2026   -> Julia Mamani, Administradora
--   chuaman / Super#2026   -> Carlos Huamán, Supervisor
--   rquispe / Cajero#2026  -> Rosa Quispe, Cajera

USE minimarket_mass;

ALTER TABLE EMPLEADO
  ADD COLUMN USUARIO VARCHAR(30) NULL,
  ADD COLUMN CLAVE_HASH VARCHAR(255) NULL,
  ADD CONSTRAINT UQ_EMP_USUARIO UNIQUE (USUARIO);

UPDATE EMPLEADO SET USUARIO = 'jmamani', CLAVE_HASH = '$2y$10$2SX3RbUeV/d9wvaEa/EJf.L5rLTneKC2jBFSqQteI760VQEdDMzDS' WHERE ID_EMPLEADO = 1;
UPDATE EMPLEADO SET USUARIO = 'chuaman', CLAVE_HASH = '$2y$10$m102UBiicwEhlADfBHNtYuVxEyj7Rw.sH2fOpFnceUfVia5COxKY2' WHERE ID_EMPLEADO = 2;
UPDATE EMPLEADO SET USUARIO = 'rquispe', CLAVE_HASH = '$2y$10$Td3LECba6tqxV81twIJ75uFqOEnncolCgd5J9e17Jwl/nFK5L1RtG' WHERE ID_EMPLEADO = 5;

-- Comprobación: deben salir las 3 cuentas con su cargo
SELECT e.USUARIO, e.NOMBRES, e.APELLIDOS, c.NOMBRE AS CARGO
FROM EMPLEADO e JOIN CARGO c ON c.ID_CARGO = e.ID_CARGO
WHERE e.USUARIO IS NOT NULL;