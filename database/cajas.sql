-- Minimarket Mass Arequipa · más cajas en las 3 tiendas · MySQL 8 (Laragon)
-- Deja 3 cajas activas en Cayma, Cerro Colorado y Yanahuara para que el cajero elija dónde abrir.
-- Se puede correr más de una vez (INSERT IGNORE no repite las que ya existen).

USE minimarket_mass;

UPDATE CAJA SET ESTADO = 'A' WHERE ID_CAJA = 4;            -- Cerro Colorado · Caja 2 vuelve a funcionar

INSERT IGNORE INTO CAJA (ID_CAJA, ID_TIENDA, NUMERO_CAJA, ESTADO) VALUES
  (6, 1, 3, 'A'),                                          -- Cayma · Caja 3
  (7, 2, 3, 'A'),                                          -- Cerro Colorado · Caja 3
  (8, 3, 2, 'A'),                                          -- Yanahuara · Caja 2
  (9, 3, 3, 'A');                                          -- Yanahuara · Caja 3

-- Comprobación: deben salir 3 cajas activas por tienda
SELECT t.NOMBRE AS TIENDA, c.NUMERO_CAJA, c.ESTADO
FROM CAJA c JOIN TIENDA t ON t.ID_TIENDA = c.ID_TIENDA ORDER BY t.ID_TIENDA, c.NUMERO_CAJA;
