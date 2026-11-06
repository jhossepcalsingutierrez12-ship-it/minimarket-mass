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
            Historial::anotar($this->usuario, 'REVISAR_FALTANTES', 'Revisó ' . $nuevos['faltantes'] . ' faltante(s) de caja (' . sol($nuevos['total']) . ')'
                . ($nuevos['respuestas'] ? ' y ' . count($nuevos['respuestas']) . ' respuesta(s) de cajeros' : ''));
        }
        // Errores del formulario «Preguntar o reportar» (se muestran una sola vez)
        $errores = $_SESSION['consulta_err'] ?? [];
        $form = $_SESSION['consulta_form'] ?? [];
        unset($_SESSION['consulta_err'], $_SESSION['consulta_form']);
        $this->vista('tiendas/faltantes', [
            'lista' => $lista,
            'desde' => $nuevos['desde'],
            'nuevos' => $nuevos['faltantes'],
            'consultas' => $this->notificaciones->consultasDeSesiones(array_column($lista, 'ID_SESION')),
            'hayConsultas' => $this->notificaciones->hayConsultas(),
            'errores' => $errores,
            'form' => $form,
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
        // Lo último que pasó: una caja cerrada con faltante o la respuesta de un cajero
        $f = $n['ultimo'];
        $r = $n['respuestas'][0] ?? null;
        $esRespuesta = $r !== null && ($f === null || $r['FECHA_RESPUESTA'] > $f['FECHA_CIERRE']);
        echo json_encode([
            'n' => $n['n'],
            'tipo' => $esRespuesta ? 'respuesta' : 'faltante',
            'texto' => $esRespuesta ? NotificacionRepository::textoRespuesta($r) : ($f ? NotificacionRepository::texto($f) : ''),
        ], JSON_UNESCAPED_UNICODE);
    }

    // 4. Preguntar al cajero qué pasó con el dinero, o reportarle el faltante
    public function consultar(): void
    {
        $this->exigir($this->usuario->puedeVerDineroCajas());
        $sesion = self::entero('sesion') ?? 0;
        $tipo = self::texto('tipo', 8);
        $mensaje = self::texto('mensaje', 500);
        $errores = [];
        if (!isset(NotificacionRepository::TIPOS[$tipo])) {
            $errores['tipo'] = 'Elige si es una pregunta o un reporte.';
        }
        if (mb_strlen($mensaje) < 10) {
            $errores['mensaje'] = 'Escribe el mensaje para el cajero (al menos 10 letras).';
        }
        if ($errores) {
            $_SESSION['consulta_err'] = $errores + ['sesion' => $sesion];
            $_SESSION['consulta_form'] = ['sesion' => $sesion, 'tipo' => $tipo, 'mensaje' => $mensaje];
            $this->redirigir('faltantes');
        }
        try {
            $f = $this->notificaciones->enviarConsulta($this->usuario, $sesion, $tipo, $mensaje);
            $cajero = nombreCorto($f['CAJERO_NOMBRES'], $f['CAJERO_APELLIDOS']);
            Historial::anotar($this->usuario, $tipo === 'PREGUNTA' ? 'PREGUNTAR_FALTANTE' : 'REPORTAR_FALTANTE',
                ($tipo === 'PREGUNTA' ? 'Preguntó a ' : 'Reportó a ') . $cajero . ' por el faltante de ' . sol(-(float) $f['DIFERENCIA'])
                . " en {$f['TIENDA']} · Caja N° {$f['NUMERO_CAJA']} (sesión $sesion)");
            $this->avisarAlCajero($f, $tipo, $mensaje);
            $this->aviso('ok', ($tipo === 'PREGUNTA' ? 'Pregunta enviada a ' : 'Reporte enviado a ') . $cajero . '. Lo verá al entrar al sistema y te responderá aquí.');
        } catch (DomainException $e) {
            $this->aviso('err', $e->getMessage());
        } catch (PDOException $e) {
            error_log('Consulta de caja: ' . $e->getMessage());
            $this->aviso('err', 'Falta la tabla CONSULTA_CAJA: corre database/consultas_caja.sql.');
        }
        $this->redirigir('faltantes');
    }

    // Si hay cuenta de correo configurada, al cajero también le llega por correo
    private function avisarAlCajero(array $f, string $tipo, string $mensaje): void
    {
        $correo = Correo::desdeConfig();
        $para = $correo ? $this->notificaciones->correoDe((int) $f['ID_CAJERO']) : '';
        if ($para === '') {
            return;
        }
        $titulo = $tipo === 'PREGUNTA' ? 'El Administrador te pregunta por un faltante de caja' : 'Reporte por un faltante de caja';
        $detalle = "{$f['TIENDA']} · Caja N° {$f['NUMERO_CAJA']} · cerrada el " . fecha($f['FECHA_CIERRE']) . ' · faltan ' . sol(-(float) $f['DIFERENCIA']);
        try {
            $correo->enviar($para, $titulo, "$titulo\n\n$detalle\n\n«{$mensaje}»\n\n— " . $this->usuario->getNombreCompleto()
                . "\n\nEntra al sistema para responder (Preguntas del Administrador).",
                '<div style="font-family:Arial,sans-serif;max-width:480px"><h2 style="background:#ffd200;padding:10px 14px;margin:0;font-size:18px">' . e($titulo) . '</h2>'
                . '<p style="padding:0 14px;color:#555">' . e($detalle) . '</p><blockquote style="margin:0 14px;padding:10px 14px;border-left:4px solid #111;background:#f6f6f6">'
                . nl2br(e($mensaje)) . '</blockquote><p style="padding:0 14px;font-size:13px">— ' . e($this->usuario->getNombreCompleto())
                . '<br><br>Entra al sistema para responder (<b>Preguntas del Administrador</b>).</p></div>');
        } catch (Throwable $e) {
            error_log('Correo al cajero: ' . $e->getMessage());
        }
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
