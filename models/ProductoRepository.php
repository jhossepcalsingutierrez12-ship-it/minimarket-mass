<?php
declare(strict_types=1);
require_once __DIR__ . '/Producto.php';

/**
 * Única clase que sabe de dónde salen los productos.
 * SELECT con PDO sobre PRODUCTO JOIN INVENTARIO (relación 1 a 1 por ID_PRODUCTO).
 * Los productos no se borran: se dan de baja con ESTADO = 'I'.
 */
class ProductoRepository
{
    private const SELECT = '
        SELECT p.ID_PRODUCTO, p.CODIGO_SKU, p.NOMBRE, p.PRECIO_VENTA, p.ES_PERECIBLE, p.ESTADO,
               i.STOCK_ACTUAL, i.STOCK_MINIMO
        FROM PRODUCTO p
        JOIN INVENTARIO i ON i.ID_PRODUCTO = p.ID_PRODUCTO';

    // Las 3 letras del SKU dicen la categoría principal (CATEGORIA sin padre)
    private const PREFIJOS = ['ABA' => 'Abarrotes', 'LAC' => 'Lácteos', 'BEB' => 'Bebidas', 'LIM' => 'Limpieza'];

    /**
     * Productos nuevos listos para agregar (sus fotos están en public/img/productos/<SKU>.jpg).
     * En «Agregar producto» se eligen de una lista y el formulario se llena solo.
     * La marca y la subcategoría se buscan por nombre (las crea database/productos_nuevos.sql).
     */
    public const SUGERIDOS = [
        ['sku' => 'ABA-0005', 'nombre' => 'Fideo spaghetti Don Vittorio 500 g', 'subcategoria' => 'Fideos y pastas', 'marca' => 'Don Vittorio', 'precio' => '3.90', 'contenido' => '0.5', 'perecible' => 'N', 'stock' => 40, 'minimo' => 15],
        ['sku' => 'ABA-0006', 'nombre' => 'Atún Florida en aceite 140 g', 'subcategoria' => 'Conservas', 'marca' => 'Florida', 'precio' => '6.50', 'contenido' => '0.14', 'perecible' => 'N', 'stock' => 36, 'minimo' => 12],
        ['sku' => 'ABA-0007', 'nombre' => 'Sal yodada Emsal 1 kg', 'subcategoria' => 'Sal y condimentos', 'marca' => 'Emsal', 'precio' => '1.80', 'contenido' => '1', 'perecible' => 'N', 'stock' => 30, 'minimo' => 10],
        ['sku' => 'BEB-0003', 'nombre' => 'Coca-Cola sabor original 1.5 L', 'subcategoria' => 'Gaseosas', 'marca' => 'Coca-Cola', 'precio' => '7.50', 'contenido' => '1.5', 'perecible' => 'N', 'stock' => 36, 'minimo' => 12],
        ['sku' => 'BEB-0004', 'nombre' => 'Agua Cielo sin gas 625 ml', 'subcategoria' => 'Aguas', 'marca' => 'Cielo', 'precio' => '1.50', 'contenido' => '0.625', 'perecible' => 'N', 'stock' => 48, 'minimo' => 24],
        ['sku' => 'LAC-0004', 'nombre' => 'Mantequilla Gloria con sal 200 g', 'subcategoria' => 'Mantequillas', 'marca' => 'Gloria', 'precio' => '8.90', 'contenido' => '0.2', 'perecible' => 'S', 'stock' => 20, 'minimo' => 8],
        ['sku' => 'LAC-0005', 'nombre' => 'Yogurt Laive vainilla 1 L', 'subcategoria' => 'Yogurts', 'marca' => 'Laive', 'precio' => '7.50', 'contenido' => '1', 'perecible' => 'S', 'stock' => 24, 'minimo' => 10],
        ['sku' => 'LIM-0002', 'nombre' => 'Lejía Clorox 1 L', 'subcategoria' => 'Lejías y desinfectantes', 'marca' => 'Clorox', 'precio' => '4.20', 'contenido' => '1', 'perecible' => 'N', 'stock' => 30, 'minimo' => 10],
        ['sku' => 'LIM-0003', 'nombre' => 'Papel higiénico Elite 4 rollos', 'subcategoria' => 'Papel higiénico', 'marca' => 'Elite', 'precio' => '6.90', 'contenido' => '0.4', 'perecible' => 'N', 'stock' => 30, 'minimo' => 10],
    ];

    // Recibe la conexión ya creada (no la crea él mismo)
    public function __construct(private PDO $pdo) {}

    /** @return Producto[] solo los activos */
    public function obtenerTodos(): array
    {
        $filas = $this->pdo->query(self::SELECT . " WHERE p.ESTADO = 'A' ORDER BY p.ID_PRODUCTO")->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn(array $f): Producto => $this->aProducto($f), $filas);
    }

    /** @return Producto[] los dados de baja (ESTADO = 'I') */
    public function obtenerInactivos(): array
    {
        $filas = $this->pdo->query(self::SELECT . " WHERE p.ESTADO = 'I' ORDER BY p.ID_PRODUCTO")->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn(array $f): Producto => $this->aProducto($f), $filas);
    }

    public function buscarPorSku(string $codigoSku): ?Producto
    {
        // Consulta preparada: el SKU viaja aparte del SQL, así no hay inyección SQL
        $stmt = $this->pdo->prepare(self::SELECT . " WHERE p.ESTADO = 'A' AND p.CODIGO_SKU = :sku");
        $stmt->bindValue(':sku', $codigoSku, PDO::PARAM_STR);
        $stmt->execute();
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila === false ? null : $this->aProducto($fila);
    }

    // Busca activos o dados de baja (para editar, dar de baja y reactivar)
    public function buscarPorId(int $id): ?Producto
    {
        $stmt = $this->pdo->prepare(self::SELECT . ' WHERE p.ID_PRODUCTO = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila === false ? null : $this->aProducto($fila);
    }

    public function existeSku(string $sku): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM PRODUCTO WHERE CODIGO_SKU = :sku');
        $stmt->execute([':sku' => $sku]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** Subcategorías de la categoría que indica el SKU (ej. ABA -> Arroz y menestras, Aceites...) */
    public function subcategorias(string $prefijo): array
    {
        $stmt = $this->pdo->prepare('
            SELECT c.ID_CATEGORIA, c.NOMBRE
            FROM CATEGORIA c JOIN CATEGORIA padre ON padre.ID_CATEGORIA = c.ID_CATEGORIA_PADRE
            WHERE padre.NOMBRE = :padre ORDER BY c.NOMBRE');
        $stmt->execute([':padre' => self::PREFIJOS[$prefijo] ?? '']);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /** Todas las subcategorías agrupadas por prefijo: ['ABA' => [5 => 'Arroz y menestras', ...], ...] */
    public function subcategoriasPorPrefijo(): array
    {
        $r = [];
        foreach (array_keys(self::PREFIJOS) as $prefijo) {
            $r[$prefijo] = $this->subcategorias($prefijo);
        }
        return $r;
    }

    public function marcas(): array
    {
        return $this->pdo->query('SELECT ID_MARCA, NOMBRE FROM MARCA ORDER BY NOMBRE')->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /**
     * INSERT en PRODUCTO y en INVENTARIO dentro de una transacción:
     * si falla uno, no se guarda ninguno.
     */
    public function crear(array $d, int $idTienda): int
    {
        $this->pdo->beginTransaction();
        try {
            $id = (int) $this->pdo->query('SELECT COALESCE(MAX(ID_PRODUCTO), 0) + 1 FROM PRODUCTO')->fetchColumn();
            $stmt = $this->pdo->prepare('
                INSERT INTO PRODUCTO (ID_PRODUCTO, ID_CATEGORIA, ID_MARCA, CODIGO_SKU, NOMBRE, CONTENIDO, PRECIO_VENTA, ES_PERECIBLE, ESTADO)
                VALUES (:id, :cat, :marca, :sku, :nombre, :contenido, :precio, :per, \'A\')');
            $stmt->execute([
                ':id' => $id, ':cat' => $d['categoria'], ':marca' => $d['marca'], ':sku' => $d['sku'],
                ':nombre' => $d['nombre'], ':contenido' => $d['contenido'], ':precio' => $d['precio'],
                ':per' => $d['perecible'] ? 'S' : 'N',
            ]);

            // El stock se guarda en el almacén de la tienda de quien lo agrega
            $zona = $this->pdo->prepare("SELECT ID_ZONA FROM ZONA_TIENDA WHERE ID_TIENDA = :t AND TIPO = 'ALMACEN'");
            $zona->execute([':t' => $idTienda]);
            $idZona = (int) ($zona->fetchColumn() ?: 2);

            $inv = $this->pdo->prepare('
                INSERT INTO INVENTARIO (ID_PRODUCTO, ID_ZONA, STOCK_ACTUAL, STOCK_MINIMO, STOCK_MAXIMO, FECHA_ULT_CONTEO)
                VALUES (:id, :zona, :stock, :min, :max, :hoy)');
            $inv->execute([
                ':id' => $id, ':zona' => $idZona, ':stock' => $d['stock'], ':min' => $d['minimo'],
                ':max' => max($d['stock'], $d['minimo'] * 4, 1),   // CK_INV_3: mínimo <= máximo
                ':hoy' => date('Y-m-d H:i:s'),
            ]);
            $this->pdo->commit();
            return $id;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // El SKU y el stock no se editan aquí: el stock cambia con ventas, lotes y mermas
    public function actualizar(int $id, string $nombre, float $precio, int $minimo): void
    {
        $this->pdo->beginTransaction();
        try {
            $p = $this->pdo->prepare('UPDATE PRODUCTO SET NOMBRE = :n, PRECIO_VENTA = :p WHERE ID_PRODUCTO = :id');
            $p->execute([':n' => $nombre, ':p' => $precio, ':id' => $id]);
            // Si el mínimo nuevo supera al máximo, el máximo sube para cumplir CK_INV_3
            $i = $this->pdo->prepare('
                UPDATE INVENTARIO SET STOCK_MINIMO = :m,
                       STOCK_MAXIMO = CASE WHEN STOCK_MAXIMO < :m2 THEN :m3 ELSE STOCK_MAXIMO END
                WHERE ID_PRODUCTO = :id');
            $i->execute([':m' => $minimo, ':m2' => $minimo, ':m3' => $minimo, ':id' => $id]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // Dar de baja ('I') o reactivar ('A'): el producto nunca se borra
    public function cambiarEstado(int $id, string $estado): void
    {
        $stmt = $this->pdo->prepare('UPDATE PRODUCTO SET ESTADO = :e WHERE ID_PRODUCTO = :id');
        $stmt->execute([':e' => $estado === 'I' ? 'I' : 'A', ':id' => $id]);
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
            (int) $f['STOCK_MINIMO'],
            (string) $f['ESTADO']
        );
    }
}
