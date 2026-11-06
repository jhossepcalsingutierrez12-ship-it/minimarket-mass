<?php
declare(strict_types=1);
require_once __DIR__ . '/Repositorio.php';

/**
 * Reportes y alertas para el Administrador. Calculados con los datos actuales.
 * Los reportes reciben un filtro: ['desde' => 'Y-m-d', 'hasta' => 'Y-m-d', 'tienda' => ?int].
 * Solo cuentan ventas EMITIDAS (las anuladas no suman).
 */
class ReporteRepository extends Repositorio
{
    // Costo de un producto: el del último lote; si no tiene lotes, 75 % del precio sin IGV
    private const COSTO = 'COALESCE((SELECT l.COSTO_UNITARIO FROM LOTE l WHERE l.ID_PRODUCTO = p.ID_PRODUCTO ORDER BY l.ID_LOTE DESC LIMIT 1),
                                    ROUND(p.PRECIO_VENTA / 1.18 * 0.75, 2))';

    // Ventas con su tienda (VENTA -> SESION_CAJA -> CAJA)
    private const VENTAS = 'FROM VENTA v
            JOIN SESION_CAJA s ON s.ID_SESION = v.ID_SESION
            JOIN CAJA c        ON c.ID_CAJA = s.ID_CAJA';

    /** @return array{0: string, 1: array} WHERE del filtro (fechas y tienda) */
    private static function donde(array $f, bool $conTienda = true): array
    {
        $sql = 'v.FECHA_HORA >= :desde AND v.FECHA_HORA <= :hasta';
        $p = [':desde' => $f['desde'] . ' 00:00:00', ':hasta' => $f['hasta'] . ' 23:59:59'];
        if ($conTienda && !empty($f['tienda'])) {
            $sql .= ' AND c.ID_TIENDA = :tienda';
            $p[':tienda'] = (int) $f['tienda'];
        }
        return [$sql, $p];
    }

    public function resumen(array $f): array
    {
        [$w, $p] = self::donde($f);
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(CASE WHEN v.ESTADO = 'EMITIDA' THEN v.TOTAL END), 0) AS TOTAL,
                   COALESCE(SUM(CASE WHEN v.ESTADO = 'EMITIDA' THEN 1 ELSE 0 END), 0) AS EMITIDAS,
                   COALESCE(SUM(CASE WHEN v.ESTADO = 'ANULADA' THEN 1 ELSE 0 END), 0) AS ANULADAS
            " . self::VENTAS . " WHERE $w");
        $stmt->execute($p);
        $v = $stmt->fetch(PDO::FETCH_ASSOC);

        $sqlM = 'SELECT COALESCE(SUM(m.VALOR_TOTAL), 0) FROM MERMA m JOIN ZONA_TIENDA z ON z.ID_ZONA = m.ID_ZONA
                 WHERE m.FECHA >= :desde AND m.FECHA <= :hasta';
        $pM = [':desde' => $f['desde'], ':hasta' => $f['hasta']];
        if (!empty($f['tienda'])) {
            $sqlM .= ' AND z.ID_TIENDA = :tienda';
            $pM[':tienda'] = (int) $f['tienda'];
        }
        $m = $this->pdo->prepare($sqlM);
        $m->execute($pM);

        $emitidas = (int) $v['EMITIDAS'];
        $productos = $this->porProducto($f);
        return [
            'total'    => (float) $v['TOTAL'],
            'emitidas' => $emitidas,
            'anuladas' => (int) $v['ANULADAS'],
            'promedio' => $emitidas ? (float) $v['TOTAL'] / $emitidas : 0.0,
            'mermas'   => (float) $m->fetchColumn(),
            'ganancia' => array_sum(array_column($productos, 'GANANCIA')),
        ];
    }

    /** Meses que se pueden ver: desde el de la primera venta hasta el actual, del más nuevo al más viejo */
    public function meses(): array
    {
        $nombres = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Setiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        $primera = $this->pdo->query('SELECT MIN(FECHA_HORA) FROM VENTA')->fetchColumn();
        $inicio = strtotime(date('Y-m-01', $primera ? strtotime((string) $primera) : time()));
        $r = [];
        for ($m = strtotime(date('Y-m-01')); $m >= $inicio && count($r) < 24; $m = strtotime('-1 month', $m)) {
            $r[date('Y-m', $m)] = $nombres[(int) date('n', $m) - 1] . ' ' . date('Y', $m);
        }
        return $r;
    }

    /**
     * Ventas agrupadas por día, semana o mes (para el gráfico).
     * @return array<string, array{total: float, ventas: int}> [etiqueta => datos], en orden de fecha
     */
    public function ventasPorPeriodo(array $f, string $agrupar): array
    {
        [$w, $p] = self::donde($f);
        $stmt = $this->pdo->prepare("
            SELECT DATE(v.FECHA_HORA) AS DIA, SUM(v.TOTAL) AS TOTAL, COUNT(*) AS VENTAS
            " . self::VENTAS . " WHERE $w AND v.ESTADO = 'EMITIDA'
            GROUP BY DATE(v.FECHA_HORA) ORDER BY DIA");
        $stmt->execute($p);
        $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'set', 'oct', 'nov', 'dic'];
        $r = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $d) {
            $t = strtotime((string) $d['DIA']);
            $clave = match ($agrupar) {
                // La semana empieza el lunes: "Semana del 19/10"
                'semana' => 'Semana del ' . date('d/m', strtotime('monday this week', $t)),
                'mes'    => ucfirst($meses[(int) date('n', $t) - 1]) . ' ' . date('Y', $t),
                default  => date('d/m/Y', $t),
            };
            $r[$clave]['total'] = ($r[$clave]['total'] ?? 0.0) + (float) $d['TOTAL'];
            $r[$clave]['ventas'] = ($r[$clave]['ventas'] ?? 0) + (int) $d['VENTAS'];
        }
        return $r;
    }

    /**
     * Comparación entre las 3 tiendas en el periodo (no usa el filtro de tienda).
     * @return array<int, array{NOMBRE: string, TOTAL: float, VENTAS: int, MERMAS: float}>
     */
    public function comparacionTiendas(array $f): array
    {
        [$w, $p] = self::donde($f, false);
        $stmt = $this->pdo->prepare("
            SELECT c.ID_TIENDA, SUM(v.TOTAL) AS TOTAL, COUNT(*) AS VENTAS
            " . self::VENTAS . " WHERE $w AND v.ESTADO = 'EMITIDA' GROUP BY c.ID_TIENDA");
        $stmt->execute($p);
        $ventas = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $x) {
            $ventas[(int) $x['ID_TIENDA']] = $x;
        }
        $m = $this->pdo->prepare('
            SELECT z.ID_TIENDA, SUM(m.VALOR_TOTAL) FROM MERMA m JOIN ZONA_TIENDA z ON z.ID_ZONA = m.ID_ZONA
            WHERE m.FECHA >= :desde AND m.FECHA <= :hasta GROUP BY z.ID_TIENDA');
        $m->execute([':desde' => $f['desde'], ':hasta' => $f['hasta']]);
        $mermas = array_map('floatval', $m->fetchAll(PDO::FETCH_KEY_PAIR));

        $r = [];
        foreach ($this->pdo->query('SELECT ID_TIENDA, NOMBRE FROM TIENDA ORDER BY ID_TIENDA') as $t) {
            $id = (int) $t['ID_TIENDA'];
            $r[] = ['ID_TIENDA' => $id, 'NOMBRE' => $t['NOMBRE'], 'TOTAL' => (float) ($ventas[$id]['TOTAL'] ?? 0),
                    'VENTAS' => (int) ($ventas[$id]['VENTAS'] ?? 0), 'MERMAS' => $mermas[$id] ?? 0.0];
        }
        return $r;
    }

    public function metodosDePago(array $f): array
    {
        [$w, $p] = self::donde($f);
        $stmt = $this->pdo->prepare("
            SELECT m.NOMBRE, SUM(pg.MONTO) AS T
            FROM PAGO pg JOIN METODO_PAGO m ON m.ID_METODO_PAGO = pg.ID_METODO_PAGO
            JOIN VENTA v       ON v.ID_VENTA = pg.ID_VENTA
            JOIN SESION_CAJA s ON s.ID_SESION = v.ID_SESION
            JOIN CAJA c        ON c.ID_CAJA = s.ID_CAJA
            WHERE $w AND v.ESTADO = 'EMITIDA' GROUP BY m.ID_METODO_PAGO, m.NOMBRE ORDER BY T DESC");
        $stmt->execute($p);
        return array_map('floatval', $stmt->fetchAll(PDO::FETCH_KEY_PAIR));
    }

    /**
     * Cada producto activo con lo vendido en el periodo y su ganancia estimada:
     * ganancia = venta sin IGV − unidades × costo. Ordenado de más a menos vendido.
     */
    public function porProducto(array $f): array
    {
        [$w, $p] = self::donde($f);
        $stmt = $this->pdo->prepare("
            SELECT p.ID_PRODUCTO, p.CODIGO_SKU, p.NOMBRE, p.PRECIO_VENTA, i.STOCK_ACTUAL, " . self::COSTO . " AS COSTO,
                   COALESCE(x.UNIDADES, 0) AS UNIDADES, COALESCE(x.VENTA, 0) AS VENTA
            FROM PRODUCTO p
            JOIN INVENTARIO i ON i.ID_PRODUCTO = p.ID_PRODUCTO
            LEFT JOIN (SELECT d.ID_PRODUCTO, SUM(d.CANTIDAD) AS UNIDADES, SUM(d.IMPORTE) AS VENTA
                       FROM DETALLE_VENTA d JOIN VENTA v ON v.ID_VENTA = d.ID_VENTA
                       JOIN SESION_CAJA s ON s.ID_SESION = v.ID_SESION
                       JOIN CAJA c        ON c.ID_CAJA = s.ID_CAJA
                       WHERE $w AND v.ESTADO = 'EMITIDA' GROUP BY d.ID_PRODUCTO) x ON x.ID_PRODUCTO = p.ID_PRODUCTO
            WHERE p.ESTADO = 'A'");
        $stmt->execute($p);
        $r = array_map(function (array $x): array {
            $x['UNIDADES'] = (int) round((float) $x['UNIDADES']);
            $x['VENTA'] = (float) $x['VENTA'];
            $x['COSTO'] = (float) $x['COSTO'];
            $x['GANANCIA'] = round($x['VENTA'] / 1.18 - $x['UNIDADES'] * $x['COSTO'], 2);
            return $x;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
        usort($r, fn($a, $b) => $b['UNIDADES'] <=> $a['UNIDADES'] ?: strcmp($a['NOMBRE'], $b['NOMBRE']));
        return $r;
    }

    /**
     * Alertas de inventario: agotados, poco stock (en o bajo el mínimo) con la cantidad
     * recomendada para reponer, y lotes vencidos o que vencen en los próximos 30 días.
     * Reposición recomendada = lo que falta para llegar a la meta,
     * meta = el mayor entre 2 × stock mínimo y lo que se vende en 14 días (promedio de los últimos 30).
     */
    public function alertasInventario(): array
    {
        $desde = date('Y-m-d 00:00:00', strtotime('-29 days'));
        $stmt = $this->pdo->prepare("
            SELECT p.ID_PRODUCTO, p.CODIGO_SKU, p.NOMBRE, p.ES_PERECIBLE, i.STOCK_ACTUAL, i.STOCK_MINIMO,
                   COALESCE((SELECT SUM(d.CANTIDAD) FROM DETALLE_VENTA d JOIN VENTA v ON v.ID_VENTA = d.ID_VENTA
                             WHERE d.ID_PRODUCTO = p.ID_PRODUCTO AND v.ESTADO = 'EMITIDA' AND v.FECHA_HORA >= :desde), 0) AS VENDIDO_30
            FROM PRODUCTO p JOIN INVENTARIO i ON i.ID_PRODUCTO = p.ID_PRODUCTO
            WHERE p.ESTADO = 'A' AND i.STOCK_ACTUAL <= i.STOCK_MINIMO
            ORDER BY i.STOCK_ACTUAL, p.CODIGO_SKU");
        $stmt->execute([':desde' => $desde]);
        $agotados = $poco = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $x) {
            $stock = (int) $x['STOCK_ACTUAL'];
            $min = (int) $x['STOCK_MINIMO'];
            $porDia = (float) $x['VENDIDO_30'] / 30;
            $meta = max(2 * $min, (int) ceil($porDia * 14));
            $x['POR_DIA'] = round($porDia, 1);
            $x['META'] = $meta;
            $x['REPONER'] = max(1, $meta - $stock);
            if ($stock === 0) {
                $agotados[] = $x;
            } else {
                $poco[] = $x;
            }
        }
        $lotes = $this->pdo->prepare('
            SELECT l.*, p.NOMBRE, p.CODIGO_SKU, i.STOCK_ACTUAL
            FROM LOTE l JOIN PRODUCTO p ON p.ID_PRODUCTO = l.ID_PRODUCTO JOIN INVENTARIO i ON i.ID_PRODUCTO = p.ID_PRODUCTO
            WHERE p.ESTADO = \'A\' AND l.FECHA_VENCIMIENTO IS NOT NULL AND l.FECHA_VENCIMIENTO <= :limite
            ORDER BY l.FECHA_VENCIMIENTO');
        $lotes->execute([':limite' => date('Y-m-d', strtotime('+30 days'))]);
        $hoy = strtotime(date('Y-m-d'));
        $vencidos = $porVencer = [];
        foreach ($lotes->fetchAll(PDO::FETCH_ASSOC) as $l) {
            $l['DIAS'] = (int) round((strtotime((string) $l['FECHA_VENCIMIENTO']) - $hoy) / 86400);
            if ($l['DIAS'] < 0) {
                $vencidos[] = $l;
            } else {
                $porVencer[] = $l;
            }
        }
        return compact('agotados', 'poco', 'vencidos', 'porVencer');
    }

    /** Cuántas alertas hay (para el aviso del catálogo y el menú) */
    public function contarAlertas(): int
    {
        $a = $this->alertasInventario();
        return count($a['agotados']) + count($a['poco']) + count($a['vencidos']) + count($a['porVencer']);
    }

    /**
     * Cuadre de stock de hoy, por producto activo:
     * inicio del día = stock actual − recibido hoy + vendido hoy + mermas de hoy.
     * Vendido = ventas EMITIDAS de hoy (si se anuló, el stock ya volvió).
     */
    public function cuadreStockHoy(): array
    {
        $hoy = date('Y-m-d');
        $stmt = $this->pdo->prepare("
            SELECT p.ID_PRODUCTO, p.CODIGO_SKU, p.NOMBRE, i.STOCK_ACTUAL,
                   COALESCE((SELECT SUM(l.CANTIDAD_RECIBIDA) FROM LOTE l
                              WHERE l.ID_PRODUCTO = p.ID_PRODUCTO AND DATE(l.FECHA_RECEPCION) = :h1), 0) AS RECIBIDO,
                   COALESCE((SELECT SUM(d.CANTIDAD) FROM DETALLE_VENTA d JOIN VENTA v ON v.ID_VENTA = d.ID_VENTA
                              WHERE d.ID_PRODUCTO = p.ID_PRODUCTO AND v.ESTADO = 'EMITIDA' AND DATE(v.FECHA_HORA) = :h2), 0) AS VENDIDO,
                   COALESCE((SELECT SUM(m.CANTIDAD) FROM MERMA m
                              WHERE m.ID_PRODUCTO = p.ID_PRODUCTO AND m.FECHA = :h3), 0) AS MERMAS
            FROM PRODUCTO p JOIN INVENTARIO i ON i.ID_PRODUCTO = p.ID_PRODUCTO
            WHERE p.ESTADO = 'A' ORDER BY p.CODIGO_SKU");
        $stmt->execute([':h1' => $hoy, ':h2' => $hoy, ':h3' => $hoy]);
        return array_map(function (array $f): array {
            $f['RECIBIDO'] = (int) $f['RECIBIDO'];
            $f['VENDIDO'] = (int) round((float) $f['VENDIDO']);
            $f['MERMAS'] = (int) round((float) $f['MERMAS']);
            $f['STOCK_ACTUAL'] = (int) $f['STOCK_ACTUAL'];
            $f['INICIO'] = $f['STOCK_ACTUAL'] - $f['RECIBIDO'] + $f['VENDIDO'] + $f['MERMAS'];
            return $f;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}
