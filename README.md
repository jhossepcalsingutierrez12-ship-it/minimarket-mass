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
   ```

   - `schema.sql` crea la base `minimarket_mass` y sus 20 tablas.
   - `datos.sql` carga los datos de prueba.
   - `usuarios.sql` agrega usuario y contraseña (hash) a 3 empleados.
   - `extras.sql` agrega el efectivo recibido y el vuelto en la tabla PAGO.
   - `ingreso.sql` agrega el correo de cada empleado (ahí llega el código de ingreso).
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
| `jmamani` | `Admin#2026` | Administrador: todo, incluidos productos, personal y reportes |
| `chuaman` | `Super#2026` | Supervisor: cierra cajas, anula ventas, lotes, autoriza mermas |
| `rquispe` | `Cajero#2026` | Cajero: abre su caja, vende, registra mermas e incidentes |

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
| Catálogo, caja, nueva venta, ventas | ✔ (sus cajas) | ✔ (su tienda) | ✔ (3 tiendas) |
| Cerrar caja y anular ventas (R4) | | ✔ | ✔ |
| Lotes y vencimientos | | ✔ | ✔ |
| Mermas | registra | registra y autoriza | registra y autoriza |
| Incidentes | reporta | reporta y da seguimiento | reporta y da seguimiento |
| Agregar / editar / dar de baja productos, PDF | | | ✔ |
| Tiendas y personal, reportes | | | ✔ |
