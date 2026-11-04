-- Minimarket Mass Arequipa · otro cajero para probar 2 cajas abiertas · MySQL 8 (Laragon)
-- Ejecutar DESPUÉS de usuarios.sql e ingreso.sql. Se puede correr más de una vez.
-- Cuenta de prueba (inventada):
--   jccama / Jorge#2026  -> Jorge Ccama Vilca, Cajero (tienda Cayma)
-- La contraseña NO se guarda: se guarda su hash, creado con password_hash() de PHP.

USE minimarket_mass;

UPDATE EMPLEADO SET USUARIO = 'jccama', CLAVE_HASH = '$2y$10$vb37EzdUyk.FF0wOSsn8quUCHN0POKP7kmJIYCfQdfKpXojG/Jrce' WHERE ID_EMPLEADO = 6;

-- El código de ingreso le llega al mismo correo que a rquispe
UPDATE EMPLEADO e
  JOIN (SELECT CORREO FROM EMPLEADO WHERE USUARIO = 'rquispe') r
   SET e.CORREO = r.CORREO
 WHERE e.ID_EMPLEADO = 6;

-- Comprobación: debe salir jccama como Cajero, con correo
SELECT e.USUARIO, e.NOMBRES, e.APELLIDOS, c.NOMBRE AS CARGO, e.CORREO
FROM EMPLEADO e JOIN CARGO c ON c.ID_CARGO = e.ID_CARGO
WHERE e.USUARIO IS NOT NULL;
