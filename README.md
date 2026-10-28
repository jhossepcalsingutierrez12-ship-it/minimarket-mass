# Minimarket Mass

Sistema de base de datos para la gestión de ventas, pagos, cajas y stock
de un minimarket con tres tiendas en Arequipa.

## Estructura
- `docs/`: diagrama ER y bitácora de IA
- `database/`: schema.sql, datos.sql, usuarios.sql y consultas.sql
- `config/`: conexión a MySQL
- `models/`, `controllers/`, `views/`: el sistema en PHP (MVC)
- `public/`: punto de entrada (`index.php`) y página de demostración (`mass.html`)

## Cómo instalar la base

Requisitos: Laragon con MySQL y PHP 8.

1. Inicia Laragon (**Start All**).
2. Desde la carpeta del proyecto, en PowerShell, ejecuta en este orden:

   ```powershell
   mysql -u root -P 3307 -e "source database/schema.sql"
   mysql -u root -P 3307 minimarket_mass -e "source database/datos.sql"
   mysql -u root -P 3307 -e "source database/usuarios.sql"
   ```

   - `schema.sql` crea la base `minimarket_mass` y sus 20 tablas.
   - `datos.sql` carga los datos de prueba.
   - `usuarios.sql` agrega usuario y contraseña cifrada a 3 empleados (correrlo una sola vez).
   - `consultas.sql` tiene consultas de ejemplo (opcional).

> ⚠️ `schema.sql` borra las tablas si ya existen. Si lo vuelves a correr, corre también `datos.sql` y `usuarios.sql`.

> En el aula MySQL usa el puerto **3307**. Si en tu PC usa **3306**, cambia `-P 3307` por `-P 3306`.

## Cómo ejecutar la web

```powershell
php -S localhost:8000 -t public
```

Si tu MySQL usa el puerto 3306, antes escribe: `$env:DB_PORT = "3306"`

Luego abre http://localhost:8000

## Usuarios de prueba

| Usuario | Contraseña | Rol |
|---|---|---|
| rquispe | Cajero#2026 | Cajero |
| chuaman | Super#2026 | Supervisor |
| jmamani | Admin#2026 | Administrador |

Después de 3 intentos fallidos, el login se bloquea 30 segundos.