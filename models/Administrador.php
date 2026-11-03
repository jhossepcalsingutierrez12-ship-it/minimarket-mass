<?php
declare(strict_types=1);
require_once __DIR__ . '/Supervisor.php';

// Hereda lo del supervisor y además administra productos, ve personal, reportes y las 3 tiendas
class Administrador extends Supervisor
{
    public function rol(): string { return 'Administrador'; }

    public function puedeAdministrarProductos(): bool { return true; }

    public function puedeVerPersonal(): bool { return true; }

    public function puedeVerReportes(): bool { return true; }

    public function puedeVerTodasLasTiendas(): bool { return true; }
}
