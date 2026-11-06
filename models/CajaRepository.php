<?php
declare(strict_types=1);
require_once __DIR__ . '/Repositorio.php';

/**
 * Cajas y sesiones de caja. Tablas: CAJA, SESION_CAJA, TURNO.
 * R7: solo se vende con una sesión ABIERTA (lo vigila el trigger TRG_VENTA_SESION_ABIERTA).
 */
class CajaRepository extends Repositorio
{
    // Regla de la tienda: de sus 3 cajas, como máximo 2 pueden estar abiertas a la vez
    public const MAX_ABIERTAS_POR_TIENDA = 2;

    private const SELECT_SESION = '
        SELECT s.*, c.NUMERO_CAJA, c.ID_TIENDA, t.NOMBRE AS TIENDA, t.DIRECCION, t.DISTRITO, tu.NOMBRE AS TURNO,
               e.NOMBRES AS CAJERO_NOMBRES, e.APELLIDOS AS CAJERO_APELLIDOS
        FROM SESION_CAJA s
        JOIN CAJA c     ON c.ID_CAJA = s.ID_CAJA
        JOIN TIENDA t   ON t.ID_TIENDA = c.ID_TIENDA
        JOIN TURNO tu   ON tu.ID_TURNO = s.ID_TURNO
        JOIN EMPLEADO e ON e.ID_EMPLEADO = s.ID_CAJERO';

    /** @return array<int, array{NOMBRE: string, HORA_INICIO: string, HORA_FIN: string}> */
    public function turnos(): array
    {
        $r = [];
        foreach ($this->pdo->query('SELECT * FROM TURNO ORDER BY ID_TURNO') as $f) {
            $r[(int) $f['ID_TURNO']] = $f;
        }
        return $r;
    }

    public function sesionAbiertaDe(int $idCajero): ?array
    {
        $stmt = $this->pdo->prepare(self::SELECT_SESION . " WHERE s.ID_CAJERO = :e AND s.ESTADO = 'ABIERTA'");
        $stmt->execute([':e' => $idCajero]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function sesion(int $idSesion): ?array
    {
        $stmt = $this->pdo->prepare(self::SELECT_SESION . ' WHERE s.ID_SESION = :id');
        $stmt->execute([':id' => $idSesion]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Las cajas de las 3 tiendas con su lugar y cómo están ahora:
     * LIBRE, ABIERTA (con el nombre del cajero) o FUERA DE SERVICIO (CAJA.ESTADO = 'I').
     */
    public function cajasConEstado(): array
    {
        return $this->pdo->query("
            SELECT c.ID_CAJA, c.NUMERO_CAJA, c.ESTADO AS ESTADO_CAJA, c.ID_TIENDA,
                   t.NOMBRE AS TIENDA, t.DIRECCION, t.DISTRITO,
                   (SELECT z.NOMBRE FROM ZONA_TIENDA z WHERE z.ID_TIENDA = c.ID_TIENDA AND z.TIPO = 'CAJA' LIMIT 1) AS ZONA,
                   s.ID_SESION, e.NOMBRES AS CAJERO_NOMBRES, e.APELLIDOS AS CAJERO_APELLIDOS
            FROM CAJA c
            JOIN TIENDA t ON t.ID_TIENDA = c.ID_TIENDA
            LEFT JOIN SESION_CAJA s ON s.ID_CAJA = c.ID_CAJA AND s.ESTADO = 'ABIERTA'
            LEFT JOIN EMPLEADO e ON e.ID_EMPLEADO = s.ID_CAJERO
            ORDER BY c.ID_TIENDA, c.NUMERO_CAJA")->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int, int> [ID_TIENDA => cajas abiertas ahora] */
    public function abiertasPorTienda(): array
    {
        $r = [];
        foreach ($this->cajasConEstado() as $c) {
            $r[(int) $c['ID_TIENDA']] = ($r[(int) $c['ID_TIENDA']] ?? 0) + ($c['ID_SESION'] !== null ? 1 : 0);
        }
        return $r;
    }

    // Cajas que se pueden abrir: activas, sin sesión y de una tienda que aún no tiene 2 abiertas
    public function cajasLibres(): array
    {
        $abiertas = $this->abiertasPorTienda();
        return array_values(array_filter($this->cajasConEstado(), fn($c) => $c['ESTADO_CAJA'] === 'A' && $c['ID_SESION'] === null
            && ($abiertas[(int) $c['ID_TIENDA']] ?? 0) < self::MAX_ABIERTAS_POR_TIENDA));
    }

    /**
     * Sesiones que el usuario puede ver: todas (admin), las de su tienda (supervisor)
     * o solo las suyas (cajero). $soloAbiertas filtra las que siguen abiertas.
     */
    public function sesiones(Usuario $u, bool $soloAbiertas = false): array
    {
        [$donde, $params] = self::filtro($u);
        if ($soloAbiertas) {
            $donde .= " AND s.ESTADO = 'ABIERTA'";
        }
        $stmt = $this->pdo->prepare(self::SELECT_SESION . " WHERE $donde ORDER BY s.ID_SESION DESC");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function abrir(int $idCaja, Usuario $cajero, int $idTurno, float $monto): int
    {
        return $this->enTransaccion(function () use ($idCaja, $cajero, $idTurno, $monto): int {
            $libre = array_filter($this->cajasLibres(), fn($c) => (int) $c['ID_CAJA'] === $idCaja);
            if (!$libre) {
                throw new DomainException('No se puede abrir esa caja: ya está abierta, está fuera de servicio o su tienda ya tiene '
                    . self::MAX_ABIERTAS_POR_TIENDA . ' cajas abiertas (máximo).');
            }
            if ($this->sesionAbiertaDe($cajero->getIdEmpleado()) !== null) {
                throw new DomainException('Ya tienes una caja abierta.');
            }
            $id = $this->siguienteId('SESION_CAJA', 'ID_SESION');
            $stmt = $this->pdo->prepare("
                INSERT INTO SESION_CAJA (ID_SESION, ID_CAJA, ID_CAJERO, ID_TURNO, ID_SUPERVISOR, FECHA_APERTURA, MONTO_APERTURA, ESTADO)
                VALUES (:id, :caja, :cajero, :turno, :sup, :fecha, :monto, 'ABIERTA')");
            $stmt->execute([
                ':id' => $id, ':caja' => $idCaja, ':cajero' => $cajero->getIdEmpleado(), ':turno' => $idTurno,
                ':sup' => $cajero->getIdSupervisor(), ':fecha' => self::ahora(), ':monto' => self::dinero($monto),
            ]);
            return $id;
        });
    }

    // Efectivo que debería haber: monto de apertura + pagos en efectivo de ventas EMITIDAS
    public function efectivoSistema(int $idSesion): float
    {
        $stmt = $this->pdo->prepare("
            SELECT s.MONTO_APERTURA + COALESCE((
                SELECT SUM(p.MONTO) FROM PAGO p
                JOIN VENTA v ON v.ID_VENTA = p.ID_VENTA
                JOIN METODO_PAGO m ON m.ID_METODO_PAGO = p.ID_METODO_PAGO
                WHERE v.ID_SESION = s.ID_SESION AND v.ESTADO = 'EMITIDA' AND m.NOMBRE = 'Efectivo'), 0)
            FROM SESION_CAJA s WHERE s.ID_SESION = :id");
        $stmt->execute([':id' => $idSesion]);
        return round((float) $stmt->fetchColumn(), 2);
    }

    /** @return array{cantidad: int, total: float} ventas emitidas de la sesión */
    public function resumenVentas(int $idSesion): array
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) AS N, COALESCE(SUM(TOTAL), 0) AS T FROM VENTA WHERE ID_SESION = :id AND ESTADO = 'EMITIDA'");
        $stmt->execute([':id' => $idSesion]);
        $f = $stmt->fetch(PDO::FETCH_ASSOC);
        return ['cantidad' => (int) $f['N'], 'total' => (float) $f['T']];
    }

    /** Cierra la sesión y guarda el cuadre. Devuelve [sistema, contado, diferencia]. */
    public function cerrar(int $idSesion, float $contado, Usuario $supervisor): array
    {
        return $this->enTransaccion(function () use ($idSesion, $contado, $supervisor): array {
            $s = $this->sesion($idSesion);
            if ($s === null || $s['ESTADO'] !== 'ABIERTA') {
                throw new DomainException('Esa sesión ya está cerrada.');
            }
            if (!$supervisor->puedeVerTodasLasTiendas() && (int) $s['ID_TIENDA'] !== $supervisor->getIdTienda()) {
                throw new DomainException('Esa caja no es de tu tienda.');
            }
            $sistema = $this->efectivoSistema($idSesion);
            $dif = round($contado - $sistema, 2);   // CK_SES_5: DIFERENCIA = CONTADO - SISTEMA
            // CK_SES_3: el cierre debe ser después de la apertura
            $cierre = max(self::ahora(), date('Y-m-d H:i:s', strtotime((string) $s['FECHA_APERTURA']) + 1));
            $stmt = $this->pdo->prepare("
                UPDATE SESION_CAJA SET FECHA_CIERRE = :cierre, EFECTIVO_SISTEMA = :sis, EFECTIVO_CONTADO = :con,
                       DIFERENCIA = :dif, ESTADO = 'CERRADA', ID_SUPERVISOR = :sup
                WHERE ID_SESION = :id");
            $stmt->execute([
                ':cierre' => $cierre, ':sis' => self::dinero($sistema), ':con' => self::dinero($contado),
                ':dif' => self::dinero($dif), ':sup' => $supervisor->getIdEmpleado(), ':id' => $idSesion,
            ]);
            return [$sistema, round($contado, 2), $dif];
        });
    }

    /** @return array{0: string, 1: array} condición WHERE según lo que el rol puede ver */
    private static function filtro(Usuario $u): array
    {
        if ($u->puedeVerTodasLasTiendas()) {
            return ['1 = 1', []];
        }
        if ($u->puedeCerrarCaja()) {
            return ['c.ID_TIENDA = :tienda', [':tienda' => $u->getIdTienda()]];
        }
        return ['s.ID_CAJERO = :emp', [':emp' => $u->getIdEmpleado()]];
    }
}
