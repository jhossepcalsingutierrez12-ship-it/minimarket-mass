<?php
declare(strict_types=1);
require_once __DIR__ . '/Repositorio.php';

/**
 * Reportes para el Administrador. Son las mismas preguntas de database/consultas.sql,
 * calculadas con los datos actuales (si vendes o anulas, cambian).
 */
class ReporteRepository extends Repositorio
{
    public function resumen(): array
    {
        $v = $this->pdo->query("
            SELECT COALESCE(SUM(CASE WHEN ESTADO = 'EMITIDA' THEN TOTAL END), 0) AS TOTAL,
                   COALESCE(SUM(CASE WHEN ESTADO = 'EMITIDA' THEN 1 ELSE 0 END), 0) AS EMITIDAS,
                   COALESCE(SUM(CASE WHEN ESTADO = 'ANULADA' THEN 1 ELSE 0 END), 0) AS ANULADAS
            FROM VENTA")->fetch(PDO::FETCH_ASSOC);
        $mermas = (float) $this->pdo->query('SELECT COALESCE(SUM(VALOR_TOTAL), 0) FROM MERMA')->fetchColumn();
        // Lotes vencidos o que vencen en los próximos 30 días
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM LOTE WHERE FECHA_VENCIMIENTO IS NOT NULL AND FECHA_VENCIMIENTO <= :limite');
        $stmt->execute([':limite' => date('Y-m-d', strtotime('+30 days'))]);
        $emitidas = (int) $v['EMITIDAS'];
        return [
            'total'     => (float) $v['TOTAL'],
            'emitidas'  => $emitidas,
            'anuladas'  => (int) $v['ANULADAS'],
            'promedio'  => $emitidas ? (float) $v['TOTAL'] / $emitidas : 0.0,
            'mermas'    => $mermas,
            'porVencer' => (int) $stmt->fetchColumn(),
        ];
    }

    /** @return array<string, float> */
    public function ventasPorTienda(): array
    {
        return $this->pares("
            SELECT t.NOMBRE, COALESCE(SUM(CASE WHEN v.ESTADO = 'EMITIDA' THEN v.TOTAL END), 0)
            FROM TIENDA t
            LEFT JOIN CAJA c        ON c.ID_TIENDA = t.ID_TIENDA
            LEFT JOIN SESION_CAJA s ON s.ID_CAJA = c.ID_CAJA
            LEFT JOIN VENTA v       ON v.ID_SESION = s.ID_SESION
            GROUP BY t.ID_TIENDA, t.NOMBRE ORDER BY t.ID_TIENDA");
    }

    public function metodosDePago(): array
    {
        return $this->pares("
            SELECT m.NOMBRE, SUM(p.MONTO) AS T
            FROM PAGO p JOIN VENTA v ON v.ID_VENTA = p.ID_VENTA
            JOIN METODO_PAGO m ON m.ID_METODO_PAGO = p.ID_METODO_PAGO
            WHERE v.ESTADO = 'EMITIDA' GROUP BY m.ID_METODO_PAGO, m.NOMBRE ORDER BY T DESC");
    }

    public function masVendidos(int $limite = 6): array
    {
        $stmt = $this->pdo->prepare("
            SELECT p.NOMBRE, SUM(d.CANTIDAD) AS U
            FROM DETALLE_VENTA d JOIN VENTA v ON v.ID_VENTA = d.ID_VENTA
            JOIN PRODUCTO p ON p.ID_PRODUCTO = d.ID_PRODUCTO
            WHERE v.ESTADO = 'EMITIDA' GROUP BY p.ID_PRODUCTO, p.NOMBRE ORDER BY U DESC LIMIT :lim");
        $stmt->bindValue(':lim', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return array_map('floatval', $stmt->fetchAll(PDO::FETCH_KEY_PAIR));
    }

    private function pares(string $sql): array
    {
        return array_map('floatval', $this->pdo->query($sql)->fetchAll(PDO::FETCH_KEY_PAIR));
    }
}
