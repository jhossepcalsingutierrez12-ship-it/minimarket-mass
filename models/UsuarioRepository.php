<?php
declare(strict_types=1);
require_once __DIR__ . '/Usuario.php';
require_once __DIR__ . '/Cajero.php';
require_once __DIR__ . '/Supervisor.php';
require_once __DIR__ . '/Administrador.php';

/**
 * Busca a los empleados que pueden entrar al sistema.
 * Tablas: EMPLEADO (USUARIO, CLAVE_HASH, ESTADO, CORREO) y CARGO (el rol).
 */
class UsuarioRepository
{
    private const SELECT = '
        SELECT e.ID_EMPLEADO, e.ID_TIENDA, e.ID_SUPERVISOR, e.NOMBRES, e.APELLIDOS,
               e.TELEFONO, e.CORREO, e.USUARIO, e.CLAVE_HASH, c.NOMBRE AS CARGO
        FROM EMPLEADO e
        JOIN CARGO c ON c.ID_CARGO = e.ID_CARGO';

    public function __construct(private PDO $pdo) {}

    /**
     * Devuelve el empleado activo con ese usuario, el hash de su clave y su correo,
     * o null si no existe. El objeto sale con su rol (Cajero, Supervisor...).
     *
     * @return array{usuario: Usuario, hash: string, correo: string, login: string}|null
     */
    public function buscarPorUsuario(string $usuario): ?array
    {
        $stmt = $this->pdo->prepare(self::SELECT . ' WHERE e.USUARIO = :usuario AND e.ESTADO = \'A\'');
        $stmt->bindValue(':usuario', $usuario, PDO::PARAM_STR);
        $stmt->execute();
        $f = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($f === false || $f['CLAVE_HASH'] === null) {
            return null;
        }
        return [
            'usuario'  => self::crearRol($f),
            'hash'     => (string) $f['CLAVE_HASH'],
            'correo'   => (string) ($f['CORREO'] ?? ''),
            'login'    => (string) $f['USUARIO'],
        ];
    }

    // Se usa en cada página: vuelve a armar el objeto con el rol de quien inició sesión
    public function buscarPorId(int $idEmpleado): ?Usuario
    {
        $stmt = $this->pdo->prepare(self::SELECT . ' WHERE e.ID_EMPLEADO = :id AND e.ESTADO = \'A\'');
        $stmt->bindValue(':id', $idEmpleado, PDO::PARAM_INT);
        $stmt->execute();
        $f = $stmt->fetch(PDO::FETCH_ASSOC);
        return $f === false ? null : self::crearRol($f);
    }

    // El cargo de la base decide qué clase (rol) se crea: aquí se usa la herencia
    private static function crearRol(array $f): Usuario
    {
        $datos = [
            (int) $f['ID_EMPLEADO'],
            (string) $f['NOMBRES'],
            (string) $f['APELLIDOS'],
            (int) $f['ID_TIENDA'],
            $f['ID_SUPERVISOR'] === null ? null : (int) $f['ID_SUPERVISOR'],
        ];
        return match ($f['CARGO']) {
            'Administrador' => new Administrador(...$datos),
            'Supervisor'    => new Supervisor(...$datos),
            'Cajero'        => new Cajero(...$datos),
            default         => new Usuario(...$datos),
        };
    }
}
