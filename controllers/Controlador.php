<?php
declare(strict_types=1);

/**
 * Lo que comparten los controladores: quién inició sesión, mostrar una vista,
 * redirigir, mensajes de aviso y revisar permisos por rol.
 */
abstract class Controlador
{
    public function __construct(protected Usuario $usuario, protected array $tiendaActual) {}

    // Muestra views/<archivo>.php con sus datos. Toda vista recibe $u (el rol) y $tiendaActual.
    protected function vista(string $archivo, array $datos = []): void
    {
        extract($datos + ['u' => $this->usuario, 'tiendaActual' => $this->tiendaActual, 'errores' => [], 'form' => []]);
        require __DIR__ . '/../views/' . $archivo . '.php';
    }

    // Después de guardar se redirige (patrón POST -> redirección -> GET): recargar no repite la acción
    protected function redirigir(string $accion, array $parametros = []): void
    {
        header('Location: ?' . http_build_query(['accion' => $accion] + $parametros));
        exit;
    }

    // Aviso que se muestra una sola vez en la siguiente página (ok, err o info)
    protected function aviso(string $tipo, string $texto): void
    {
        $_SESSION['aviso'] = ['tipo' => $tipo, 'texto' => $texto];
    }

    // Si el rol no tiene permiso, vuelve al catálogo con un aviso
    protected function exigir(bool $permitido): void
    {
        if (!$permitido) {
            $this->aviso('err', 'Tu rol no tiene acceso a esa sección.');
            $this->redirigir('listar');
        }
    }

    protected static function texto(string $campo, int $max = 200): string
    {
        return mb_substr(trim((string) ($_POST[$campo] ?? '')), 0, $max);
    }

    // Número entero >= 0 escrito por el usuario, o null si no es válido
    protected static function entero(string $campo, ?array $origen = null): ?int
    {
        $v = trim((string) (($origen ?? $_POST)[$campo] ?? ''));
        return preg_match('/^\d{1,9}$/', $v) === 1 ? (int) $v : null;
    }

    // Monto con hasta 2 decimales (acepta coma o punto), o null si no es válido
    protected static function decimal(string $campo): ?float
    {
        $v = str_replace(',', '.', trim((string) ($_POST[$campo] ?? '')));
        return preg_match('/^\d{1,8}(\.\d{1,2})?$/', $v) === 1 ? (float) $v : null;
    }

    // ID que llega por la dirección (?id=5)
    protected static function idGet(): int
    {
        return (int) preg_replace('/\D/', '', (string) ($_GET['id'] ?? '0'));
    }
}
