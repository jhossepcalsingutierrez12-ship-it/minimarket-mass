<?php
declare(strict_types=1);
require_once __DIR__ . '/Usuario.php';

// SESION_CAJA.ID_SUPERVISOR y MERMA.ID_AUTORIZA
class Supervisor extends Usuario
{
    public function rol(): string { return 'Supervisor'; }

    public function puedeCerrarCaja(): bool { return true; }

    public function puedeAnularVenta(): bool { return true; }

    public function puedeAutorizarMerma(): bool { return true; }

    public function puedeVerLotes(): bool { return true; }

    public function puedeGestionarIncidentes(): bool { return true; }
}
