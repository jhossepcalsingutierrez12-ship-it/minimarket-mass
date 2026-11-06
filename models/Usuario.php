<?php
declare(strict_types=1);

/**
 * Clase base de los roles. En schema.sql el usuario es un EMPLEADO
 * y su rol sale de CARGO (ID_CARGO). Cada rol hijo solo cambia lo que puede hacer.
 */
class Usuario
{
    public function __construct(
        protected int $idEmpleado,
        protected string $nombres,
        protected string $apellidos,
        protected int $idTienda = 1,
        protected ?int $idSupervisor = null
    ) {}

    public function getIdEmpleado(): int { return $this->idEmpleado; }
    public function getNombreCompleto(): string { return $this->nombres . ' ' . $this->apellidos; }
    public function getIdTienda(): int { return $this->idTienda; }
    public function getIdSupervisor(): ?int { return $this->idSupervisor; }

    public function rol(): string { return 'Empleado'; }

    // SESION_CAJA.ID_CAJERO: solo el cajero abre su caja y vende (R7: sin caja abierta no hay venta)
    public function puedeAbrirCaja(): bool { return false; }

    // SESION_CAJA: el supervisor cuenta el efectivo y cierra la caja
    public function puedeCerrarCaja(): bool { return false; }

    // VENTA: ver la lista de ventas y sus comprobantes (el administrador solo ve el volumen en Reportes)
    public function puedeVerVentas(): bool { return true; }

    // Ver cuánto dinero tiene cada caja abierta, sin contarla ni cerrarla (administrador)
    public function puedeVerDineroCajas(): bool { return false; }

    // R4: las ventas no se borran, se anulan; solo algunos cargos pueden anular
    public function puedeAnularVenta(): bool { return false; }

    // MERMA.ID_REGISTRA: registrar productos vencidos, dañados o robados
    public function puedeRegistrarMerma(): bool { return true; }

    // MERMA.ID_AUTORIZA: alguien con permiso aprueba la merma
    public function puedeAutorizarMerma(): bool { return false; }

    // LOTE: recibir mercadería y ver vencimientos
    public function puedeVerLotes(): bool { return false; }

    // INCIDENTE.ID_REPORTA: reportar un robo, sismo, incendio...
    public function puedeReportarIncidente(): bool { return true; }

    // INCIDENTE: darle seguimiento (pasar a investigación o cerrar)
    public function puedeGestionarIncidentes(): bool { return false; }

    // PRODUCTO: agregar, editar, dar de baja y sacar el catálogo en PDF
    public function puedeAdministrarProductos(): bool { return false; }

    public function puedeVerPersonal(): bool { return false; }

    public function puedeVerReportes(): bool { return false; }

    // Cuadre de stock del día: entradas, salidas y conteo físico por producto
    public function puedeVerCuadreStock(): bool { return false; }

    // El administrador ve las 3 tiendas; los demás solo la suya
    public function puedeVerTodasLasTiendas(): bool { return false; }

    // Funciones de control del administrador
    public function puedeVerAlertas(): bool { return false; }          // alertas de inventario
    public function puedeVerHistorial(): bool { return false; }        // historial de actividades

    // CK_MER_6: quien autoriza una merma no puede ser quien la registró
    public function autorizaMermaDe(int $idRegistra): bool
    {
        return $this->puedeAutorizarMerma() && $idRegistra !== $this->idEmpleado;
    }
}
