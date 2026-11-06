<?php
declare(strict_types=1);
require_once __DIR__ . '/Repositorio.php';

/**
 * Notificaciones del Administrador: cajas que se cerraron con FALTANTE de dinero
 * (SESION_CAJA.DIFERENCIA < 0, o sea contaron menos efectivo del que dice el sistema).
 *
 * No usa tablas nuevas:
 *  - el faltante sale de SESION_CAJA (lo guarda el Supervisor al cerrar la caja);
 *  - «ya lo vi» se guarda en el HISTORIAL como REVISAR_FALTANTES: lo nuevo es lo cerrado después de esa fecha.
 * Además, al cerrar con faltante se manda un correo a los administradores (si existe config/correo.php).
 *
 * El Administrador puede PREGUNTAR al cajero qué pasó con el dinero o REPORTAR el faltante
 * (tabla CONSULTA_CAJA de database/consultas_caja.sql); el cajero lo ve al entrar y responde.
 */
class NotificacionRepository extends Repositorio
{
    // Solo se avisan los faltantes de los últimos 30 días
    private const DIAS = 30;

    private const SELECT_FALTANTE = "
        SELECT s.ID_SESION, s.FECHA_APERTURA, s.FECHA_CIERRE, s.MONTO_APERTURA, s.EFECTIVO_SISTEMA, s.EFECTIVO_CONTADO, s.DIFERENCIA,
               c.NUMERO_CAJA, t.NOMBRE AS TIENDA, tu.NOMBRE AS TURNO,
               e.NOMBRES AS CAJERO_NOMBRES, e.APELLIDOS AS CAJERO_APELLIDOS,
               sp.NOMBRES AS SUP_NOMBRES, sp.APELLIDOS AS SUP_APELLIDOS
        FROM SESION_CAJA s
        JOIN CAJA c      ON c.ID_CAJA = s.ID_CAJA
        JOIN TIENDA t    ON t.ID_TIENDA = c.ID_TIENDA
        JOIN TURNO tu    ON tu.ID_TURNO = s.ID_TURNO
        JOIN EMPLEADO e  ON e.ID_EMPLEADO = s.ID_CAJERO
        LEFT JOIN EMPLEADO sp ON sp.ID_EMPLEADO = s.ID_SUPERVISOR
        WHERE s.ESTADO = 'CERRADA' AND s.DIFERENCIA < 0";

    /** Faltantes de caja de los últimos 30 días, lo más reciente primero */
    public function faltantes(int $limite = 100): array
    {
        $stmt = $this->pdo->prepare(self::SELECT_FALTANTE . ' AND s.FECHA_CIERRE >= :desde
            ORDER BY s.FECHA_CIERRE DESC, s.ID_SESION DESC LIMIT ' . max(1, $limite));
        $stmt->execute([':desde' => self::desde()]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Un faltante (para el correo que se manda al cerrar la caja) */
    public function faltante(int $idSesion): ?array
    {
        $stmt = $this->pdo->prepare(self::SELECT_FALTANTE . ' AND s.ID_SESION = :id');
        $stmt->execute([':id' => $idSesion]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Última vez que este administrador abrió «Faltantes de caja» (null = nunca) */
    public function ultimaRevision(int $idAdmin): ?string
    {
        try {
            $stmt = $this->pdo->prepare("SELECT MAX(FECHA_HORA) FROM HISTORIAL WHERE ID_EMPLEADO = :e AND ACCION = 'REVISAR_FALTANTES'");
            $stmt->execute([':e' => $idAdmin]);
            $f = $stmt->fetchColumn();
            return $f ? (string) $f : null;
        } catch (PDOException) {
            return null;   // sin tabla HISTORIAL (falta correr admin.sql): todo cuenta como nuevo
        }
    }

    /**
     * Faltantes que el administrador todavía no vio.
     * @return array{n: int, total: float, ultimo: ?array}
     */
    public function nuevos(int $idAdmin): array
    {
        $visto = $this->ultimaRevision($idAdmin);
        $desde = $visto !== null && $visto > self::desde() ? $visto : self::desde();
        $stmt = $this->pdo->prepare(self::SELECT_FALTANTE . ' AND s.FECHA_CIERRE > :desde ORDER BY s.FECHA_CIERRE DESC, s.ID_SESION DESC');
        $stmt->execute([':desde' => $desde]);
        $lista = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $respuestas = $this->respuestasNuevas($idAdmin, $desde);
        return [
            'n' => count($lista) + count($respuestas),
            'faltantes' => count($lista),
            'total' => -array_sum(array_map(fn($f) => (float) $f['DIFERENCIA'], $lista)),
            'ultimo' => $lista[0] ?? null,
            'respuestas' => $respuestas,
            'desde' => $desde,
        ];
    }

    /* ============ Preguntas y reportes al cajero (tabla CONSULTA_CAJA) ============ */

    public const TIPOS = ['PREGUNTA' => 'Pregunta', 'REPORTE' => 'Reporte'];

    private const SELECT_CONSULTA = '
        SELECT q.*, a.NOMBRES AS ADMIN_NOMBRES, a.APELLIDOS AS ADMIN_APELLIDOS,
               e.NOMBRES AS CAJERO_NOMBRES, e.APELLIDOS AS CAJERO_APELLIDOS,
               s.FECHA_CIERRE, s.EFECTIVO_SISTEMA, s.EFECTIVO_CONTADO, s.DIFERENCIA, c.NUMERO_CAJA, t.NOMBRE AS TIENDA
        FROM CONSULTA_CAJA q
        JOIN EMPLEADO a    ON a.ID_EMPLEADO = q.ID_ADMIN
        JOIN EMPLEADO e    ON e.ID_EMPLEADO = q.ID_CAJERO
        JOIN SESION_CAJA s ON s.ID_SESION = q.ID_SESION
        JOIN CAJA c        ON c.ID_CAJA = s.ID_CAJA
        JOIN TIENDA t      ON t.ID_TIENDA = c.ID_TIENDA';

    /** ¿Ya se corrió database/consultas_caja.sql? */
    public function hayConsultas(): bool
    {
        try {
            $this->pdo->query('SELECT 1 FROM CONSULTA_CAJA LIMIT 1');
            return true;
        } catch (PDOException) {
            return false;
        }
    }

    /** Preguntas y reportes de esas sesiones: [ID_SESION => [consultas...]] */
    public function consultasDeSesiones(array $idsSesion): array
    {
        $ids = array_values(array_unique(array_map('intval', $idsSesion)));
        if (!$ids || !$this->hayConsultas()) {
            return [];
        }
        $marcas = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(self::SELECT_CONSULTA . " WHERE q.ID_SESION IN ($marcas) ORDER BY q.FECHA_ENVIO, q.ID_CONSULTA");
        $stmt->execute($ids);
        $r = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $q) {
            $r[(int) $q['ID_SESION']][] = $q;
        }
        return $r;
    }

    /** El Administrador pregunta o reporta al cajero de una caja cerrada con faltante */
    public function enviarConsulta(Usuario $admin, int $idSesion, string $tipo, string $mensaje): array
    {
        return $this->enTransaccion(function () use ($admin, $idSesion, $tipo, $mensaje): array {
            $f = $this->faltante($idSesion);
            if ($f === null) {
                throw new DomainException('Esa caja no se cerró con faltante.');
            }
            $stmt = $this->pdo->prepare('SELECT ID_CAJERO FROM SESION_CAJA WHERE ID_SESION = :s');
            $stmt->execute([':s' => $idSesion]);
            $cajero = (int) $stmt->fetchColumn();
            $id = $this->siguienteId('CONSULTA_CAJA', 'ID_CONSULTA');
            $this->pdo->prepare("
                INSERT INTO CONSULTA_CAJA (ID_CONSULTA, ID_SESION, ID_ADMIN, ID_CAJERO, TIPO, MENSAJE, FECHA_ENVIO, ESTADO)
                VALUES (:id, :s, :a, :c, :t, :m, :f, 'PENDIENTE')")
                ->execute([':id' => $id, ':s' => $idSesion, ':a' => $admin->getIdEmpleado(), ':c' => $cajero,
                           ':t' => $tipo, ':m' => $mensaje, ':f' => self::ahora()]);
            return $f + ['ID_CAJERO' => $cajero, 'ID_CONSULTA' => $id];
        });
    }

    /** Lo que el Administrador le preguntó o reportó a este cajero (pendientes primero) */
    public function consultasDelCajero(int $idCajero): array
    {
        if (!$this->hayConsultas()) {
            return [];
        }
        $stmt = $this->pdo->prepare(self::SELECT_CONSULTA . " WHERE q.ID_CAJERO = :c
            ORDER BY q.ESTADO = 'PENDIENTE' DESC, q.FECHA_ENVIO DESC LIMIT 50");
        $stmt->execute([':c' => $idCajero]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function pendientesDelCajero(int $idCajero): int
    {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM CONSULTA_CAJA WHERE ID_CAJERO = :c AND ESTADO = 'PENDIENTE'");
            $stmt->execute([':c' => $idCajero]);
            return (int) $stmt->fetchColumn();
        } catch (PDOException) {
            return 0;
        }
    }

    /** El cajero responde (solo lo suyo y solo una vez) */
    public function responder(Usuario $cajero, int $idConsulta, string $respuesta): array
    {
        return $this->enTransaccion(function () use ($cajero, $idConsulta, $respuesta): array {
            $stmt = $this->pdo->prepare(self::SELECT_CONSULTA . ' WHERE q.ID_CONSULTA = :id');
            $stmt->execute([':id' => $idConsulta]);
            $q = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$q || (int) $q['ID_CAJERO'] !== $cajero->getIdEmpleado()) {
                throw new DomainException('Esa pregunta no es para ti.');
            }
            if ($q['ESTADO'] !== 'PENDIENTE') {
                throw new DomainException('Ya respondiste esa pregunta.');
            }
            $this->pdo->prepare("UPDATE CONSULTA_CAJA SET RESPUESTA = :r, FECHA_RESPUESTA = :f, ESTADO = 'RESPONDIDA' WHERE ID_CONSULTA = :id")
                ->execute([':r' => $respuesta, ':f' => self::ahora(), ':id' => $idConsulta]);
            return $q;
        });
    }

    /** Respuestas de los cajeros que este Administrador todavía no vio */
    private function respuestasNuevas(int $idAdmin, string $desde): array
    {
        if (!$this->hayConsultas()) {
            return [];
        }
        $stmt = $this->pdo->prepare(self::SELECT_CONSULTA . " WHERE q.ID_ADMIN = :a AND q.ESTADO = 'RESPONDIDA' AND q.FECHA_RESPUESTA > :d
            ORDER BY q.FECHA_RESPUESTA DESC");
        $stmt->execute([':a' => $idAdmin, ':d' => $desde]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Correo del empleado (para avisarle al cajero), '' si no tiene */
    public function correoDe(int $idEmpleado): string
    {
        $stmt = $this->pdo->prepare('SELECT CORREO FROM EMPLEADO WHERE ID_EMPLEADO = :e');
        $stmt->execute([':e' => $idEmpleado]);
        $c = (string) $stmt->fetchColumn();
        return filter_var($c, FILTER_VALIDATE_EMAIL) ? $c : '';
    }

    /** «Rosa Quispe respondió por el faltante de Mass Cayma · Caja N° 1» */
    public static function textoRespuesta(array $q): string
    {
        return nombreCorto($q['CAJERO_NOMBRES'], $q['CAJERO_APELLIDOS']) . ' respondió por el faltante de ' . $q['TIENDA'] . ' · Caja N° ' . (int) $q['NUMERO_CAJA'];
    }

    /** Correos de los administradores activos */
    public function correosAdministradores(): array
    {
        $stmt = $this->pdo->query("
            SELECT e.CORREO FROM EMPLEADO e JOIN CARGO c ON c.ID_CARGO = e.ID_CARGO
            WHERE c.NOMBRE = 'Administrador' AND e.ESTADO = 'A' AND e.CORREO IS NOT NULL AND e.CORREO <> ''");
        return array_values(array_filter($stmt->fetchAll(PDO::FETCH_COLUMN), fn($c) => filter_var($c, FILTER_VALIDATE_EMAIL)));
    }

    /** Texto corto del faltante: «Faltan S/ 5.00 en Mass Cayma · Caja N° 2 (Rosa Quispe)» */
    public static function texto(array $f): string
    {
        return 'Faltan ' . sol(-(float) $f['DIFERENCIA']) . ' en ' . $f['TIENDA'] . ' · Caja N° ' . (int) $f['NUMERO_CAJA']
             . ' (' . nombreCorto($f['CAJERO_NOMBRES'], $f['CAJERO_APELLIDOS']) . ')';
    }

    /**
     * Manda el aviso por correo a los administradores. Si no hay config/correo.php o falla el envío,
     * no pasa nada grave: el aviso igual aparece en la campana del sistema.
     */
    public function avisarPorCorreo(int $idSesion): void
    {
        $correo = Correo::desdeConfig();
        $f = $correo ? $this->faltante($idSesion) : null;
        if ($correo === null || $f === null) {
            return;
        }
        $falta = sol(-(float) $f['DIFERENCIA']);
        $cajero = $f['CAJERO_NOMBRES'] . ' ' . $f['CAJERO_APELLIDOS'];
        $cerro = $f['SUP_NOMBRES'] ? $f['SUP_NOMBRES'] . ' ' . $f['SUP_APELLIDOS'] : '—';
        $filas = [
            'Tienda' => $f['TIENDA'] . ' · Caja N° ' . (int) $f['NUMERO_CAJA'],
            'Cajero' => $cajero . ' (turno ' . $f['TURNO'] . ')',
            'Cerró la caja' => $cerro . ' · ' . fecha($f['FECHA_CIERRE']),
            'Según el sistema' => sol((float) $f['EFECTIVO_SISTEMA']),
            'Contado' => sol((float) $f['EFECTIVO_CONTADO']),
            'Falta' => $falta,
        ];
        $texto = "Faltó dinero al cerrar una caja.\n\n";
        $html = '<div style="font-family:Arial,sans-serif;max-width:480px">'
              . '<h2 style="background:#ffd200;padding:10px 14px;margin:0;font-size:18px">Faltó dinero al cerrar una caja</h2>'
              . '<table style="border-collapse:collapse;width:100%;font-size:14px">';
        foreach ($filas as $k => $v) {
            $texto .= "$k: $v\n";
            $html .= '<tr><td style="padding:6px 14px;border-bottom:1px solid #eee;color:#555">' . e($k) . '</td>'
                   . '<td style="padding:6px 14px;border-bottom:1px solid #eee;font-weight:bold' . ($k === 'Falta' ? ';color:#d0131f' : '') . '">' . e($v) . '</td></tr>';
        }
        $texto .= "\nRevísalo en el sistema: Administración › Faltantes de caja.";
        $html .= '</table><p style="font-size:13px;color:#555">Revísalo en el sistema: <b>Administración › Faltantes de caja</b>.</p></div>';

        foreach ($this->correosAdministradores() as $para) {
            try {
                $correo->enviar($para, "Faltante de caja: $falta en {$f['TIENDA']}", $texto, $html);
            } catch (Throwable $e) {
                error_log('Correo del faltante: ' . $e->getMessage());
            }
        }
    }

    private static function desde(): string
    {
        return date('Y-m-d H:i:s', strtotime('-' . self::DIAS . ' days'));
    }
}
