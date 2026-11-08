# Bitácora de uso de IA · Minimarket Mass Arequipa

Grupo ILTM · SENATI CFP Arequipa · NRC 16211
Herramienta de IA usada: Claude (Anthropic)

## Reparto

| Integrante | Qué hizo | Rama |
|---|---|---|
| Ronny (Wirny) | Pasos 1, 2 y 3: modelo `Producto`, roles con herencia y `ProductoRepository` | `feature/ronny-modelo` |
| Jhossep (Gus) | Pasos 4, 5 y 6: controlador, vistas, `index.php` y páginas de prueba en `public/` | `feature/gus-vista` |
| Ronny (Wirny) | `database/datos.sql`, `database/consultas.sql` y esta bitácora | `feature/base-datos` |
| Ronny (Wirny) | Conexión PDO, login seguro, README y panel | `feat/conexion-pdo`, `feat/login-seguro`, `docs/readme-login`, `feat/panel` |
| Ronny (Wirny) | Sistema completo: caja, ventas, inventario, reportes, administrador, faltantes de caja y mejora visual | `feat/sistema-completo` |
| Jhossep (Gus) | Ideas para las pantallas y las funciones, imágenes de productos y categorías, revisión de los Pull Requests | `feature/gus-vista` y revisiones |

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

---

## Ronny · sesiones 5 y 6 (PDO, login y panel)

### Brief usado

- **PDO:** cambiar la lista fija de `ProductoRepository` por consultas a MySQL con PDO, sin tocar el controlador ni las vistas. Conexión única en `config/conexion.php`, `ERRMODE_EXCEPTION`, `EMULATE_PREPARES = false` y puerto 3307.
- **Login:** columnas `USUARIO` y `CLAVE_HASH` en `EMPLEADO` (`database/usuarios.sql`), `password_verify`, `session_regenerate_id`, bloqueo después de 3 intentos y el rol leído desde `CARGO`.
- **Panel:** menú lateral que muestra solo lo que el rol puede usar, con el mismo diseño de la página de demostración (amarillo y negro de Mass).

### Cómo lo probé

- El catálogo mostró los productos de la base y no los de la lista fija; al apagar Laragon salió el mensaje "No se pudo conectar a la base de datos" en vez del error de PHP.
- Entré con `jmamani`, `chuaman` y `rquispe` y cada uno vio un menú distinto; con 3 claves malas el botón quedó bloqueado.

### Problemas y qué corregí

- En casa MySQL estaba en el puerto 3306 y en el aula en 3307. Agregué la variable `DB_PORT` para no cambiar el código cada vez.
- Lo que entendí: con PDO solo cambié el Repository y el resto del sistema siguió igual, como decía el MVC. Las consultas preparadas mandan los datos aparte del SQL, por eso no se puede meter código en el usuario.

---

## Sistema completo (sesiones 7 y 8)

Ronny armó el sistema con Claude y Gus aportó ideas para las pantallas y las funciones, agregó las imágenes de los productos y categorías y revisó los Pull Requests.

### Brief usado

- **Contexto:** el mismo MVC sin framework, MySQL de Laragon, datos inventados y todo en PHP (el HTML solo como maqueta para mostrar la idea).
- **Reglas que se le dieron a la IA:**
  - R7: sin caja abierta no hay venta. El cajero, al entrar, primero abre su caja (elige tienda, una de sus 3 cajas, turno y monto).
  - Solo el cajero abre caja y vende; el supervisor cuenta el efectivo y cierra; el administrador solo revisa el dinero de las cajas, no abre ni cierra.
  - El administrador ve el volumen de ventas, mermas, incidentes, el cuadre de stock del día, alertas, reportes del mes por tienda y el historial; no ve las ventas una por una.
  - 3 tiendas: Cayma, Cerro Colorado y Yanahuara.
  - Cuando falte dinero al cerrar una caja, al administrador le llega un aviso al momento y puede preguntar o reportar al cajero; el cajero responde.
- **Seguridad:** token CSRF en todos los formularios, `htmlspecialchars` en toda salida, el rol se vuelve a leer de la base en cada página, contraseñas y claves fuera de GitHub (`config/correo.php` en `.gitignore`).

### Qué se hizo

- Caja (abrir, efectivo del sistema, cerrar con cuadre), nueva venta con boleta/factura, cobro con vuelto, comprobante con QR y anulación.
- Lotes y vencimientos, mermas con autorización, incidentes, cuadre de stock, alertas de inventario, reportes por mes y tienda, historial de actividades.
- Ingreso en dos pasos: girar el logo de Mass (captcha) y código de 6 dígitos al correo.
- Faltantes de caja: campana y aviso al administrador, correo, y preguntas y respuestas con el cajero (`database/consultas_caja.sql`).
- Mejora visual: avisos flotantes, un solo juego de íconos, menú hamburguesa en el celular, botones de 44 px y catálogo con orden y filtros.
- Datos de ejemplo en las 3 tiendas (`ejemplos_tiendas.sql`) para que los reportes y el historial no salgan vacíos.

### Cómo lo probé

- Entré con cada rol y revisé que el menú y los permisos fueran los correctos: el supervisor no entra a Reportes y el cajero sin caja solo ve "Abre tu caja".
- Hice una venta de 2 Arroz, 1 Aceite y 3 Leche pagando S/ 70: total S/ 64.30, IGV S/ 9.81 y vuelto S/ 5.70.
- Cerré una caja contando menos dinero y al administrador le llegó el aviso sin recargar la página; le pregunté al cajero y respondió desde su cuenta.
- Pruebas de seguridad: inyección SQL en el login, entrar sin sesión, rol incorrecto, formulario sin token y un `<script>` en un incidente. Ninguna pasó.

### Problemas y qué corregí

- Corrí un script SQL sin `--default-character-set=utf8mb4` y las tildes salieron como "Ã¡". Agregué `SET NAMES utf8mb4` a los scripts y la opción al ejecutarlos.
- La contraseña de aplicación de Gmail se vio en una captura que compartí. La revoqué, generé otra y dejé `config/correo.php` fuera de GitHub.
- Se probó un asistente con Gemini, pero la clave de la API quedaba en el código; se quitó (commit "quitar asistente IA").
- El arroz bajó a S/ 19.90 y en Reportes salía ganancia negativa porque el lote costaba S/ 19.80. Corregí el costo del lote.
- Un cajero de ejemplo tenía un DNI repetido y MySQL lo rechazó por `UQ_EMP_1`. Le puse otro DNI.
- Un aviso de PHP decía "Undefined variable $mensaje»": PHP tomaba la comilla » como parte del nombre. Se escribió `{$mensaje}`.

### Qué rechazamos o cambiamos de lo que propuso la IA

- **Proveedores y compras:** la IA los agregó, pero no están en nuestro modelo de base de datos. Se quitaron del código y de la base.
- **El administrador cerraba cajas:** lo cambiamos; el administrador solo revisa el dinero y el que cierra es el supervisor.
- **Botón "Ahora no" al abrir caja:** dejaba al cajero entrar sin caja abierta, contra R7. Se quitó.
- **Reportes por día y por semana:** pedimos solo por mes y por tienda.
- **Cambios en HTML:** pedimos que todo se haga en las vistas PHP y el HTML solo como maqueta.

---

## Reflexión final

### Ronny

Lo que más nos costó fue la base de datos y el orden de las cosas: los triggers no dejaban vender con la caja cerrada, los CHECK rechazaban datos por un decimal y un DNI repetido nos tumbó un script entero. También sufrimos con Laragon: el puerto 3306 en casa y 3307 en el aula, la terminal que no reconocía `php` y las tildes que salían mal. Hubo días en que cambiamos varias veces cómo funcionaba la caja hasta dejarla bien: primero cualquiera cerraba, luego solo el supervisor, luego el cajero tenía que abrir antes de todo.

En lo que mejoré: ahora pienso primero en las reglas del negocio y después en el código, y reviso que cada regla esté en PHP y en MySQL. Aprendí a no confiar en todo lo que propone la IA: si algo no está en el modelo (como proveedores) o rompe una regla (como el botón "Ahora no"), se quita. También aprendí a cuidar las claves: nunca más una contraseña en una captura ni en GitHub. Y a trabajar con ramas y Pull Requests sin pisar el trabajo de Gus.

### Gus

Lo que más me costó fue Git y la terminal al inicio: subí un commit vacío por no guardar el archivo, pegué código en PowerShell en vez del editor y un archivo mal escrito (`poo_pruducto.php`) no se encontraba. También me costó seguir el ritmo cuando el sistema creció tanto en pocos días.

En lo que mejoré: ahora reviso que el archivo esté guardado antes de `git add`, uso ramas y reviso los Pull Requests de mi compañero antes de aprobarlos. Aporté ideas para las pantallas y las funciones del sistema, y agregué las imágenes de los productos y categorías para que el catálogo se vea como una tienda de verdad. Entendí que el controlador solo coordina, que la vista solo muestra y que todo lo que se imprime pasa por `htmlspecialchars`.
