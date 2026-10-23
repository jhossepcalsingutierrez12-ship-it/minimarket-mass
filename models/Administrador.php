<?php
declare(strict_types=1);
require_once __DIR__ . '/Supervisor.php';

// Hereda lo del supervisor y además ve los reportes
class Administrador extends Supervisor
{
    public function rol(): string { return 'Administrador'; }

    public function puedeVerReportes(): bool { return true; }
}
