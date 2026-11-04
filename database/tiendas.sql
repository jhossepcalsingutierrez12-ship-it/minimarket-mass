-- Minimarket Mass Arequipa · las 3 tiendas: Cayma, Cerro Colorado y Yanahuara · MySQL 8 (Laragon)
-- La tercera tienda pasa a ser Mass Yanahuara (antes José Luis Bustamante).
-- Se puede correr las veces que quieras (solo actualiza datos).

USE minimarket_mass;

UPDATE TIENDA
   SET NOMBRE = 'Mass Yanahuara', DIRECCION = 'Av. Emmel 120', DISTRITO = 'Yanahuara'
 WHERE ID_TIENDA = 3;

-- Comprobación: deben salir Cayma, Cerro Colorado y Yanahuara
SELECT ID_TIENDA, CODIGO_TIENDA, NOMBRE, DIRECCION, DISTRITO FROM TIENDA ORDER BY ID_TIENDA;
