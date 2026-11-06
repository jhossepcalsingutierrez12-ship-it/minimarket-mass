<?php
declare(strict_types=1);
require_once __DIR__ . '/Repositorio.php';

/**
 * Historial de actividades (tabla HISTORIAL de database/admin.sql):
 * quién agregó un producto, cambió un precio, anuló una venta, registró una merma, etc.
 */
class HistorialRepository extends Repositorio
{
    // Acción guardada => texto que ve el administrador
    public const ACCIONES = [
        'AGREGAR_PRODUCTO'     => 'Agregó un producto',
        'CAMBIAR_PRECIO'       => 'Cambió un precio',
        'EDITAR_PRODUCTO'      => 'Editó un producto',
        'BAJA_PRODUCTO'        => 'Dio de baja un producto',
        'REACTIVAR_PRODUCTO'   => 'Reactivó un producto',
        'ANULAR_VENTA'         => 'Anuló una venta',
        'REGISTRAR_MERMA'      => 'Registró una merma',
        'AUTORIZAR_MERMA'      => 'Autorizó una merma',
        'RECIBIR_LOTE'         => 'Recibió un lote',
        'ABRIR_CAJA'           => 'Abrió una caja',
        'CERRAR_CAJA'          => 'Cerró una caja',
        'FALTANTE_CAJA'        => 'Faltó dinero en una caja',
        'REVISAR_FALTANTES'    => 'Revisó los faltantes de caja',
        'PREGUNTAR_FALTANTE'   => 'Preguntó al cajero por un faltante',
        'REPORTAR_FALTANTE'    => 'Reportó un faltante al cajero',
        'RESPONDER_FALTANTE'   => 'Respondió por un faltante',
    ];

    public function registrar(int $idEmpleado, string $accion, string $detalle): void
    {
        $this->enTransaccion(function () use ($idEmpleado, $accion, $detalle): void {
            $this->pdo->prepare('
                INSERT INTO HISTORIAL (ID_HISTORIAL, ID_EMPLEADO, FECHA_HORA, ACCION, DETALLE)
                VALUES (:id, :e, :f, :a, :d)')
                ->execute([':id' => $this->siguienteId('HISTORIAL', 'ID_HISTORIAL'), ':e' => $idEmpleado,
                           ':f' => self::ahora(), ':a' => $accion, ':d' => mb_substr($detalle, 0, 255)]);
        });
    }

    /** Filtros opcionales: accion, empleado, desde y hasta (Y-m-d). Lo más reciente primero. */
    public function listar(array $f = [], int $limite = 300): array
    {
        $donde = ['1 = 1'];
        $params = [];
        if (!empty($f['accion'])) {
            $donde[] = 'h.ACCION = :accion';
            $params[':accion'] = $f['accion'];
        }
        if (!empty($f['empleado'])) {
            $donde[] = 'h.ID_EMPLEADO = :emp';
            $params[':emp'] = (int) $f['empleado'];
        }
        if (!empty($f['desde'])) {
            $donde[] = 'h.FECHA_HORA >= :desde';
            $params[':desde'] = $f['desde'] . ' 00:00:00';
        }
        if (!empty($f['hasta'])) {
            $donde[] = 'h.FECHA_HORA <= :hasta';
            $params[':hasta'] = $f['hasta'] . ' 23:59:59';
        }
        $stmt = $this->pdo->prepare('
            SELECT h.*, e.NOMBRES, e.APELLIDOS, c.NOMBRE AS CARGO, t.NOMBRE AS TIENDA
            FROM HISTORIAL h
            JOIN EMPLEADO e ON e.ID_EMPLEADO = h.ID_EMPLEADO
            JOIN CARGO c    ON c.ID_CARGO = e.ID_CARGO
            JOIN TIENDA t   ON t.ID_TIENDA = e.ID_TIENDA
            WHERE ' . implode(' AND ', $donde) . '
            ORDER BY h.FECHA_HORA DESC, h.ID_HISTORIAL DESC
            LIMIT ' . max(1, $limite));
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Empleados que aparecen en el historial (para el filtro)
    public function empleados(): array
    {
        return $this->pdo->query('
            SELECT DISTINCT e.ID_EMPLEADO, e.NOMBRES, e.APELLIDOS
            FROM HISTORIAL h JOIN EMPLEADO e ON e.ID_EMPLEADO = h.ID_EMPLEADO
            ORDER BY e.NOMBRES')->fetchAll(PDO::FETCH_ASSOC);
    }
}

/**
 * Atajo para anotar en el historial desde cualquier controlador:
 *   Historial::anotar($usuario, 'CAMBIAR_PRECIO', 'ABA-0001: S/ 24.90 → S/ 25.50');
 * Si la tabla HISTORIAL todavía no existe (no se corrió admin.sql), no rompe la página.
 */
final class Historial
{
    private static ?HistorialRepository $repo = null;

    public static function usar(HistorialRepository $repo): void
    {
        self::$repo = $repo;
    }

    public static function anotar(Usuario $u, string $accion, string $detalle): void
    {
        if (self::$repo === null) {
            return;
        }
        try {
            self::$repo->registrar($u->getIdEmpleado(), $accion, $detalle);
        } catch (Throwable $e) {
            error_log('Historial: ' . $e->getMessage());
        }
    }
}
