-- Minimarket Mass Arequipa · código de ingreso por correo · MySQL 8 (Laragon)
-- Ejecutar DESPUÉS de usuarios.sql y extras.sql. Correrlo solo una vez.
-- ANTES de correrlo: cambia los 3 correos de abajo por correos reales
-- (pueden ser el mismo tuyo para las 3 cuentas). Ahí llegará el código de 6 dígitos.

USE minimarket_mass;

-- 1. Correo de cada empleado: ahí llega el código del paso 2
ALTER TABLE EMPLEADO ADD COLUMN CORREO VARCHAR(120) NULL;

UPDATE EMPLEADO SET CORREO = 'ronnypumacayo123@gmail.com'      WHERE USUARIO = 'jmamani';
UPDATE EMPLEADO SET CORREO = 'ronnypumacayo123@gmail.com' WHERE USUARIO = 'chuaman';
UPDATE EMPLEADO SET CORREO = 'ronnypumacayo123@gmail.com'     WHERE USUARIO = 'rquispe';

-- Comprobación: deben salir los 3 usuarios con su correo
SELECT USUARIO, CORREO FROM EMPLEADO WHERE USUARIO IS NOT NULL;
