<?php
declare(strict_types=1);
require_once __DIR__ . '/Usuario.php';

// SESION_CAJA.ID_CAJERO: abre caja y vende; no anula ni autoriza
class Cajero extends Usuario
{
    public function rol(): string { return 'Cajero'; }
}
