-- Minimarket Mass Arequipa · datos de prueba · MySQL 8 (Laragon)
-- Ejecutar DESPUÉS de schema.sql (schema.sql deja las tablas vacías).
-- Todos los datos son inventados: DNI, RUC, nombres y teléfonos no son reales.
-- Orden: primero las tablas sin claves foráneas, al final ventas, pagos y mermas.

SET NAMES utf8mb4;
USE minimarket_mass;

-- =========================================================
-- 1. TIENDAS Y ZONAS
-- =========================================================
INSERT INTO TIENDA (ID_TIENDA, CODIGO_TIENDA, NOMBRE, RUC_EMPRESA, RAZON_SOCIAL, DIRECCION, DISTRITO, TELEFONO, AREA_M2, AFORO, HORA_APERTURA, HORA_CIERRE, FECHA_APERTURA, NRO_CERTIFICADO_ITSE, FECHA_VENC_ITSE) VALUES
  (1, 'AQP-01', 'Mass Cayma', '20601234567', 'Minimarket Mass Arequipa S.A.C.', 'Av. Ejército 1020', 'Cayma', '054-251100', 180.00, 60, '07:00', '23:00', '2023-03-15', 'ITSE-2025-0412', '2027-03-14'),
  (2, 'AQP-02', 'Mass Cerro Colorado', '20601234567', 'Minimarket Mass Arequipa S.A.C.', 'Av. Aviación 2315', 'Cerro Colorado', '054-251200', 150.00, 45, '07:00', '22:00', '2024-01-20', 'ITSE-2025-0587', '2026-12-10'),
  (3, 'AQP-03', 'Mass José Luis Bustamante', '20601234567', 'Minimarket Mass Arequipa S.A.C.', 'Av. Dolores 410', 'José Luis Bustamante y Rivero', NULL, 120.00, 40, '07:00', '22:00', '2025-06-02', NULL, NULL);

INSERT INTO ZONA_TIENDA (ID_ZONA, ID_TIENDA, NOMBRE, TIPO, ES_ZONA_SEGURA) VALUES
  (1, 1, 'Salón de ventas', 'VENTA', 'N'),
  (2, 1, 'Almacén', 'ALMACEN', 'N'),
  (3, 1, 'Zona de cajas', 'CAJA', 'N'),
  (4, 1, 'Punto de reunión', 'EXTERIOR', 'S'),
  (5, 2, 'Salón de ventas', 'VENTA', 'N'),
  (6, 2, 'Almacén', 'ALMACEN', 'N'),
  (7, 2, 'Zona de cajas', 'CAJA', 'N'),
  (8, 2, 'Punto de reunión', 'EXTERIOR', 'S'),
  (9, 3, 'Salón de ventas', 'VENTA', 'N'),
  (10, 3, 'Almacén', 'ALMACEN', 'N'),
  (11, 3, 'Zona de cajas', 'CAJA', 'N'),
  (12, 3, 'Punto de reunión', 'EXTERIOR', 'S');

-- =========================================================
-- 2. PERSONAL
-- =========================================================
INSERT INTO CARGO (ID_CARGO, NOMBRE, DESCRIPCION) VALUES
  (1, 'Administrador', 'Responsable de la tienda, ve reportes'),
  (2, 'Supervisor', 'Abre y cierra cajas, anula ventas y autoriza mermas'),
  (3, 'Cajero', 'Atiende en caja y emite comprobantes'),
  (4, 'Reponedor', 'Recibe mercadería y repone góndolas');

INSERT INTO TURNO (ID_TURNO, NOMBRE, HORA_INICIO, HORA_FIN) VALUES
  (1, 'Mañana', '07:00', '15:00'),
  (2, 'Tarde', '15:00', '23:00');

-- Administradora (sin supervisor)
INSERT INTO EMPLEADO (ID_EMPLEADO, ID_TIENDA, ID_CARGO, ID_SUPERVISOR, DNI, NOMBRES, APELLIDOS, TELEFONO, GRUPO_SANGUINEO, CONTACTO_FAMILIAR, TELEFONO_FAMILIAR, FECHA_INGRESO, SUELDO, ESTADO) VALUES
  (1, 1, 1, NULL, '40112233', 'Julia', 'Mamani Ccori', '958111001', 'O+', 'Pedro Mamani', '958111901', '2023-03-01', 3200.00, 'A');

-- Supervisores (reportan a la administradora)
INSERT INTO EMPLEADO (ID_EMPLEADO, ID_TIENDA, ID_CARGO, ID_SUPERVISOR, DNI, NOMBRES, APELLIDOS, TELEFONO, GRUPO_SANGUINEO, CONTACTO_FAMILIAR, TELEFONO_FAMILIAR, FECHA_INGRESO, SUELDO, ESTADO) VALUES
  (2, 1, 2, 1, '41223344', 'Carlos', 'Huamán Torres', '958111002', 'A+', 'Elena Torres', '958111902', '2023-03-01', 2100.00, 'A'),
  (3, 2, 2, 1, '42334455', 'Lucía', 'Condori Apaza', '958111003', 'O+', 'Mario Condori', '958111903', '2024-01-15', 2100.00, 'A'),
  (4, 3, 2, 1, '43445566', 'Diego', 'Quispe Flores', '958111004', 'B+', NULL, NULL, '2025-05-20', 2000.00, 'A');

-- Cajeros y reponedor (reportan a su supervisor). Sandra está inactiva.
INSERT INTO EMPLEADO (ID_EMPLEADO, ID_TIENDA, ID_CARGO, ID_SUPERVISOR, DNI, NOMBRES, APELLIDOS, TELEFONO, GRUPO_SANGUINEO, CONTACTO_FAMILIAR, TELEFONO_FAMILIAR, FECHA_INGRESO, SUELDO, ESTADO) VALUES
  (5, 1, 3, 2, '70112233', 'Rosa', 'Quispe Huanca', '958111005', 'O+', 'Ana Huanca', '958111905', '2024-02-01', 1250.00, 'A'),
  (6, 1, 3, 2, '71223344', 'Jorge', 'Ccama Vilca', '958111006', NULL, NULL, NULL, '2024-08-10', 1250.00, 'A'),
  (7, 2, 3, 3, '72334455', 'Milagros', 'Pari Choque', '958111007', 'A+', 'José Pari', '958111907', '2024-01-20', 1250.00, 'A'),
  (8, 3, 3, 4, '73445566', 'Kevin', 'Turpo Mendoza', '958111008', NULL, NULL, NULL, '2025-06-01', 1200.00, 'A'),
  (9, 1, 4, 2, '74556677', 'Wilber', 'Apaza Luque', '958111009', 'O-', NULL, NULL, '2023-04-10', 1150.00, 'A'),
  (10, 2, 3, 3, '75667788', 'Sandra', 'Llerena Paz', '958111010', NULL, NULL, NULL, '2024-02-01', 1250.00, 'I');

INSERT INTO CAJA (ID_CAJA, ID_TIENDA, NUMERO_CAJA, ESTADO) VALUES
  (1, 1, 1, 'A'),
  (2, 1, 2, 'A'),
  (3, 2, 1, 'A'),
  (4, 2, 2, 'I'),
  (5, 3, 1, 'A');

-- =========================================================
-- 3. CLIENTES Y MÉTODOS DE PAGO
-- =========================================================
INSERT INTO CLIENTE (ID_CLIENTE, TIPO_DOCUMENTO, NRO_DOCUMENTO, NOMBRE_RAZON_SOCIAL, TELEFONO, FECHA_REGISTRO) VALUES
  (1, 'DNI', '45678901', 'María Fernanda Salas Rivera', '959222001', '2026-01-10 10:15:00'),
  (2, 'DNI', '46789012', 'Luis Alberto Chávez Núñez', NULL, '2026-03-05 18:40:00'),
  (3, 'RUC', '20456789012', 'Restaurante El Sabor Arequipeño E.I.R.L.', '054-262626', '2026-02-14 09:00:00'),
  (4, 'RUC', '10457890123', 'Bodega Doña Carmen', '959222004', '2026-05-22 11:30:00'),
  (5, 'CE', '001234567', 'Andrea Gómez Pérez', NULL, '2026-07-01 16:20:00');

INSERT INTO METODO_PAGO (ID_METODO_PAGO, NOMBRE) VALUES
  (1, 'Efectivo'),
  (2, 'Tarjeta de débito'),
  (3, 'Tarjeta de crédito'),
  (4, 'Yape'),
  (5, 'Plin');

-- =========================================================
-- 4. CATÁLOGO
-- =========================================================
-- Categorías principales
INSERT INTO CATEGORIA (ID_CATEGORIA, ID_CATEGORIA_PADRE, NOMBRE) VALUES
  (1, NULL, 'Abarrotes'),
  (2, NULL, 'Lácteos'),
  (3, NULL, 'Bebidas'),
  (4, NULL, 'Limpieza');

-- Subcategorías
INSERT INTO CATEGORIA (ID_CATEGORIA, ID_CATEGORIA_PADRE, NOMBRE) VALUES
  (5, 1, 'Arroz y menestras'),
  (6, 1, 'Aceites'),
  (7, 1, 'Azúcar'),
  (8, 2, 'Leches'),
  (9, 2, 'Yogurts'),
  (10, 3, 'Gaseosas'),
  (11, 3, 'Aguas'),
  (12, 4, 'Detergentes');

INSERT INTO MARCA (ID_MARCA, NOMBRE, ES_PROPIA) VALUES
  (1, 'Costeño', 'N'),
  (2, 'Gloria', 'N'),
  (3, 'Inca Kola', 'N'),
  (4, 'Cartavio', 'N'),
  (5, 'Primor', 'N'),
  (6, 'San Luis', 'N'),
  (7, 'Bolívar', 'N'),
  (8, 'Del Misti', 'S');

-- Los 6 primeros SKU son los mismos de models/ProductoRepository.php
INSERT INTO PRODUCTO (ID_PRODUCTO, ID_CATEGORIA, ID_MARCA, CODIGO_SKU, CODIGO_BARRAS, NOMBRE, CONTENIDO, PRECIO_VENTA, ES_PERECIBLE, ESTADO) VALUES
  (1, 5, 1, 'ABA-0001', '7750243000011', 'Arroz Costeño extra 5 kg', 5.000, 24.90, 'N', 'A'),
  (2, 8, 2, 'LAC-0001', '7751271000028', 'Leche Gloria azul 400 g', 0.400, 4.20, 'S', 'A'),
  (3, 9, 2, 'LAC-0002', '7751271000035', 'Yogurt Gloria fresa 1 L', 1.000, 7.90, 'S', 'A'),
  (4, 10, 3, 'BEB-0001', '7750182000042', 'Inca Kola 1.5 L', 1.500, 8.50, 'N', 'A'),
  (5, 7, 4, 'ABA-0002', '7750106000059', 'Azúcar rubia Cartavio 1 kg', 1.000, 4.50, 'N', 'A'),
  (6, 6, 5, 'ABA-0003', '7750243000066', 'Aceite Primor 900 ml', 0.900, 11.90, 'N', 'A'),
  (7, 11, 6, 'BEB-0002', '7750182000073', 'Agua San Luis sin gas 2.5 L', 2.500, 3.80, 'N', 'A'),
  (8, 12, 7, 'LIM-0001', '7750106000080', 'Detergente Bolívar 2.6 kg', 2.600, 29.90, 'N', 'A'),
  (9, 5, 8, 'ABA-0004', '7759999000097', 'Lentejas Del Misti 500 g', 0.500, 5.20, 'N', 'A'),
  (10, 8, 2, 'LAC-0003', NULL, 'Leche Gloria light 400 g (descontinuada)', 0.400, 4.40, 'S', 'I');

-- =========================================================
-- 5. INVENTARIO Y LOTES
-- STOCK_ACTUAL inicial = stock final + lo vendido + mermas,
-- porque los triggers lo descuentan al insertar ventas y mermas.
-- Al terminar el script, el stock coincide con el de ProductoRepository.php.
-- =========================================================
INSERT INTO INVENTARIO (ID_PRODUCTO, ID_ZONA, UBICACION, STOCK_ACTUAL, STOCK_MINIMO, STOCK_MAXIMO, FECHA_ULT_CONTEO) VALUES
  (1, 2, 'P1-E1', 26, 10, 80, '2026-10-19 21:00:00'),
  (2, 6, 'P1-E2', 99, 24, 200, '2026-10-19 21:00:00'),
  (3, 2, 'P1-E3', 14, 12, 60, '2026-10-19 21:00:00'),
  (4, 6, 'P1-E4', 14, 12, 96, '2026-10-19 21:00:00'),
  (5, 2, 'P2-E1', 73, 20, 150, '2026-10-19 21:00:00'),
  (6, 6, 'P2-E2', 35, 10, 80, '2026-10-19 21:00:00'),
  (7, 2, 'P2-E3', 52, 24, 120, '2026-10-19 21:00:00'),
  (8, 6, 'P2-E4', 16, 8, 40, '2026-10-19 21:00:00'),
  (9, 2, 'P3-E1', 26, 15, 70, '2026-10-19 21:00:00'),
  (10, 6, 'P3-E2', 0, 0, 0, '2026-10-19 21:00:00');

INSERT INTO LOTE (ID_LOTE, ID_PRODUCTO, CODIGO_LOTE, FECHA_VENCIMIENTO, CANTIDAD_RECIBIDA, COSTO_UNITARIO) VALUES
  (1, 2, 'GL-2610-A', '2027-03-15', 120, 3.10),
  (2, 3, 'GL-2611-Y', '2026-11-08', 30, 5.40),
  (3, 3, 'GL-2609-Y', '2026-10-18', 12, 5.40),
  (4, 1, 'CS-2605', '2027-05-30', 40, 19.80),
  (5, 6, 'PR-2608', '2027-08-01', 36, 8.90),
  (6, 4, 'IK-2610', '2026-12-20', 72, 5.80),
  (7, 9, 'DM-2609', '2026-11-20', 40, 3.60);

INSERT INTO PROTOCOLO_EMERGENCIA (ID_PROTOCOLO, NOMBRE, TIPO_EVENTO, NIVEL_RIESGO, VERSION, FECHA_APROBACION) VALUES
  (1, 'Evacuación por sismo', 'SISMO', 'ALTO', 2, '2025-08-15'),
  (2, 'Amago de incendio', 'INCENDIO', 'ALTO', 1, '2024-03-10'),
  (3, 'Robo o hurto en tienda', 'ROBO', 'MEDIO', 1, '2024-03-10');

-- =========================================================
-- 6. CAJAS, VENTAS Y PAGOS
-- R7: las sesiones se crean ABIERTAS; se cierran al final con UPDATE.
-- =========================================================
INSERT INTO SESION_CAJA (ID_SESION, ID_CAJA, ID_CAJERO, ID_TURNO, ID_SUPERVISOR, FECHA_APERTURA, MONTO_APERTURA) VALUES
  (1, 1, 5, 1, 2, '2026-10-20 07:00:00', 200.00),
  (2, 3, 7, 1, 3, '2026-10-20 07:05:00', 150.00),
  (3, 2, 6, 2, 2, '2026-10-21 15:00:00', 200.00),
  (4, 1, 5, 1, 2, '2026-10-22 07:00:00', 200.00);

-- El precio incluye IGV: IGV = total × 18/118 y SUBTOTAL = TOTAL − IGV
INSERT INTO VENTA (ID_VENTA, ID_SESION, ID_CLIENTE, TIPO_COMPROBANTE, SERIE, NUMERO, FECHA_HORA, SUBTOTAL, IGV, TOTAL) VALUES
  (1, 1, NULL, 'BOLETA', 'B001', 1, '2026-10-20 08:12:00', 28.73, 5.17, 33.90),
  (2, 1, 1, 'BOLETA', 'B001', 2, '2026-10-20 09:40:00', 39.15, 7.05, 46.20),
  (3, 1, 3, 'FACTURA', 'F001', 1, '2026-10-20 10:25:00', 129.49, 23.31, 152.80),
  (4, 1, NULL, 'BOLETA', 'B001', 3, '2026-10-20 12:03:00', 17.63, 3.17, 20.80),
  (5, 2, NULL, 'BOLETA', 'B002', 1, '2026-10-20 08:30:00', 35.85, 6.45, 42.30),
  (6, 2, 2, 'BOLETA', 'B002', 2, '2026-10-20 11:15:00', 32.03, 5.77, 37.80),
  (7, 2, NULL, 'BOLETA', 'B002', 3, '2026-10-20 13:50:00', 15.25, 2.75, 18.00),
  (8, 3, 4, 'FACTURA', 'F001', 2, '2026-10-21 16:20:00', 126.02, 22.68, 148.70),
  (9, 3, NULL, 'BOLETA', 'B001', 4, '2026-10-21 19:45:00', 38.90, 7.00, 45.90),
  (10, 3, 5, 'BOLETA', 'B001', 5, '2026-10-21 21:10:00', 41.69, 7.51, 49.20),
  (11, 4, NULL, 'BOLETA', 'B001', 6, '2026-10-22 08:05:00', 28.73, 5.17, 33.90);

-- IMPORTE = CANTIDAD × PRECIO_UNITARIO − DESCUENTO. El trigger R5 descuenta el stock.
INSERT INTO DETALLE_VENTA (ID_VENTA, ID_PRODUCTO, CANTIDAD, PRECIO_UNITARIO, DESCUENTO, IMPORTE) VALUES
  (1, 1, 1.000, 24.90, 0.00, 24.90),
  (1, 5, 2.000, 4.50, 0.00, 9.00),
  (2, 2, 6.000, 4.20, 0.00, 25.20),
  (2, 3, 2.000, 7.90, 0.00, 15.80),
  (2, 9, 1.000, 5.20, 0.00, 5.20),
  (3, 1, 4.000, 24.90, 5.00, 94.60),
  (3, 6, 3.000, 11.90, 0.00, 35.70),
  (3, 5, 5.000, 4.50, 0.00, 22.50),
  (4, 4, 2.000, 8.50, 0.00, 17.00),
  (4, 7, 1.000, 3.80, 0.00, 3.80),
  (5, 4, 3.000, 8.50, 0.00, 25.50),
  (5, 2, 4.000, 4.20, 0.00, 16.80),
  (6, 8, 1.000, 29.90, 0.00, 29.90),
  (6, 3, 1.000, 7.90, 0.00, 7.90),
  (7, 7, 2.000, 3.80, 0.00, 7.60),
  (7, 9, 2.000, 5.20, 0.00, 10.40),
  (8, 1, 3.000, 24.90, 0.00, 74.70),
  (8, 5, 6.000, 4.50, 1.00, 26.00),
  (8, 2, 12.000, 4.20, 2.40, 48.00),
  (9, 4, 4.000, 8.50, 0.00, 34.00),
  (9, 6, 1.000, 11.90, 0.00, 11.90),
  (10, 3, 1.000, 7.90, 0.00, 7.90),
  (10, 8, 1.000, 29.90, 0.00, 29.90),
  (10, 7, 3.000, 3.80, 0.00, 11.40),
  (11, 4, 3.000, 8.50, 0.00, 25.50),
  (11, 2, 2.000, 4.20, 0.00, 8.40);

INSERT INTO PAGO (ID_PAGO, ID_VENTA, ID_METODO_PAGO, MONTO, NRO_OPERACION) VALUES
  (1, 1, 1, 33.90, NULL),
  (2, 2, 4, 46.20, '58213347'),
  (3, 3, 2, 152.80, '410022'),
  (4, 4, 1, 20.80, NULL),
  (5, 5, 1, 20.00, NULL),
  (6, 5, 5, 22.30, '77120045'),
  (7, 6, 3, 37.80, '410158'),
  (8, 7, 1, 18.00, NULL),
  (9, 8, 1, 148.70, NULL),
  (10, 9, 4, 45.90, '58290011'),
  (11, 10, 1, 30.00, NULL),
  (12, 10, 2, 19.20, '410377'),
  (13, 11, 1, 33.90, NULL);

-- R4: la venta 7 no se borra, se anula. El trigger devuelve su stock al inventario.
UPDATE VENTA SET ESTADO = 'ANULADA' WHERE ID_VENTA = 7;

-- Cierre de caja: EFECTIVO_SISTEMA = monto de apertura + ventas en efectivo no anuladas.
-- DIFERENCIA = EFECTIVO_CONTADO − EFECTIVO_SISTEMA (faltante si es negativa).
UPDATE SESION_CAJA SET FECHA_CIERRE = '2026-10-20 15:05:00', EFECTIVO_SISTEMA = 254.70, EFECTIVO_CONTADO = 254.70, DIFERENCIA = 0.00, ESTADO = 'CERRADA' WHERE ID_SESION = 1;
UPDATE SESION_CAJA SET FECHA_CIERRE = '2026-10-20 15:10:00', EFECTIVO_SISTEMA = 170.00, EFECTIVO_CONTADO = 167.50, DIFERENCIA = -2.50, ESTADO = 'CERRADA' WHERE ID_SESION = 2;
UPDATE SESION_CAJA SET FECHA_CIERRE = '2026-10-21 23:02:00', EFECTIVO_SISTEMA = 378.70, EFECTIVO_CONTADO = 379.70, DIFERENCIA = 1.00, ESTADO = 'CERRADA' WHERE ID_SESION = 3;
-- La sesión 4 queda ABIERTA (turno en curso).

-- =========================================================
-- 7. INCIDENTES Y MERMAS
-- =========================================================
INSERT INTO INCIDENTE (ID_INCIDENTE, ID_PROTOCOLO, ID_ZONA, ID_REPORTA, ID_RESPONSABLE, FECHA_HORA, DESCRIPCION, HUBO_HERIDOS, MONTO_PERDIDA, NRO_DENUNCIA_PNP, ESTADO) VALUES
  (1, 3, 5, 7, 3, '2026-10-21 18:30:00', 'Persona sale sin pagar con 2 Inca Kola 1.5 L; se revisa cámara del pasillo de bebidas', 'N', 11.60, NULL, 'EN INVESTIGACION'),
  (2, 1, 4, 2, 1, '2026-10-15 10:00:00', 'Simulacro de sismo: evacuación del personal y clientes al punto de reunión en 3 minutos', 'N', 0.00, NULL, 'CERRADO');

-- VALOR_TOTAL = CANTIDAD × COSTO_UNITARIO. Quien autoriza no es quien registra (CK_MER_6). El trigger descuenta el stock.
INSERT INTO MERMA (ID_MERMA, ID_PRODUCTO, ID_LOTE, ID_ZONA, ID_REGISTRA, ID_AUTORIZA, ID_INCIDENTE, MOTIVO, FECHA, CANTIDAD, COSTO_UNITARIO, VALOR_TOTAL, ESTADO, OBSERVACION) VALUES
  (1, 3, 3, 2, 9, 2, NULL, 'VENCIMIENTO', '2026-10-19', 4.000, 5.40, 21.60, 'APROBADA', 'Lote vencido retirado de góndola'),
  (2, 6, NULL, 1, 5, 2, NULL, 'DETERIORO', '2026-10-21', 1.000, 8.90, 8.90, 'APROBADA', 'Botella rota al reponer'),
  (3, 4, NULL, 5, 7, NULL, 1, 'ROBO', '2026-10-21', 2.000, 5.80, 11.60, 'REGISTRADA', 'Faltante detectado tras el incidente');

-- Comprobación rápida: debe mostrar 11 ventas (1 anulada) y el stock de la web.
SELECT COUNT(*) AS ventas, SUM(ESTADO = 'ANULADA') AS anuladas FROM VENTA;
SELECT p.CODIGO_SKU, p.NOMBRE, i.STOCK_ACTUAL, i.STOCK_MINIMO FROM PRODUCTO p JOIN INVENTARIO i ON i.ID_PRODUCTO = p.ID_PRODUCTO ORDER BY p.ID_PRODUCTO;
