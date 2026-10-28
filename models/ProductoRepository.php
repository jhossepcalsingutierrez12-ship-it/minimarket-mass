<?php
declare(strict_types=1);
require_once __DIR__ . '/Producto.php';

/**
 * Única clase que sabe de dónde salen los productos.
 * Ahora: SELECT con PDO sobre PRODUCTO JOIN INVENTARIO (relación 1 a 1 por ID_PRODUCTO).
 * Solo lista productos activos (PRODUCTO.ESTADO = 'A').
 */
class ProductoRepository
{
    private const SELECT = '
        SELECT p.ID_PRODUCTO, p.CODIGO_SKU, p.NOMBRE, p.PRECIO_VENTA, p.ES_PERECIBLE,
               i.STOCK_ACTUAL, i.STOCK_MINIMO
        FROM PRODUCTO p
        JOIN INVENTARIO i ON i.ID_PRODUCTO = p.ID_PRODUCTO
        WHERE p.ESTADO = \'A\'';

    // Recibe la conexión ya creada (no la crea él mismo)
    public function __construct(private PDO $pdo) {}

    /** @return Producto[] */
    public function obtenerTodos(): array
    {
        $filas = $this->pdo->query(self::SELECT . ' ORDER BY p.ID_PRODUCTO')->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn(array $f): Producto => $this->aProducto($f), $filas);
    }

    public function buscarPorSku(string $codigoSku): ?Producto
    {
        // Consulta preparada: el SKU viaja aparte del SQL, así no hay inyección SQL
        $stmt = $this->pdo->prepare(self::SELECT . ' AND p.CODIGO_SKU = :sku');
        $stmt->bindValue(':sku', $codigoSku, PDO::PARAM_STR);
        $stmt->execute();
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila === false ? null : $this->aProducto($fila);
    }

    // PDO devuelve texto: se convierte a los tipos que pide Producto (strict_types)
    private function aProducto(array $f): Producto
    {
        return new Producto(
            (int) $f['ID_PRODUCTO'],
            (string) $f['CODIGO_SKU'],
            (string) $f['NOMBRE'],
            (float) $f['PRECIO_VENTA'],
            $f['ES_PERECIBLE'] === 'S',   // en la base es 'S' / 'N'
            (int) $f['STOCK_ACTUAL'],
            (int) $f['STOCK_MINIMO']
        );
    }
}