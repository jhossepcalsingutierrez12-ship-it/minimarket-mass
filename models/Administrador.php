<?php
declare(strict_types=1);
require_once __DIR__ . '/Supervisor.php';

/**
 * Hereda del supervisor, pero su trabajo es CONTROLAR, no operar:
 * - ve el volumen de ventas (Reportes), no las ventas una por una;
 * - ve qué mermas hay y si pasó un robo u otro incidente (solo ver);
 * - ve si cuadra el stock del día;
 * - ve cuánto dinero tiene cada caja, pero no la cuenta ni la cierra (eso es del supervisor).
 * Además administra productos, ve el personal y las 3 tiendas.
 */
class Administrador extends Supervisor
{
    public function rol(): string { return 'Administrador'; }

    // Lo que hace el supervisor y el administrador ya no
    public function puedeVerVentas(): bool { return false; }
    public function puedeAnularVenta(): bool { return false; }
    public function puedeCerrarCaja(): bool { return false; }
    public function puedeRegistrarMerma(): bool { return false; }
    public function puedeAutorizarMerma(): bool { return false; }
    public function puedeReportarIncidente(): bool { return false; }
    public function puedeGestionarIncidentes(): bool { return false; }

    // Lo propio del administrador
    public function puedeVerDineroCajas(): bool { return true; }
    public function puedeVerCuadreStock(): bool { return true; }
    public function puedeAdministrarProductos(): bool { return true; }
    public function puedeVerPersonal(): bool { return true; }
    public function puedeVerReportes(): bool { return true; }
    public function puedeVerTodasLasTiendas(): bool { return true; }
}
