<?php
declare(strict_types=1);

/**
 * Login seguro:
 * - la clave se compara con password_verify() contra el hash de la base;
 * - después de 3 intentos fallidos se bloquea 30 segundos;
 * - el formulario lleva un token (CSRF) para que otra página no pueda enviarlo.
 */
class AuthController
{
    private const MAX_INTENTOS = 3;
    private const BLOQUEO_SEGUNDOS = 30;

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

    public function mostrarLogin(string $error = ''): void
    {
        if (self::usuarioActual() !== null) {
            header('Location: ?accion=listar');
            return;
        }
        $espera = $this->segundosBloqueo();
        $token = self::token();
        require __DIR__ . '/../views/auth/login.php';
    }

    public function login(): void
    {
        if (!$this->tokenValido()) {
            http_response_code(400);
            $this->mostrarLogin('La página expiró. Vuelve a intentarlo.');
            return;
        }
        if ($this->segundosBloqueo() > 0) {
            $this->mostrarLogin();
            return;
        }

        $usuario = trim((string) ($_POST['usuario'] ?? ''));
        $clave = (string) ($_POST['clave'] ?? '');
        $encontrado = $usuario !== '' ? $this->repo->buscarPorUsuario($usuario) : null;

        if ($encontrado !== null && password_verify($clave, $encontrado['hash'])) {
            session_regenerate_id(true);           // nuevo id de sesión al entrar
            unset($_SESSION['intentos'], $_SESSION['bloqueado_hasta']);
            $u = $encontrado['usuario'];
            $_SESSION['usuario'] = [
                'id'     => $u->getIdEmpleado(),
                'nombre' => $u->getNombreCompleto(),
                'rol'    => $u->rol(),
            ];
            header('Location: ?accion=listar');
            return;
        }

        // Falló: se cuenta el intento; al tercero se bloquea 30 segundos
        $_SESSION['intentos'] = ($_SESSION['intentos'] ?? 0) + 1;
        if ($_SESSION['intentos'] >= self::MAX_INTENTOS) {
            $_SESSION['bloqueado_hasta'] = time() + self::BLOQUEO_SEGUNDOS;
            $_SESSION['intentos'] = 0;
        }
        // Mismo mensaje para usuario o clave: no se revela cuál de los dos falló
        $this->mostrarLogin('Usuario o contraseña incorrectos.');
    }

    public function logout(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $this->tokenValido()) {
            $_SESSION = [];
            session_destroy();
        }
        header('Location: ?accion=login');
    }

    private function segundosBloqueo(): int
    {
        return max(0, (int) ($_SESSION['bloqueado_hasta'] ?? 0) - time());
    }

    private function tokenValido(): bool
    {
        return hash_equals($_SESSION['csrf'] ?? '', (string) ($_POST['csrf'] ?? ''));
    }
}