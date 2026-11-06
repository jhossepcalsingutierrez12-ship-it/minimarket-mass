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
        return [
            'n' => count($lista),
            'total' => -array_sum(array_map(fn($f) => (float) $f['DIFERENCIA'], $lista)),
            'ultimo' => $lista[0] ?? null,
            'desde' => $desde,
        ];
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
