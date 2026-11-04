<?php
/** @var array $resumen @var array $porTienda @var array $metodos @var array $top @var Producto[] $reponer */
$titulo = 'Reportes';
$seccion = 'reportes';
require __DIR__ . '/../partials/cabecera.php';

// Barras horizontales hechas con CSS (sin librerías)
$barras = function (array $datos, callable $formato): string {
    if (!$datos) {
        return '<p class="sub">Sin datos.</p>';
    }
    $max = max(max($datos), 1);
    $html = '';
    foreach ($datos as $nombre => $valor) {
        $html .= '<div class="barra-fila"><span class="n" title="' . e((string) $nombre) . '">' . e((string) $nombre) . '</span>'
               . '<span class="pista-b"><i style="width:' . number_format($valor / $max * 100, 1, '.', '') . '%"></i></span>'
               . '<span class="num">' . $formato($valor) . '</span></div>';
    }
    return $html;
};
?>
<h1>Reportes</h1>
<p class="sub">Se calculan con los datos actuales: si vendes o anulas, cambian.</p>
<div class="kpis">
    <div class="card kpi"><b><?= sol($resumen['total']) ?></b><span>Total vendido</span></div>
    <div class="card kpi"><b><?= $resumen['emitidas'] ?></b><span>Ventas emitidas</span></div>
    <div class="card kpi"><b><?= $resumen['anuladas'] ?></b><span>Ventas anuladas (R4)</span></div>
    <div class="card kpi"><b><?= sol($resumen['promedio']) ?></b><span>Ticket promedio</span></div>
    <div class="card kpi"><b><?= sol($resumen['mermas']) ?></b><span>Valor en mermas</span></div>
    <div class="card kpi"><b><?= $resumen['porVencer'] ?></b><span>Lotes vencidos o por vencer</span></div>
</div>
<div class="dos par">
    <div class="card"><h3 style="margin-top:0">Ventas por tienda</h3><?= $barras($porTienda, 'sol') ?></div>
    <div class="card"><h3 style="margin-top:0">Métodos de pago</h3><?= $barras($metodos, 'sol') ?></div>
    <div class="card"><h3 style="margin-top:0">Más vendidos (unidades)</h3><?= $barras($top, fn($v) => ($v + 0) . ' und.') ?></div>
    <div class="card"><h3 style="margin-top:0">Reposición</h3>
        <?php if ($reponer): ?>
            <div class="tabla" style="margin:0"><table>
                <thead><tr><th>Producto</th><th class="num">Stock</th><th class="num">Mín.</th><th>Estado</th></tr></thead>
                <tbody>
                <?php foreach ($reponer as $p): ?>
                    <tr><td><?= e($p->getNombre()) ?></td><td class="num"><?= $p->getStockActual() ?></td><td class="num"><?= $p->getStockMinimo() ?></td><td><span class="tag <?= e($p->estadoStock()) ?>"><?= e($p->estadoStock()) ?></span></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php else: ?><p class="sub">Todo el stock está normal.</p><?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/../partials/pie.php'; ?>
