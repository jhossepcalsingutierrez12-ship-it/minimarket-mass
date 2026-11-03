<?php
declare(strict_types=1);
require_once __DIR__ . '/Repositorio.php';

/**
 * Tiendas, zonas y personal. Tablas: TIENDA, ZONA_TIENDA, EMPLEADO, CARGO, CAJA.
 * Por privacidad no se leen DNI, sueldo, grupo sanguíneo ni contacto familiar.
 */
class TiendaRepository extends Repositorio
{
    public function tiendas(): array
    {
        return $this->pdo->query("
            SELECT t.*,
                   (SELECT COUNT(*) FROM EMPLEADO e WHERE e.ID_TIENDA = t.ID_TIENDA AND e.ESTADO = 'A') AS EMPLEADOS,
                   (SELECT COUNT(*) FROM CAJA c WHERE c.ID_TIENDA = t.ID_TIENDA AND c.ESTADO = 'A') AS CAJAS
            FROM TIENDA t ORDER BY t.ID_TIENDA")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function tienda(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM TIENDA WHERE ID_TIENDA = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function zonas(?int $idTienda = null): array
    {
        if ($idTienda === null) {
            return $this->pdo->query('SELECT * FROM ZONA_TIENDA ORDER BY ID_ZONA')->fetchAll(PDO::FETCH_ASSOC);
        }
        $stmt = $this->pdo->prepare('SELECT * FROM ZONA_TIENDA WHERE ID_TIENDA = :t ORDER BY ID_ZONA');
        $stmt->execute([':t' => $idTienda]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function personal(?int $idTienda = null): array
    {
        $sql = '
            SELECT e.ID_EMPLEADO, e.ID_TIENDA, e.NOMBRES, e.APELLIDOS, e.FECHA_INGRESO, e.ESTADO,
                   c.NOMBRE AS CARGO, t.NOMBRE AS TIENDA, j.NOMBRES AS JEFE_NOMBRES, j.APELLIDOS AS JEFE_APELLIDOS
            FROM EMPLEADO e
            JOIN CARGO c  ON c.ID_CARGO = e.ID_CARGO
            JOIN TIENDA t ON t.ID_TIENDA = e.ID_TIENDA
            LEFT JOIN EMPLEADO j ON j.ID_EMPLEADO = e.ID_SUPERVISOR';
        if ($idTienda === null) {
            return $this->pdo->query($sql . ' ORDER BY e.ID_TIENDA, e.ID_CARGO, e.ID_EMPLEADO')->fetchAll(PDO::FETCH_ASSOC);
        }
        $stmt = $this->pdo->prepare($sql . ' WHERE e.ID_TIENDA = :t ORDER BY e.ID_CARGO, e.ID_EMPLEADO');
        $stmt->execute([':t' => $idTienda]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
