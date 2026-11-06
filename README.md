# Minimarket Mass

Sistema web para la gestión de ventas, pagos, cajas y stock
de un minimarket con tres tiendas en Arequipa. PHP 8 (MVC + PDO) y MySQL 8.

## Estructura
- `docs/`: diagrama ER y modelo de Workbench
- `database/`: schema.sql, datos.sql, usuarios.sql, extras.sql y consultas.sql
- `models/`: clases (Producto, Usuario y sus roles) y repositorios (las consultas con PDO)
- `controllers/`: reciben lo que pide la página y deciden qué mostrar
- `views/`: las pantallas (HTML con PHP)
- `public/`: lo único que ve el navegador (`index.php`, css, js e imágenes)

## Cómo restaurar la base

Requisitos: Laragon con MySQL en el puerto **3307** y PHP 8.

1. Inicia Laragon (**Start All**).
2. Desde la carpeta del proyecto, en PowerShell, ejecuta en este orden:

   ```powershell
   mysql -u root -P 3307 -e "source database/schema.sql"
   mysql -u root -P 3307 minimarket_mass -e "source database/datos.sql"
   mysql -u root -P 3307 minimarket_mass -e "source database/usuarios.sql"
   mysql -u root -P 3307 minimarket_mass -e "source database/extras.sql"
   mysql -u root -P 3307 minimarket_mass -e "source database/ingreso.sql"
   mysql -u root -P 3307 minimarket_mass -e "source database/cuadre.sql"
   mysql -u root -P 3307 minimarket_mass -e "source database/tiendas.sql"
   mysql -u root -P 3307 minimarket_mass -e "source database/cajas.sql"
   mysql -u root -P 3307 minimarket_mass -e "source database/cajeros.sql"
   mysql -u root -P 3307 --default-character-set=utf8mb4 minimarket_mass -e "source database/admin.sql"
   mysql -u root -P 3307 --default-character-set=utf8mb4 minimarket_mass -e "source database/productos_nuevos.sql"
   mysql -u root -P 3307 --default-character-set=utf8mb4 minimarket_mass -e "source database/ejemplos_tiendas.sql"
   ```

   - `schema.sql` crea la base `minimarket_mass` y sus 20 tablas.
   - `datos.sql` carga los datos de prueba.
   - `usuarios.sql` agrega usuario y contraseña (hash) a 3 empleados.
   - `extras.sql` agrega el efectivo recibido y el vuelto en la tabla PAGO.
   - `ingreso.sql` agrega el correo de cada empleado (ahí llega el código de ingreso).
   - `tiendas.sql` deja las 3 tiendas: Cayma, Cerro Colorado y Yanahuara.
   - `cajas.sql` deja 3 cajas activas en cada tienda.
   - `cajeros.sql` agrega un segundo cajero (`jccama`) para probar 2 cajas abiertas.
   - `productos_nuevos.sql` agrega las marcas y subcategorías de los 9 productos nuevos, baja el precio del arroz y deja una compra de ejemplo.
   - `ejemplos_tiendas.sql` carga ventas, cierres de caja, una merma y actividades de ejemplo en las 3 tiendas (2 al 5 de noviembre).
   - `admin.sql` crea PROVEEDOR, COMPRA, DETALLE_COMPRA e HISTORIAL (proveedores, compras de mercadería e historial de actividades).
   - `cuadre.sql` agrega a LOTE la fecha en que llegó la mercadería (para el cuadre de stock del día).
     **Antes de correrlo cambia los 3 correos** que trae por correos reales.
   - `consultas.sql` tiene consultas de ejemplo (opcional).

> ⚠️ `schema.sql` borra las tablas si ya existen, así que se pierden los datos que tengas.
> Si ya tenías la base, corre solo los archivos que te falten (cada uno una sola vez).
> En casa MySQL puede estar en el puerto 3306: usa `-P 3306` y antes de `php -S` escribe `$env:DB_PORT="3306"`.

## Cómo ejecutar la web

```powershell
php -S localhost:8000 -t public
```

Luego abre http://localhost:8000 en el navegador.

## Usuarios de prueba (inventados)

| Usuario | Contraseña | Rol |
|---|---|---|
| `jmamani` | `Admin#2026` | Administrador: controla (alertas de inventario, reportes con filtros, cierres de caja, mermas, incidentes, cuadre de stock, dinero en cajas, historial), proveedores y compras, productos y personal |
| `chuaman` | `Super#2026` | Supervisor: cierra las cajas, anula ventas, lotes, autoriza mermas |
| `rquispe` | `Cajero#2026` | Cajero: abre su caja, vende, registra mermas e incidentes |
| `jccama` | `Jorge#2026` | Cajero (Jorge Ccama): igual que rquispe, para abrir una segunda caja |

## Ingreso en dos pasos

1. Usuario, contraseña y **verifica que eres humano**: girar el logo de Mass hasta que quede
   derecho (la imagen girada la arma PHP con la extensión gd). 3 intentos y se bloquea 30 segundos.
2. Código de 6 dígitos que llega al **correo** del empleado (vence en 5 minutos, 3 intentos).

Para que el correo se envíe de verdad:

1. Copia `config/correo.ejemplo.php` como `config/correo.php`.
2. Pon tu Gmail y una **contraseña de aplicación** (Cuenta de Google → Seguridad →
   Verificación en 2 pasos → Contraseñas de aplicaciones). No es tu contraseña normal.
3. `config/correo.php` está en `.gitignore`: la contraseña no se sube a GitHub.

Si `config/correo.php` no existe, el código se muestra en pantalla (modo simulado).

## Qué hace cada rol

| Sección | Cajero | Supervisor | Administrador |
|---|---|---|---|
| Catálogo | ✔ | ✔ | ✔ |
| Abrir caja y vender (nueva venta) | ✔ | | |
| Lista de ventas y comprobantes | sus ventas | su tienda | (solo el volumen en Reportes) |
| Cerrar caja y anular ventas (R4) | | ✔ | |
| Dinero en cada caja abierta | | ✔ (al cerrar) | ✔ (solo ver) |
| Lotes y vencimientos | | ✔ | ✔ |
| Mermas | registra | registra y autoriza | ve las de las 3 tiendas |
| Incidentes (robos, sismos…) | reporta | reporta y da seguimiento | ve los de las 3 tiendas |
| Cuadre de stock del día | | | ✔ |
| Productos (agregar, editar, baja), PDF | | | ✔ |
| Tiendas y personal | | | ✔ |
| Alertas de inventario (agotados, poco stock, reposición recomendada, por vencer) | | | ✔ |
| Reportes con filtros (día, semana, mes, tiendas, ganancias, menor movimiento) | | | ✔ |
| Revisar cierres de caja (debía haber, contado, diferencia, quién cerró) | | cierra | ✔ (solo ver) |
| Proveedores y compras (la compra crea lotes y sube el stock) | | | ✔ |
| Historial de actividades (quién agregó, cambió precios, anuló, registró mermas…) | | | ✔ |
