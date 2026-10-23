<?php
declare(strict_types=1);

/**
 * Clase base de los roles. En schema.sql el usuario es un EMPLEADO
 * y su rol sale de CARGO (ID_CARGO).
 */
class Usuario
{
    public function __construct(
        protected int $idEmpleado,
        protected string $nombres,
        protected string $apellidos
    ) {}

    public function getIdEmpleado(): int { return $this->idEmpleado; }
    public function getNombreCompleto(): string { return $this->nombres . ' ' . $this->apellidos; }

    public function rol(): string { return 'Empleado'; }

    // R4: las ventas no se borran, se anulan; solo algunos cargos pueden anular
    public function puedeAnularVenta(): bool { return false; }

    // MERMA.ID_AUTORIZA: alguien con permiso aprueba la merma
    public function puedeAutorizarMerma(): bool { return false; }

    public function puedeVerReportes(): bool { return false; }

    // CK_MER_6: quien autoriza una merma no puede ser quien la registró
    public function autorizaMermaDe(int $idRegistra): bool
    {
        return $this->puedeAutorizarMerma() && $idRegistra !== $this->idEmpleado;
    }
}
