<?php
declare(strict_types=1);
require_once __DIR__ . '/Repositorio.php';

/**
 * Mermas: productos vencidos, dañados o robados. Tabla MERMA.
 * CK_MER_4 valor = cantidad × costo · CK_MER_6 quien autoriza no es quien registra.
 * El trigger TRG_MERMA_DESCUENTA_STOCK baja el stock al registrar.
 */
class MermaRepository extends Repositorio
{
    private const SELECT = '
        SELECT m.*, p.NOMBRE, l.CODIGO_LOTE, z.ID_TIENDA,
               r.NOMBRES AS REG_NOMBRES, r.APELLIDOS AS REG_APELLIDOS,
               a.NOMBRES AS AUT_NOMBRES, a.APELLIDOS AS AUT_APELLIDOS
        FROM MERMA m
        JOIN PRODUCTO p    ON p.ID_PRODUCTO = m.ID_PRODUCTO
        JOIN ZONA_TIENDA z ON z.ID_ZONA = m.ID_ZONA
        JOIN EMPLEADO r    ON r.ID_EMPLEADO = m.ID_REGISTRA
        LEFT JOIN EMPLEADO a ON a.ID_EMPLEADO = m.ID_AUTORIZA
        LEFT JOIN LOTE l     ON l.ID_LOTE = m.ID_LOTE';

    // Admin: todas · Supervisor: las de su tienda · Cajero: solo las que registró
    public function listar(Usuario $u): array
    {
        [$donde, $params] = self::filtro($u);
        $stmt = $this->pdo->prepare(self::SELECT . " WHERE $donde ORDER BY m.ID_MERMA DESC");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function registrar(Usuario $u, int $idProducto, ?int $idLote, int $idZona, string $motivo, int $cantidad, ?string $obs): float
    {
        return $this->enTransaccion(function () use ($u, $idProducto, $idLote, $idZona, $motivo, $cantidad, $obs): float {
            $p = $this->pdo->prepare('SELECT p.PRECIO_VENTA, i.STOCK_ACTUAL FROM PRODUCTO p JOIN INVENTARIO i ON i.ID_PRODUCTO = p.ID_PRODUCTO WHERE p.ID_PRODUCTO = :id');
            $p->execute([':id' => $idProducto]);
            $prod = $p->fetch(PDO::FETCH_ASSOC);
            if ($prod === false) {
                throw new DomainException('Producto no encontrado.');
            }
            if ($cantidad > (int) $prod['STOCK_ACTUAL']) {
                throw new DomainException("Solo hay {$prod['STOCK_ACTUAL']} en stock.");
            }
            $z = $this->pdo->prepare('SELECT COUNT(*) FROM ZONA_TIENDA WHERE ID_ZONA = :z AND ID_TIENDA = :t');
            $z->execute([':z' => $idZona, ':t' => $u->getIdTienda()]);
            if ((int) $z->fetchColumn() === 0) {
                throw new DomainException('Elige una zona de tu tienda.');
            }

            // El costo sale del lote elegido o del último lote recibido de ese producto
            if ($idLote !== null) {
                $l = $this->pdo->prepare('SELECT COSTO_UNITARIO FROM LOTE WHERE ID_LOTE = :l AND ID_PRODUCTO = :p');
                $l->execute([':l' => $idLote, ':p' => $idProducto]);
            } else {
                $l = $this->pdo->prepare('SELECT COSTO_UNITARIO FROM LOTE WHERE ID_PRODUCTO = :p ORDER BY ID_LOTE DESC LIMIT 1');
                $l->execute([':p' => $idProducto]);
            }
            $costo = $l->fetchColumn();
            if ($costo === false && $idLote !== null) {
                throw new DomainException('Ese lote no es de este producto.');
            }
            // Sin lote: costo aproximado = 75 % del precio sin IGV
            $costo = $costo === false ? round((float) $prod['PRECIO_VENTA'] / 1.18 * 0.75, 2) : (float) $costo;
            $valor = round($cantidad * $costo, 2);   // CK_MER_4

            $this->pdo->prepare("
                INSERT INTO MERMA (ID_MERMA, ID_PRODUCTO, ID_LOTE, ID_ZONA, ID_REGISTRA, MOTIVO, FECHA, CANTIDAD, COSTO_UNITARIO, VALOR_TOTAL, ESTADO, OBSERVACION)
                VALUES (:id, :p, :lote, :zona, :reg, :motivo, :fecha, :cant, :costo, :valor, 'REGISTRADA', :obs)")
                ->execute([':id' => $this->siguienteId('MERMA', 'ID_MERMA'), ':p' => $idProducto, ':lote' => $idLote,
                           ':zona' => $idZona, ':reg' => $u->getIdEmpleado(), ':motivo' => $motivo, ':fecha' => date('Y-m-d'),
                           ':cant' => $cantidad, ':costo' => self::dinero($costo), ':valor' => self::dinero($valor), ':obs' => $obs]);
            return $valor;
        });
    }

    // CK_MER_6: nunca la autoriza quien la registró
    public function autorizar(int $idMerma, Usuario $u): array
    {
        [$donde, $params] = self::filtro($u);
        $stmt = $this->pdo->prepare(self::SELECT . " WHERE m.ID_MERMA = :id AND $donde");
        $stmt->execute([':id' => $idMerma] + $params);
        $m = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($m === false || $m['ESTADO'] !== 'REGISTRADA') {
            throw new DomainException('Esa merma no existe o ya fue aprobada.');
        }
        if (!$u->autorizaMermaDe((int) $m['ID_REGISTRA'])) {
            throw new DomainException('CK_MER_6: no puedes autorizar una merma que registraste tú. Debe hacerlo otra persona con permiso.');
        }
        $this->pdo->prepare("UPDATE MERMA SET ID_AUTORIZA = :a, ESTADO = 'APROBADA' WHERE ID_MERMA = :id")
            ->execute([':a' => $u->getIdEmpleado(), ':id' => $idMerma]);
        return $m;
    }

    /** @return array{0: string, 1: array} */
    private static function filtro(Usuario $u): array
    {
        if ($u->puedeVerTodasLasTiendas()) {
            return ['1 = 1', []];
        }
        if ($u->puedeAutorizarMerma()) {
            return ['z.ID_TIENDA = :tienda', [':tienda' => $u->getIdTienda()]];
        }
        return ['m.ID_REGISTRA = :emp', [':emp' => $u->getIdEmpleado()]];
    }
}
