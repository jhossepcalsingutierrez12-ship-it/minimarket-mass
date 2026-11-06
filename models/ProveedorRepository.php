<?php
declare(strict_types=1);
require_once __DIR__ . '/Repositorio.php';

/**
 * Proveedores y compras de mercadería (database/admin.sql).
 * Al registrar una compra, cada producto crea un LOTE con su fecha de recepción
 * y su cantidad entra al stock de INVENTARIO, todo en una sola transacción.
 */
class ProveedorRepository extends Repositorio
{
    public function listar(): array
    {
        return $this->pdo->query('
            SELECT pr.*,
                   (SELECT COUNT(*) FROM COMPRA c WHERE c.ID_PROVEEDOR = pr.ID_PROVEEDOR) AS COMPRAS,
                   (SELECT COALESCE(SUM(c.TOTAL), 0) FROM COMPRA c WHERE c.ID_PROVEEDOR = pr.ID_PROVEEDOR) AS COMPRADO,
                   (SELECT MAX(c.FECHA_RECEPCION) FROM COMPRA c WHERE c.ID_PROVEEDOR = pr.ID_PROVEEDOR) AS ULTIMA
            FROM PROVEEDOR pr ORDER BY pr.RAZON_SOCIAL')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function activos(): array
    {
        return $this->pdo->query("SELECT * FROM PROVEEDOR WHERE ESTADO = 'A' ORDER BY RAZON_SOCIAL")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM PROVEEDOR WHERE ID_PROVEEDOR = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function existeRuc(string $ruc): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM PROVEEDOR WHERE RUC = :r');
        $stmt->execute([':r' => $ruc]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function crear(string $ruc, string $razon, ?string $rubro, ?string $categoria, ?string $contacto, ?string $telefono, ?string $correo): int
    {
        return $this->enTransaccion(function () use ($ruc, $razon, $rubro, $categoria, $contacto, $telefono, $correo): int {
            $id = $this->siguienteId('PROVEEDOR', 'ID_PROVEEDOR');
            $this->pdo->prepare("
                INSERT INTO PROVEEDOR (ID_PROVEEDOR, RUC, RAZON_SOCIAL, RUBRO, CATEGORIA_SKU, CONTACTO, TELEFONO, CORREO, ESTADO, FECHA_REGISTRO)
                VALUES (:id, :ruc, :razon, :rubro, :cat, :contacto, :tel, :correo, 'A', :f)")
                ->execute([':id' => $id, ':ruc' => $ruc, ':razon' => $razon, ':rubro' => $rubro, ':cat' => $categoria, ':contacto' => $contacto,
                           ':tel' => $telefono, ':correo' => $correo, ':f' => self::ahora()]);
            return $id;
        });
    }

    public function cambiarEstado(int $id, string $estado): void
    {
        $this->pdo->prepare('UPDATE PROVEEDOR SET ESTADO = :e WHERE ID_PROVEEDOR = :id')->execute([':e' => $estado, ':id' => $id]);
    }

    public function compras(int $limite = 100): array
    {
        $compras = $this->pdo->query('
            SELECT c.*, pr.RAZON_SOCIAL, pr.RUC, t.NOMBRE AS TIENDA, e.NOMBRES, e.APELLIDOS,
                   (SELECT COUNT(*) FROM DETALLE_COMPRA d WHERE d.ID_COMPRA = c.ID_COMPRA) AS ITEMS,
                   (SELECT COALESCE(SUM(d.CANTIDAD), 0) FROM DETALLE_COMPRA d WHERE d.ID_COMPRA = c.ID_COMPRA) AS UNIDADES
            FROM COMPRA c
            JOIN PROVEEDOR pr ON pr.ID_PROVEEDOR = c.ID_PROVEEDOR
            JOIN TIENDA t     ON t.ID_TIENDA = c.ID_TIENDA
            JOIN EMPLEADO e   ON e.ID_EMPLEADO = c.ID_EMPLEADO
            ORDER BY c.FECHA_RECEPCION DESC, c.ID_COMPRA DESC
            LIMIT ' . max(1, $limite))->fetchAll(PDO::FETCH_ASSOC);
        if (!$compras) {
            return [];
        }
        // Detalle de cada compra (producto, lote, cantidad y costo)
        $det = $this->pdo->query('
            SELECT d.*, p.NOMBRE, p.CODIGO_SKU, l.CODIGO_LOTE, l.FECHA_VENCIMIENTO
            FROM DETALLE_COMPRA d
            JOIN PRODUCTO p ON p.ID_PRODUCTO = d.ID_PRODUCTO
            JOIN LOTE l     ON l.ID_LOTE = d.ID_LOTE
            ORDER BY d.ID_COMPRA, p.CODIGO_SKU')->fetchAll(PDO::FETCH_ASSOC);
        $porCompra = [];
        foreach ($det as $d) {
            $porCompra[(int) $d['ID_COMPRA']][] = $d;
        }
        foreach ($compras as &$c) {
            $c['DETALLE'] = $porCompra[(int) $c['ID_COMPRA']] ?? [];
        }
        return $compras;
    }

    /**
     * Registra la compra. $lineas = [['producto' => id, 'cantidad' => int, 'costo' => float, 'vence' => ?Y-m-d], ...]
     * Por cada línea: crea el LOTE (código SKU + año/mes + letra), suma el stock y guarda el DETALLE_COMPRA.
     * @return array{id: int, total: float}
     */
    public function registrarCompra(int $idProveedor, int $idTienda, Usuario $quien, ?string $numeroDoc, array $lineas): array
    {
        return $this->enTransaccion(function () use ($idProveedor, $idTienda, $quien, $numeroDoc, $lineas): array {
            $ahora = self::ahora();
            $total = 0.0;
            foreach ($lineas as $l) {
                $total += round($l['cantidad'] * $l['costo'], 2);
            }
            $idCompra = $this->siguienteId('COMPRA', 'ID_COMPRA');
            $this->pdo->prepare('
                INSERT INTO COMPRA (ID_COMPRA, ID_PROVEEDOR, ID_TIENDA, ID_EMPLEADO, NUMERO_DOC, FECHA_RECEPCION, TOTAL)
                VALUES (:id, :pr, :t, :e, :doc, :f, :total)')
                ->execute([':id' => $idCompra, ':pr' => $idProveedor, ':t' => $idTienda, ':e' => $quien->getIdEmpleado(),
                           ':doc' => $numeroDoc, ':f' => $ahora, ':total' => self::dinero($total)]);

            $sku = $this->pdo->prepare('SELECT CODIGO_SKU FROM PRODUCTO WHERE ID_PRODUCTO = :p');
            $cuenta = $this->pdo->prepare('SELECT COUNT(*) FROM LOTE WHERE CODIGO_LOTE LIKE :b');
            $lote = $this->pdo->prepare('
                INSERT INTO LOTE (ID_LOTE, ID_PRODUCTO, CODIGO_LOTE, FECHA_VENCIMIENTO, CANTIDAD_RECIBIDA, COSTO_UNITARIO, FECHA_RECEPCION)
                VALUES (:id, :p, :cod, :vence, :cant, :costo, :f)');
            $stock = $this->pdo->prepare('UPDATE INVENTARIO SET STOCK_ACTUAL = STOCK_ACTUAL + :c, FECHA_ULT_CONTEO = :f WHERE ID_PRODUCTO = :p');
            $detalle = $this->pdo->prepare('
                INSERT INTO DETALLE_COMPRA (ID_COMPRA, ID_PRODUCTO, ID_LOTE, CANTIDAD, COSTO_UNITARIO, IMPORTE)
                VALUES (:c, :p, :l, :cant, :costo, :imp)');
            foreach ($lineas as $l) {
                $sku->execute([':p' => $l['producto']]);
                $base = $sku->fetchColumn() . '-' . date('ym');
                $cuenta->execute([':b' => $base . '%']);
                $codigo = $base . chr(65 + (int) $cuenta->fetchColumn());
                $idLote = $this->siguienteId('LOTE', 'ID_LOTE');
                $lote->execute([':id' => $idLote, ':p' => $l['producto'], ':cod' => $codigo, ':vence' => $l['vence'],
                                ':cant' => $l['cantidad'], ':costo' => self::dinero($l['costo']), ':f' => $ahora]);
                $stock->execute([':c' => $l['cantidad'], ':f' => $ahora, ':p' => $l['producto']]);
                $detalle->execute([':c' => $idCompra, ':p' => $l['producto'], ':l' => $idLote, ':cant' => $l['cantidad'],
                                   ':costo' => self::dinero($l['costo']), ':imp' => self::dinero($l['cantidad'] * $l['costo'])]);
            }
            return ['id' => $idCompra, 'total' => round($total, 2)];
        });
    }
}
