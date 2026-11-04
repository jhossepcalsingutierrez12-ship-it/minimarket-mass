-- Minimarket Mass Arequipa · cuadre de stock del día · MySQL 8 (Laragon)
-- Ejecutar DESPUÉS de los demás .sql. Correrlo solo una vez.
-- LOTE no guardaba cuándo llegó la mercadería; para el cuadre del día hace falta saberlo.

USE minimarket_mass;

ALTER TABLE LOTE ADD COLUMN FECHA_RECEPCION DATETIME NULL;

-- Comprobación: debe salir la columna FECHA_RECEPCION
SHOW COLUMNS FROM LOTE;
