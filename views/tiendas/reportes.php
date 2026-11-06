<?php
/**
 * Reportes del mes (solo Administrador): volumen de ventas, no ventas una por una.
 * @var array $f @var array $meses @var array $tiendas @var array $resumen @var array $serie @var array $comparacion
 * @var array $metodos @var array $top @var array $menor @var array $productos
 */
$titulo = 'Reportes';
$seccion = 'reportes';
require __DIR__ . '/../partials/cabecera.php';

// Barras horizontales hechas con CSS (sin librerías)
$barras = function (array $datos, callable $formato): string {
    if (!$datos || max($datos) <= 0) {
        return '<p class="sub">Sin ventas en este mes.</p>';
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
$nombreTienda = 'las 3 tiendas';
foreach ($tiendas as $t) {
    if ((int) $t['ID_TIENDA'] === $f['tienda']) {
        $nombreTienda = $t['NOMBRE'];
    }
}
$totalTiendas = array_sum(array_column($comparacion, 'TOTAL'));
$ganancias = array_filter($productos, fn($x) => $x['UNIDADES'] > 0);
usort($ganancias, fn($a, $b) => $b['GANANCIA'] <=> $a['GANANCIA']);
?>
<h1>Reportes</h1>
<p class="sub">Volumen de ventas de <b><?= e($nombreTienda) ?></b> en <b><?= e($meses[$f['mes']]) ?></b>
    (totales del mes, no ventas una por una). Solo cuentan las ventas emitidas.</p>
<?php $ayuda = [
    'Muestra el <b>volumen de ventas del mes</b> (totales), no las ventas una por una. Elige el <b>mes</b> y la <b>tienda</b>: todo lo de abajo cambia según lo que elijas.',
    '<b>Compara las 3 tiendas</b>, muestra los productos más vendidos y los métodos de pago.',
    'Calcula la <b>ganancia estimada</b> de cada producto (venta sin IGV − costo) y los productos con <b>menor movimiento</b>, para decidir ofertas o comprar menos.',
]; require __DIR__ . '/../partials/ayuda.php'; ?>

<form class="filtros" method="get">
    <input type="hidden" name="accion" value="reportes">
    <select name="mes" aria-label="Mes" data-enviar>
        <?php foreach ($meses as $k => $txt): ?>
            <option value="<?= e($k) ?>" <?= $f['mes'] === $k ? 'selected' : '' ?>><?= e($txt) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="tienda" aria-label="Tienda" data-enviar>
        <option value="">Las 3 tiendas</option>
        <?php foreach ($tiendas as $t): ?>
            <option value="<?= (int) $t['ID_TIENDA'] ?>" <?= $f['tienda'] === (int) $t['ID_TIENDA'] ? 'selected' : '' ?>><?= e($t['NOMBRE']) ?></option>
        <?php endforeach; ?>
    </select>
    <noscript><button class="btn sm" type="submit">Ver reporte</button></noscript>
</form>

<div class="kpis">
    <div class="card kpi"><b><?= sol($resumen['total']) ?></b><span>Total vendido</span></div>
    <div class="card kpi"><b><?= $resumen['emitidas'] ?></b><span>Ventas emitidas</span></div>
    <div class="card kpi"><b><?= sol($resumen['promedio']) ?></b><span>Ticket promedio</span></div>
    <div class="card kpi"><b><?= sol($resumen['ganancia']) ?></b><span>Ganancia estimada</span></div>
    <div class="card kpi"><b><?= sol($resumen['mermas']) ?></b><span>Valor en mermas</span></div>
    <div class="card kpi"><b><?= $resumen['anuladas'] ?></b><span>Ventas anuladas (R4)</span></div>
</div>

<div class="card" style="margin-bottom:18px">
    <h3 style="margin-top:0">Ventas de cada mes · <?= e($nombreTienda) ?></h3>
    <p class="sub" style="margin:-6px 0 8px;font-size:.82rem">Últimos 6 meses hasta <?= e($meses[$f['mes']]) ?>, para comparar cómo va el mes elegido.</p>
    <?= $barras(array_map(fn($x) => $x['total'], $serie), 'sol') ?>
</div>

<h2 class="sec">Comparación entre las 3 tiendas · <?= e($meses[$f['mes']]) ?></h2>
<?php if ($f['tienda']): ?><p class="sub">Esta tabla siempre muestra las 3 tiendas; la que elegiste está resaltada. Lo demás de la página es solo de <?= e($nombreTienda) ?>.</p><?php endif; ?>
<div class="tabla"><table>
    <thead><tr><th>Tienda</th><th class="num">Vendido</th><th class="num">N° de ventas</th><th class="num">Ticket promedio</th><th class="num">Mermas</th><th style="min-width:160px">Parte del total</th></tr></thead>
    <tbody>
    <?php foreach ($comparacion as $c): $pct = $totalTiendas > 0 ? $c['TOTAL'] / $totalTiendas * 100 : 0; ?>
        <tr class="<?= $f['tienda'] === $c['ID_TIENDA'] ? 'fila-tienda' : '' ?>">
            <td><b><?= e($c['NOMBRE']) ?></b></td>
            <td class="num"><?= sol($c['TOTAL']) ?></td>
            <td class="num"><?= $c['VENTAS'] ?></td>
            <td class="num"><?= sol($c['VENTAS'] ? $c['TOTAL'] / $c['VENTAS'] : 0) ?></td>
            <td class="num"><?= sol($c['MERMAS']) ?></td>
            <td><span class="pista-b" style="display:inline-block;width:100px;vertical-align:middle"><i style="width:<?= number_format($pct, 1, '.', '') ?>%"></i></span> <?= number_format($pct, 1) ?> %</td>
        </tr>
    <?php endforeach; ?>
    <tr class="fila-total"><td><b>Total</b></td><td class="num"><b><?= sol($totalTiendas) ?></b></td><td class="num"><b><?= array_sum(array_column($comparacion, 'VENTAS')) ?></b></td><td></td><td class="num"><b><?= sol(array_sum(array_column($comparacion, 'MERMAS'))) ?></b></td><td></td></tr>
    </tbody>
</table></div>

<div class="dos par">
    <div class="card"><h3 style="margin-top:0">Más vendidos (unidades)</h3>
        <?= $barras(array_column($top, 'UNIDADES', 'NOMBRE'), fn($v) => ($v + 0) . ' und.') ?></div>
    <div class="card"><h3 style="margin-top:0">Métodos de pago</h3><?= $barras($metodos, 'sol') ?></div>
</div>

<h2 class="sec">Ganancias estimadas por producto</h2>
<div class="tabla"><table>
    <thead><tr><th>Producto</th><th class="num">Unidades</th><th class="num">Vendido (con IGV)</th><th class="num">Sin IGV</th><th class="num">Costo</th><th class="num">Ganancia</th><th class="num">Margen</th></tr></thead>
    <tbody>
    <?php foreach ($ganancias as $x): $sinIgv = $x['VENTA'] / 1.18; ?>
        <tr>
            <td><?= e($x['NOMBRE']) ?><br><span class="sku"><?= e($x['CODIGO_SKU']) ?></span></td>
            <td class="num"><?= $x['UNIDADES'] ?></td>
            <td class="num"><?= sol($x['VENTA']) ?></td>
            <td class="num"><?= sol($sinIgv) ?></td>
            <td class="num"><?= sol($x['UNIDADES'] * $x['COSTO']) ?></td>
            <td class="num <?= $x['GANANCIA'] < 0 ? 'neg' : 'posi' ?>"><b><?= sol($x['GANANCIA']) ?></b></td>
            <td class="num"><?= $sinIgv > 0 ? number_format($x['GANANCIA'] / $sinIgv * 100, 1) . ' %' : '—' ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$ganancias): ?><tr><td colspan="7">Sin ventas en este mes.</td></tr><?php endif; ?>
    </tbody>
</table></div>
<p class="sub" style="font-size:.8rem">Ganancia estimada = venta sin IGV − unidades × costo. El costo es el del último lote o compra del producto
    (si no tiene, se estima en 75 % del precio sin IGV).</p>

<h2 class="sec">Productos con menor movimiento</h2>
<p class="sub">Los que menos se vendieron en el mes: conviene ponerlos en oferta o comprar menos.</p>
<div class="tabla"><table>
    <thead><tr><th>Producto</th><th class="num">Unidades vendidas</th><th class="num">Stock actual</th><th>Situación</th></tr></thead>
    <tbody>
    <?php foreach ($menor as $x): ?>
        <tr>
            <td><?= e($x['NOMBRE']) ?><br><span class="sku"><?= e($x['CODIGO_SKU']) ?></span></td>
            <td class="num"><?= $x['UNIDADES'] ?></td>
            <td class="num"><?= (int) $x['STOCK_ACTUAL'] ?></td>
            <td><?= $x['UNIDADES'] === 0 ? tag('Sin ventas', 'bad') : tag('Poco movimiento', 'warn') ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>

<?php require __DIR__ . '/../partials/pie.php'; ?>
