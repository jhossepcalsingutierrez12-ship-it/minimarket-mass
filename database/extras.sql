-- Minimarket Mass Arequipa · lo que necesita el sistema web completo · MySQL 8 (Laragon)
-- Ejecutar DESPUÉS de schema.sql, datos.sql y usuarios.sql. Correrlo solo una vez:
-- si se vuelve a correr, el ALTER TABLE da error porque las columnas ya existen.

USE minimarket_mass;

-- 1. PAGO: en efectivo se guarda cuánto entregó el cliente y su vuelto (salen en el comprobante)
ALTER TABLE PAGO
  ADD COLUMN MONTO_RECIBIDO DECIMAL(12,2) NULL,
  ADD COLUMN VUELTO DECIMAL(12,2) NULL,
  ADD CONSTRAINT CK_PAG_RECIBIDO CHECK (MONTO_RECIBIDO IS NULL OR MONTO_RECIBIDO >= MONTO),
  ADD CONSTRAINT CK_PAG_VUELTO CHECK (VUELTO IS NULL OR VUELTO = MONTO_RECIBIDO - MONTO);

-- Comprobación: PAGO debe mostrar MONTO_RECIBIDO y VUELTO
SHOW COLUMNS FROM PAGO;
