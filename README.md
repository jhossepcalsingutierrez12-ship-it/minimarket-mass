# Minimarket Mass

Sistema de base de datos para la gestión de ventas, pagos, cajas y stock
de un minimarket con tres tiendas en Arequipa.

## Estructura
- `docs/`: diagrama ER y modelo de Workbench
- `database/`: schema.sql, datos.sql y consultas.sql

## Cómo restaurar la base

Requisitos: Laragon con MySQL en el puerto **3307** y PHP 8.

1. Inicia Laragon (**Start All**).
2. Desde la carpeta del proyecto, en PowerShell, ejecuta en este orden:

   ```powershell
   mysql -u root -P 3307 -e "source database/schema.sql"
   mysql -u root -P 3307 minimarket_mass -e "source database/datos.sql"
   ```

   - `schema.sql` crea la base `minimarket_mass` y sus 20 tablas.
   - `datos.sql` carga los datos de prueba.
   - `consultas.sql` tiene consultas de ejemplo (opcional).

> ⚠️ `schema.sql` borra las tablas si ya existen, así que se pierden los datos que tengas.

## Cómo ejecutar la web

```powershell
php -S localhost:8000 -t public
```

Luego abre http://localhost:8000 en el navegador.