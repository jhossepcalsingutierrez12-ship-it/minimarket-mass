<?php
declare(strict_types=1);
require_once __DIR__ . '/Usuario.php';
require_once __DIR__ . '/Cajero.php';
require_once __DIR__ . '/Supervisor.php';
require_once __DIR__ . '/Administrador.php';

/**
 * Busca a los empleados que pueden entrar al sistema.
 * Tablas: EMPLEADO (USUARIO, CLAVE_HASH, ESTADO) y CARGO (el rol).
 */
class UsuarioRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * Devuelve el empleado activo con ese usuario y el hash de su clave,
     * o null si no existe. El objeto sale con su rol (Cajero, Supervisor...).
     *
     * @return array{usuario: Usuario, hash: string}|null
     */
    public function buscarPorUsuario(string $usuario): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT e.ID_EMPLEADO, e.NOMBRES, e.APELLIDOS, e.CLAVE_HASH, c.NOMBRE AS CARGO
            FROM EMPLEADO e
            JOIN CARGO c ON c.ID_CARGO = e.ID_CARGO
            WHERE e.USUARIO = :usuario AND e.ESTADO = \'A\'');
        $stmt->bindValue(':usuario', $usuario, PDO::PARAM_STR);
        $stmt->execute();
        $f = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($f === false || $f['CLAVE_HASH'] === null) {
            return null;
        }

        $id = (int) $f['ID_EMPLEADO'];
        $nombres = (string) $f['NOMBRES'];
        $apellidos = (string) $f['APELLIDOS'];

        // El cargo de la base decide qué clase (rol) se crea: aquí se usa la herencia
        $objeto = match ($f['CARGO']) {
            'Administrador' => new Administrador($id, $nombres, $apellidos),
            'Supervisor'    => new Supervisor($id, $nombres, $apellidos),
            'Cajero'        => new Cajero($id, $nombres, $apellidos),
            default         => new Usuario($id, $nombres, $apellidos),
        };

        return ['usuario' => $objeto, 'hash' => (string) $f['CLAVE_HASH']];
    }
}