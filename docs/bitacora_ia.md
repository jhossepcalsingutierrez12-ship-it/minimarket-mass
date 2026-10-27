# Bitácora de uso de IA · Minimarket Mass Arequipa

Grupo ILTM · SENATI CFP Arequipa · NRC 16211
Herramienta de IA usada: Claude (Anthropic)

## Reparto

| Integrante | Qué hizo | Rama |
|---|---|---|
| Ronny (Wirny) | Pasos 1, 2 y 3: modelo `Producto`, roles con herencia y `ProductoRepository` | `feature/ronny-modelo` |
| Jhossep (Gus) | Pasos 4, 5 y 6: controlador, vistas, `index.php` y páginas de prueba en `public/` | `feature/gus-vista` |
| Ronny (Wirny) | `database/datos.sql`, `database/consultas.sql` y esta bitácora | `feature/base-datos` |

---

## Ronny · pasos 1, 2 y 3 (Modelo)

### Brief usado

- **Contexto:** PHP 8.3, MVC manual sin framework; los datos van en una lista fija dentro del Repository (sin MySQL todavía).
- **Entidad:** `Producto` (tablas `PRODUCTO` + `INVENTARIO` de `database/schema.sql`).
- **Columnas:** `ID_PRODUCTO`, `CODIGO_SKU`, `NOMBRE`, `PRECIO_VENTA`, `ES_PERECIBLE`, `INVENTARIO.STOCK_ACTUAL`, `INVENTARIO.STOCK_MINIMO`.
- **Reglas de negocio:**
  - El precio ya incluye IGV 18%: IGV = precio × 18 / 118 y subtotal = precio − IGV.
  - R5: solo se vende lo que hay en inventario (`hayStock`).
  - Stock 0 = "Agotado"; stock en o bajo `STOCK_MINIMO` = "Reponer".
- **Roles (tabla `CARGO`):** Cajero, Supervisor y Administrador con herencia (`extends`).
  - R4: anular venta → Supervisor y Administrador.
  - `MERMA.ID_AUTORIZA` → Supervisor y Administrador autorizan mermas.
  - `CK_MER_6` → quien autoriza una merma no puede ser quien la registró.
  - Ver reportes → solo Administrador.
- **Seguridad:** `declare(strict_types=1)`, propiedades `private`/`protected`, sin datos reales de personas.

### Archivos

- `models/Producto.php`
- `models/Usuario.php`, `Cajero.php`, `Supervisor.php`, `Administrador.php`
- `models/ProductoRepository.php` (`obtenerTodos()` y `buscarPorSku()`, 6 productos de ejemplo)

### Cómo lo probé

- `http://localhost:8000/poo_producto.php`: Arroz 5 kg S/ 24.90 → subtotal S/ 21.10, IGV S/ 3.80; 5 unidades "Sí", 50 "No".
- `php public/poo_herencia.php`: el cajero no anula ni autoriza; supervisor y administrador sí; el supervisor no autoriza su propia merma.

### Problemas y qué corregí

- La terminal no reconocía `php`. Agregué la carpeta de PHP de Laragon al `$env:Path` de la terminal.
- Al correr `php -S` la terminal quedó ocupada y no dejaba escribir. Abrí una segunda terminal: una para el servidor y otra para los comandos.
- Subí solo la carpeta `models/` en mi rama y Gus revisó y aprobó el Pull Request, como pide la guía (nadie aprueba su propio PR).
- Lo que entendí: la clase `Producto` es como el molde de cada producto y sus datos son `private`, por eso solo se leen con los `get`. El IGV se calcula dentro de la clase para no repetir la fórmula en cada página. Con la herencia, `Usuario` tiene todo en "no" y cada rol cambia solo lo que puede hacer; por ejemplo, el `Administrador` hereda del `Supervisor` y solo le agrega ver reportes. El Repository es el único que sabe de dónde salen los productos, así que cuando conectemos MySQL solo cambiamos ese archivo.

---

## Gus · pasos 4, 5 y 6 (Controlador, Vista y punto de entrada)

### Brief usado

- **Objetivo:** `ProductoController`, las vistas `lista.php` y `detalle.php`, y `public/index.php` como único punto de entrada.
- **Entradas y salidas:** el controlador recibe `ProductoRepository` por el constructor; `listar()` muestra todos los productos y `ver($sku)` muestra uno solo, con 404 si no existe.
- **Reglas:** el controlador no tiene HTML ni SQL; la vista no hace cálculos (usa `igv()` y `estadoStock()` del modelo).
- **Seguridad:** `htmlspecialchars` en todo lo que se imprime; `switch` con `default` para aceptar solo `listar` y `ver`; el SKU se valida con `preg_match` (mayúsculas, números y guion, máximo 20).

### Archivos

- `controllers/ProductoController.php`
- `views/productos/lista.php`, `views/productos/detalle.php`
- `public/index.php`
- `public/poo_producto.php`, `public/poo_herencia.php` (pruebas de las clases de Ronny)

### Cómo lo probé

- `http://localhost:8000`: tabla con 6 productos; Yogurt en "Reponer" e Inca Kola en "Agotado".
- Clic en `LAC-0002`: detalle del Yogurt con subtotal S/ 6.69 e IGV S/ 1.21.
- `poo_producto.php` y `poo_herencia.php`: mismos resultados que en la parte de Ronny.
- `?accion=xx`: "Página no encontrada".

### Problemas y qué corregí

- Pegué el código PHP en la terminal en vez de en el archivo y PowerShell dio error. El código va en el editor; la terminal es solo para comandos.
- Hice commit sin guardar el archivo (tenía el punto ●) y se subió vacío (`0 insertions`). Lo guardé con Ctrl+S y corregí el commit con `git commit --amend --no-edit`.
- En `detalle.php` había pegado el código de la lista. Lo reemplacé por el correcto antes de subirlo.
- El archivo se llamaba `poo_pruducto.php` y Git no lo encontraba. Lo renombré con `Rename-Item`.
- Lo que entendí: el navegador siempre entra por `index.php`, que solo acepta `listar` y `ver`; si escribes otra cosa en la URL sale 404 y no se ejecuta nada raro. El controlador solo coordina: le pide los productos al Repository de Ronny y elige qué vista mostrar. La vista solo arma la tabla y todo lo que imprime pasa por `htmlspecialchars` para que nadie pueda meter código (XSS). También aprendí a revisar que el archivo esté guardado antes de hacer `git add`.

---

## Base de datos · datos.sql y consultas.sql

### Brief usado

- **Contexto:** MySQL 8 de Laragon (puerto 3307), base `minimarket_mass` creada con `schema.sql` (20 tablas, CHECK y triggers).
- **datos.sql:** datos de prueba inventados para las 20 tablas (3 tiendas en Arequipa, 10 empleados, 10 productos, 11 ventas con 1 anulada, 3 mermas, 2 incidentes), en orden de claves foráneas y cumpliendo:
  - R7: las sesiones de caja se crean abiertas y se cierran al final con `UPDATE`.
  - R5: `INVENTARIO` va antes de las ventas; el stock inicial ya considera lo que los triggers descuentan.
  - R4: la venta anulada se anula con `UPDATE`, no se borra.
  - CHECK: `TOTAL = SUBTOTAL + IGV`, `IMPORTE = CANTIDAD × PRECIO − DESCUENTO`, `DIFERENCIA = CONTADO − SISTEMA`, `VALOR_TOTAL` de merma, serie B/F según comprobante.
  - Sin datos reales de personas: DNI, RUC y teléfonos inventados.
- **consultas.sql:** 15 reportes (catálogo, reposición, ventas por día y tienda, más vendidos, métodos de pago, cuadre de caja, ventas por cajero, anuladas, mermas, lotes por vencer, personal con su jefe, clientes, incidentes) y pruebas comentadas de los triggers.

### Cómo lo probé

- Importé los tres archivos en el MySQL de Laragon (puerto 3307) en orden: primero `schema.sql`, después `datos.sql` y al final `consultas.sql`.
- Al terminar `datos.sql` revisé que salgan 11 ventas con 1 anulada, y que el stock sea Arroz 18, Leche 75, Yogurt 6, Inca Kola 0, Azúcar 60 y Aceite 30, igual que en la página web.
- En `consultas.sql` revisé la consulta de reposición (Inca Kola "Agotado" y Yogurt "Reponer") y el cuadre de caja, donde la caja de Cerro Colorado tiene un faltante de S/ 2.50.
- Probé el trigger R4 con `DELETE FROM VENTA WHERE ID_VENTA = 1;` y MySQL no dejó borrar la venta.

### Problemas y qué corregí

- La terminal estaba en `C:\Users\ALEJANDRO SUMIRE` y no dentro del repo, por eso Git decía `not a git repository`. Abrí la carpeta `minimarket-mass` con File → Open Folder y ya funcionó.
- Al importar me salió `ERROR 2003: Can't connect to MySQL server` porque Laragon estaba apagado. Lo prendí con Start All y volví a importar.
- Entendí que el orden de los INSERT importa: primero van las tablas que no dependen de otras (tiendas, cargos, categorías) y al final las ventas, pagos y mermas. También que las ventas se insertan con la caja abierta y luego se cierra con un `UPDATE`, porque el trigger R7 no deja vender con la caja cerrada.