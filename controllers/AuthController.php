<?php
declare(strict_types=1);

/**
 * Ingreso seguro en dos pasos:
 *  Paso 1: usuario y contraseña (password_verify contra el hash; 3 intentos y bloqueo de 30 s).
 *  Antes: "verifica que eres humano" (enderezar el logo de Mass, models/Captcha.php).
 *  Paso 2: código de 6 dígitos que llega al correo del empleado (vence en 5 min, 3 intentos).
 * Todos los formularios llevan un token (CSRF) para que otra página no pueda enviarlos.
 */
class AuthController
{
    private const MAX_INTENTOS = 3;
    private const BLOQUEO_SEGUNDOS = 30;
    private const CODIGO_VENCE = 300;       // 5 minutos
    private const REENVIO_ESPERA = 30;

    public function __construct(private UsuarioRepository $repo) {}

    // Datos de quien inició sesión, o null si nadie entró
    public static function usuarioActual(): ?array
    {
        return $_SESSION['usuario'] ?? null;
    }

    // Token secreto de la sesión que viaja oculto en los formularios
    public static function token(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['csrf'];
    }

    // El token llega oculto en cada formulario (campo csrf)
    public static function tokenValido(): bool
    {
        $enviado = (string) ($_POST['csrf'] ?? '');
        return hash_equals($_SESSION['csrf'] ?? '', $enviado);
    }

    /* ======================= Paso 1: usuario y contraseña ======================= */

    public function mostrarLogin(string $error = ''): void
    {
        if (self::usuarioActual() !== null) {
            header('Location: ?accion=listar');
            return;
        }
        $espera = $this->segundosBloqueo();
        $token = self::token();
        Captcha::nuevo();   // cada vez que se muestra el login, el logo sale girado distinto
        require __DIR__ . '/../views/auth/login.php';
    }

    public function login(): void
    {
        if (!self::tokenValido()) {
            http_response_code(400);
            $this->mostrarLogin('La página expiró. Vuelve a intentarlo.');
            return;
        }
        if ($this->segundosBloqueo() > 0) {
            $this->mostrarLogin();
            return;
        }

        // Primero el captcha: si el logo no quedó derecho, ni se revisa la contraseña
        if (!Captcha::verificar((string) ($_POST['giro'] ?? ''))) {
            $this->contarIntento();
            $this->mostrarLogin('Verifica que eres humano: gira el logo de Mass hasta que quede derecho.');
            return;
        }

        $usuario = trim((string) ($_POST['usuario'] ?? ''));
        $clave = (string) ($_POST['clave'] ?? '');
        $encontrado = $usuario !== '' ? $this->repo->buscarPorUsuario($usuario) : null;

        if ($encontrado !== null && password_verify($clave, $encontrado['hash'])) {
            session_regenerate_id(true);
            unset($_SESSION['intentos'], $_SESSION['bloqueado_hasta']);
            // Todavía NO entra: falta el paso 2
            $_SESSION['pendiente'] = [
                'id' => $encontrado['usuario']->getIdEmpleado(),
                'login' => $encontrado['login'],
                'correo' => $encontrado['correo'],
            ];
            $this->nuevoCodigo();
            header('Location: ?accion=verificar');
            return;
        }

        $this->contarIntento();
        // Mismo mensaje para usuario o clave: no se revela cuál de los dos falló
        $this->mostrarLogin('Usuario o contraseña incorrectos.');
    }

    /* ======================= Paso 2: código por correo ======================= */

    public function mostrarCodigo(string $error = ''): void
    {
        $p = $_SESSION['pendiente'] ?? null;
        if ($p === null) {
            header('Location: ?accion=login');
            return;
        }
        $token = self::token();
        $esperaReenvio = max(0, $p['reenvio'] - time());
        $porCorreo = $p['medio'] === 'correo';
        $destino = $porCorreo ? self::ocultarCorreo($p['correo']) : '';
        $avisoEnvio = $p['error_envio'] ?? '';
        // Sin config/correo.php (o si falló el envío) el código se muestra en pantalla
        $codigoSimulado = $porCorreo ? null : $p['codigo'];
        require __DIR__ . '/../views/auth/codigo.php';
    }

    public function verificarCodigo(): void
    {
        $p = $_SESSION['pendiente'] ?? null;
        if ($p === null || !self::tokenValido()) {
            header('Location: ?accion=login');
            return;
        }
        $codigo = preg_replace('/\D/', '', (string) ($_POST['codigo'] ?? ''));
        if (time() > $p['vence']) {
            $this->mostrarCodigo('El código venció. Toca «Reenviar código» para recibir otro.');
            return;
        }
        if (strlen($codigo) !== 6) {
            $this->mostrarCodigo('Escribe los 6 dígitos del código.');
            return;
        }
        if (hash_equals($p['codigo'], $codigo)) {
            $this->completarIngreso($p['id']);
            return;
        }
        $_SESSION['pendiente']['intentos']++;
        $quedan = self::MAX_INTENTOS - $_SESSION['pendiente']['intentos'];
        if ($quedan <= 0) {
            unset($_SESSION['pendiente']);
            $this->mostrarLogin('Código incorrecto 3 veces. Vuelve a ingresar tu usuario y contraseña.');
            return;
        }
        $this->mostrarCodigo('Código incorrecto. Te ' . ($quedan === 1 ? 'queda 1 intento.' : "quedan $quedan intentos."));
    }

    public function reenviarCodigo(): void
    {
        $p = $_SESSION['pendiente'] ?? null;
        if ($p !== null && self::tokenValido() && time() >= $p['reenvio']) {
            $this->nuevoCodigo();
        }
        header('Location: ?accion=verificar');
    }

    public function cancelarIngreso(): void
    {
        if (self::tokenValido()) {
            unset($_SESSION['pendiente']);
        }
        header('Location: ?accion=login');
    }

    private function nuevoCodigo(): void
    {
        $p = &$_SESSION['pendiente'];
        $p['codigo'] = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $p['vence'] = time() + self::CODIGO_VENCE;
        $p['reenvio'] = time() + self::REENVIO_ESPERA;
        $p['intentos'] = 0;
        $p['medio'] = 'simulado';
        unset($p['error_envio']);

        $correo = Correo::desdeConfig();
        if ($correo === null || !filter_var($p['correo'], FILTER_VALIDATE_EMAIL)) {
            return;   // sin cuenta de correo configurada o el empleado no tiene correo: modo simulado
        }
        try {
            $correo->enviar($p['correo'], 'Tu código de ingreso: ' . $p['codigo'],
                "Tu código para entrar al sistema Minimarket Mass es {$p['codigo']}.\nVence en 5 minutos. Si no fuiste tú, ignora este correo y avisa a tu supervisor.",
                self::htmlCodigo($p['codigo']));
            $p['medio'] = 'correo';
        } catch (Throwable $e) {
            error_log('Correo del código: ' . $e->getMessage());
            $p['error_envio'] = 'No se pudo enviar el correo (' . $e->getMessage() . '). Por ahora el código se muestra abajo.';
        }
    }

    private static function htmlCodigo(string $codigo): string
    {
        return '<div style="font-family:Arial,sans-serif;max-width:420px;margin:auto;border:3px solid #111;border-radius:8px;overflow:hidden">'
             . '<div style="background:#ffd200;padding:14px 18px;font-weight:900;font-size:22px">MASS · Minimarket Arequipa</div>'
             . '<div style="padding:18px">'
             . '<p style="margin:0 0 10px">Tu código para entrar al sistema es:</p>'
             . '<p style="font-size:34px;font-weight:900;letter-spacing:8px;margin:0 0 12px">' . $codigo . '</p>'
             . '<p style="color:#555;font-size:13px;margin:0">Vence en 5 minutos. No lo compartas. Si no fuiste tú, ignora este correo y avisa a tu supervisor.</p>'
             . '</div></div>';
    }

    // rquispe@gmail.com -> rq*****@gmail.com
    private static function ocultarCorreo(string $correo): string
    {
        [$nombre, $dominio] = explode('@', $correo, 2) + [1 => ''];
        return mb_substr($nombre, 0, 2) . str_repeat('*', max(3, mb_strlen($nombre) - 2)) . '@' . $dominio;
    }

    /* ======================= Entrar y salir ======================= */

    private function completarIngreso(int $idEmpleado): void
    {
        $u = $this->repo->buscarPorId($idEmpleado);
        unset($_SESSION['pendiente']);
        if ($u === null) {
            header('Location: ?accion=login');
            return;
        }
        session_regenerate_id(true);           // nuevo id de sesión al entrar
        $_SESSION['usuario'] = [
            'id'     => $u->getIdEmpleado(),
            'nombre' => $u->getNombreCompleto(),
            'rol'    => $u->rol(),
        ];
        header('Location: ?accion=listar');
    }

    // Falló (captcha o contraseña): se cuenta el intento; al tercero se bloquea 30 segundos
    private function contarIntento(): void
    {
        $_SESSION['intentos'] = ($_SESSION['intentos'] ?? 0) + 1;
        if ($_SESSION['intentos'] >= self::MAX_INTENTOS) {
            $_SESSION['bloqueado_hasta'] = time() + self::BLOQUEO_SEGUNDOS;
            $_SESSION['intentos'] = 0;
        }
    }

    public function logout(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && self::tokenValido()) {
            $_SESSION = [];
            session_destroy();
        }
        header('Location: ?accion=login');
    }

    private function segundosBloqueo(): int
    {
        return max(0, (int) ($_SESSION['bloqueado_hasta'] ?? 0) - time());
    }
}
