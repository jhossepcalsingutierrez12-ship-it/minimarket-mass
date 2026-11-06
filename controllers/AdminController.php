<?php
declare(strict_types=1);

/**
 * Funciones de control del Administrador: alertas de inventario e historial de actividades.
 * Los reportes del mes y el cuadre de stock están en TiendaController.
 */
class AdminController extends Controlador
{
    public function __construct(
        Usuario $usuario,
        array $tienda,
        private ReporteRepository $reportes,
        private HistorialRepository $historial
    ) {
        parent::__construct($usuario, $tienda);
    }

    // 1. Alertas: agotados, poco stock con reposición recomendada y lotes por vencer
    public function alertas(): void
    {
        $this->exigir($this->usuario->puedeVerAlertas());
        $this->vista('inventario/alertas', ['a' => $this->reportes->alertasInventario()]);
    }

    // 2. Historial de actividades
    public function historial(): void
    {
        $this->exigir($this->usuario->puedeVerHistorial());
        $accion = (string) ($_GET['tipo'] ?? '');
        $f = [
            'accion' => isset(HistorialRepository::ACCIONES[$accion]) ? $accion : '',
            'empleado' => self::entero('empleado', $_GET),
            'desde' => self::fechaGet('desde'),
            'hasta' => self::fechaGet('hasta'),
        ];
        $lista = $this->conTablas(fn() => $this->historial->listar($f));
        $this->vista('tiendas/historial', [
            'lista' => $lista ?? [], 'sinTablas' => $lista === null, 'f' => $f,
            'empleados' => $lista === null ? [] : $this->historial->empleados(),
        ]);
    }

    // Fecha Y-m-d que llega por la dirección, o '' si no es válida
    private static function fechaGet(string $campo): string
    {
        $v = (string) ($_GET[$campo] ?? '');
        $d = DateTime::createFromFormat('!Y-m-d', $v);
        return $d && $d->format('Y-m-d') === $v ? $v : '';
    }

    // Si todavía no se corrió database/admin.sql, las tablas no existen: se avisa en la página
    private function conTablas(callable $consulta): ?array
    {
        try {
            return $consulta();
        } catch (PDOException $e) {
            error_log('Tablas del administrador: ' . $e->getMessage());
            return null;
        }
    }
}
