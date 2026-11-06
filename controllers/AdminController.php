<?php
declare(strict_types=1);

/**
 * Funciones de control del Administrador: alertas de inventario, faltantes de caja e historial de actividades.
 * Los reportes del mes y el cuadre de stock están en TiendaController.
 */
class AdminController extends Controlador
{
    public function __construct(
        Usuario $usuario,
        array $tienda,
        private ReporteRepository $reportes,
        private HistorialRepository $historial,
        private NotificacionRepository $notificaciones
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

    // 3. Faltantes de caja: cajas que se cerraron con menos efectivo del que dice el sistema
    public function faltantes(): void
    {
        $this->exigir($this->usuario->puedeVerDineroCajas());
        $nuevos = $this->notificaciones->nuevos($this->usuario->getIdEmpleado());
        $lista = $this->notificaciones->faltantes();
        // Al abrir esta página quedan como vistos (la campana vuelve a 0)
        if ($nuevos['n'] > 0) {
            Historial::anotar($this->usuario, 'REVISAR_FALTANTES', 'Revisó ' . $nuevos['n'] . ' faltante(s) de caja · total ' . sol($nuevos['total']));
        }
        $this->vista('tiendas/faltantes', [
            'lista' => $lista,
            'desde' => $nuevos['desde'],
            'nuevos' => $nuevos['n'],
            'total' => -array_sum(array_map(fn($f) => (float) $f['DIFERENCIA'], $lista)),
        ]);
    }

    // La campana pregunta cada 30 segundos si hay faltantes nuevos (responde JSON)
    public function faltantesNuevos(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        if (!$this->usuario->puedeVerDineroCajas()) {
            http_response_code(403);
            echo json_encode(['n' => 0]);
            return;
        }
        $n = $this->notificaciones->nuevos($this->usuario->getIdEmpleado());
        echo json_encode([
            'n' => $n['n'],
            'texto' => $n['ultimo'] ? NotificacionRepository::texto($n['ultimo']) : '',
            'sesion' => $n['ultimo'] ? (int) $n['ultimo']['ID_SESION'] : 0,
        ], JSON_UNESCAPED_UNICODE);
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
