<?php
declare(strict_types=1);
require_once __DIR__ . '/Repositorio.php';

/**
 * Lotes recibidos y sus vencimientos. Tabla LOTE (CK_LOT_1 cantidad > 0, CK_LOT_2 costo >= 0).
 * El costo unitario ya no se escribe en la pantalla: se toma del último lote del producto
 * (o, si es el primero, 75 % del precio sin IGV). Lo usan las mermas para calcular la pérdida.
 * Al recibir un lote, su cantidad se suma al stock de INVENTARIO.
 */
class LoteRepository extends Repositorio
{
    // Ordenados por vencimiento: primero lo que vence antes; los que no vencen, al final
    public function listar(): array
    {
        return $this->pdo->query('
            SELECT l.*, p.NOMBRE, p.CODIGO_SKU
            FROM LOTE l JOIN PRODUCTO p ON p.ID_PRODUCTO = l.ID_PRODUCTO
            ORDER BY CASE WHEN l.FECHA_VENCIMIENTO IS NULL THEN 1 ELSE 0 END, l.FECHA_VENCIMIENTO, l.ID_LOTE')
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function lotesDe(int $idProducto): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM LOTE WHERE ID_PRODUCTO = :p ORDER BY ID_LOTE');
        $stmt->execute([':p' => $idProducto]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscar(int $idLote): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM LOTE WHERE ID_LOTE = :id');
        $stmt->execute([':id' => $idLote]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Código que dice de qué producto es el lote: SKU + año y mes + letra.
     * Ej.: LAC-0002 recibido en noviembre de 2026 -> LAC-0002-2611A (luego B, C...).
     */
    public function codigoSugerido(string $sku): string
    {
        $base = $sku . '-' . date('ym');
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM LOTE WHERE CODIGO_LOTE LIKE :b');
        $stmt->execute([':b' => $base . '%']);
        return $base . chr(65 + (int) $stmt->fetchColumn());
    }

    public function recibir(int $idProducto, ?string $codigo, ?string $vence, int $cantidad): int
    {
        return $this->enTransaccion(function () use ($idProducto, $codigo, $vence, $cantidad): int {
            $c = $this->pdo->prepare('
                SELECT COALESCE(
                    (SELECT COSTO_UNITARIO FROM LOTE WHERE ID_PRODUCTO = :p1 ORDER BY ID_LOTE DESC LIMIT 1),
                    (SELECT ROUND(PRECIO_VENTA / 1.18 * 0.75, 2) FROM PRODUCTO WHERE ID_PRODUCTO = :p2), 0)');
            $c->execute([':p1' => $idProducto, ':p2' => $idProducto]);
            $costo = (float) $c->fetchColumn();
            $id = $this->siguienteId('LOTE', 'ID_LOTE');
            $this->pdo->prepare('
                INSERT INTO LOTE (ID_LOTE, ID_PRODUCTO, CODIGO_LOTE, FECHA_VENCIMIENTO, CANTIDAD_RECIBIDA, COSTO_UNITARIO, FECHA_RECEPCION)
                VALUES (:id, :p, :cod, :vence, :cant, :costo, :recibido)')
                ->execute([':id' => $id, ':p' => $idProducto, ':cod' => $codigo, ':vence' => $vence,
                           ':cant' => $cantidad, ':costo' => self::dinero($costo), ':recibido' => self::ahora()]);
            // La mercadería recibida entra al inventario
            $this->pdo->prepare('UPDATE INVENTARIO SET STOCK_ACTUAL = STOCK_ACTUAL + :c, FECHA_ULT_CONTEO = :f WHERE ID_PRODUCTO = :p')
                ->execute([':c' => $cantidad, ':f' => self::ahora(), ':p' => $idProducto]);
            return $id;
        });
    }
}
