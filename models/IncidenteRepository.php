<?php
declare(strict_types=1);
require_once __DIR__ . '/Repositorio.php';

/**
 * Incidentes de seguridad según PROTOCOLO_EMERGENCIA, en una ZONA_TIENDA.
 * Estados: ABIERTO -> EN INVESTIGACION -> CERRADO (CK_INC_3).
 */
class IncidenteRepository extends Repositorio
{
    public const SIGUIENTE = ['ABIERTO' => 'EN INVESTIGACION', 'EN INVESTIGACION' => 'CERRADO'];

    public function protocolos(): array
    {
        return $this->pdo->query('SELECT * FROM PROTOCOLO_EMERGENCIA ORDER BY ID_PROTOCOLO')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listar(Usuario $u): array
    {
        $sql = '
            SELECT i.*, pr.NOMBRE AS PROTOCOLO, pr.NIVEL_RIESGO, z.NOMBRE AS ZONA, z.ID_TIENDA, t.NOMBRE AS TIENDA,
                   r.NOMBRES AS REP_NOMBRES, r.APELLIDOS AS REP_APELLIDOS,
                   s.NOMBRES AS RES_NOMBRES, s.APELLIDOS AS RES_APELLIDOS
            FROM INCIDENTE i
            JOIN PROTOCOLO_EMERGENCIA pr ON pr.ID_PROTOCOLO = i.ID_PROTOCOLO
            JOIN ZONA_TIENDA z ON z.ID_ZONA = i.ID_ZONA
            JOIN TIENDA t      ON t.ID_TIENDA = z.ID_TIENDA
            JOIN EMPLEADO r    ON r.ID_EMPLEADO = i.ID_REPORTA
            JOIN EMPLEADO s    ON s.ID_EMPLEADO = i.ID_RESPONSABLE';
        if ($u->puedeVerTodasLasTiendas()) {
            return $this->pdo->query($sql . ' ORDER BY i.FECHA_HORA DESC')->fetchAll(PDO::FETCH_ASSOC);
        }
        $stmt = $this->pdo->prepare($sql . ' WHERE z.ID_TIENDA = :t ORDER BY i.FECHA_HORA DESC');
        $stmt->execute([':t' => $u->getIdTienda()]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // El responsable es el jefe directo de quien reporta (o él mismo si no tiene jefe)
    public function reportar(Usuario $u, int $idProtocolo, int $idZona, string $desc, bool $heridos, float $perdida, ?string $pnp): int
    {
        return $this->enTransaccion(function () use ($u, $idProtocolo, $idZona, $desc, $heridos, $perdida, $pnp): int {
            $z = $this->pdo->prepare('SELECT COUNT(*) FROM ZONA_TIENDA WHERE ID_ZONA = :z AND ID_TIENDA = :t');
            $z->execute([':z' => $idZona, ':t' => $u->getIdTienda()]);
            if ((int) $z->fetchColumn() === 0) {
                throw new DomainException('Elige una zona de tu tienda.');
            }
            $id = $this->siguienteId('INCIDENTE', 'ID_INCIDENTE');
            $this->pdo->prepare("
                INSERT INTO INCIDENTE (ID_INCIDENTE, ID_PROTOCOLO, ID_ZONA, ID_REPORTA, ID_RESPONSABLE, FECHA_HORA, DESCRIPCION, HUBO_HERIDOS, MONTO_PERDIDA, NRO_DENUNCIA_PNP, ESTADO)
                VALUES (:id, :prot, :zona, :rep, :res, :fecha, :desc, :her, :per, :pnp, 'ABIERTO')")
                ->execute([':id' => $id, ':prot' => $idProtocolo, ':zona' => $idZona, ':rep' => $u->getIdEmpleado(),
                           ':res' => $u->getIdSupervisor() ?? $u->getIdEmpleado(), ':fecha' => self::ahora(), ':desc' => $desc,
                           ':her' => $heridos ? 'S' : 'N', ':per' => self::dinero($perdida), ':pnp' => $pnp]);
            return $id;
        });
    }

    public function avanzar(int $id, Usuario $u): string
    {
        foreach ($this->listar($u) as $i) {
            if ((int) $i['ID_INCIDENTE'] === $id && isset(self::SIGUIENTE[$i['ESTADO']])) {
                $nuevo = self::SIGUIENTE[$i['ESTADO']];
                $this->pdo->prepare('UPDATE INCIDENTE SET ESTADO = :e WHERE ID_INCIDENTE = :id')
                    ->execute([':e' => $nuevo, ':id' => $id]);
                return $nuevo;
            }
        }
        throw new DomainException('Ese incidente no existe o ya está cerrado.');
    }
}
