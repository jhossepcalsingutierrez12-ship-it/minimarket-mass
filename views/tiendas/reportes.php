<?php
/**
 * Reportes con filtros (solo Administrador): volumen de ventas, no ventas una por una.
 * @var array $f @var array $tiendas @var array $resumen @var array $serie @var array $comparacion
 * @var array $metodos @var array $top @var array $menor @var array $productos
 */
$titulo = 'Reportes';
$seccion = 'reportes';
require __DIR__ . '/../partials/cabecera.php';

// Barras horizontales hechas con CSS (sin librerías)
$barras = function (array $datos, callable $formato): string {
    if (!$datos || max($datos) <= 0) {
        return '<p class="sub">Sin ventas en este periodo.</p>';
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
$periodos = ['hoy' => 'Hoy', '7' => 'Últimos 7 días', '30' => 'Últimos 30 días', 'mes' => 'Este mes', 'todo' => 'Todo', 'rango' => 'Elegir fechas'];
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
<p class="sub">Volumen de ventas de <?= e($nombreTienda) ?> del <b><?= fecha($f['desde']) ?></b> al <b><?= fecha($f['hasta']) ?></b>
    (totales, no ventas una por una). Solo cuentan las ventas emitidas.</p>
<?php $ayuda = [
    'Muestra el <b>volumen de ventas</b> (totales), no las ventas una por una. Elige el periodo, la tienda y si quieres verlo por día, semana o mes.',
    '<b>Compara las 3 tiendas</b>, muestra los productos más vendidos y los métodos de pago.',
    'Calcula la <b>ganancia estimada</b> de cada producto (venta sin IGV − costo) y los productos con <b>menor movimiento</b>, para decidir ofertas o comprar menos.',
]; require __DIR__ . '/../partials/ayuda.php'; ?>

<form class="filtros filtros-reporte" method="get">
    <input type="hidden" name="accion" value="reportes">
    <select name="periodo" aria-label="Periodo" id="periodo">
        <?php foreach ($periodos as $k => $txt): ?>
            <option value="<?= e($k) ?>" <?= $f['periodo'] === (string) $k ? 'selected' : '' ?>><?= e($txt) ?></option>
        <?php endforeach; ?>
    </select>
    <label class="fecha-filtro rango">Desde <input type="date" name="desde" value="<?= e($f['desde']) ?>"></label>
    <label class="fecha-filtro rango">Hasta <input type="date" name="hasta" value="<?= e($f['hasta']) ?>"></label>
    <select name="tienda" aria-label="Tienda">
        <option value="">Las 3 tiendas</option>
        <?php foreach ($tiendas as $t): ?>
            <option value="<?= (int) $t['ID_TIENDA'] ?>" <?= $f['tienda'] === (int) $t['ID_TIENDA'] ? 'selected' : '' ?>><?= e($t['NOMBRE']) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="agrupar" aria-label="Agrupar ventas por">
        <option value="dia" <?= $f['agrupar'] === 'dia' ? 'selected' : '' ?>>Por día</option>
        <option value="semana" <?= $f['agrupar'] === 'semana' ? 'selected' : '' ?>>Por semana</option>
        <option value="mes" <?= $f['agrupar'] === 'mes' ? 'selected' : '' ?>>Por mes</option>
    </select>
    <button class="btn sm" type="submit">Ver reporte</button>
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
    <h3 style="margin-top:0">Ventas <?= ['dia' => 'por día', 'semana' => 'por semana', 'mes' => 'por mes'][$f['agrupar']] ?></h3>
    <?= $barras(array_map(fn($x) => $x['total'], $serie), 'sol') ?>
</div>

<h2 class="sec">Comparación entre las 3 tiendas</h2>
<div class="tabla"><table>
    <thead><tr><th>Tienda</th><th class="num">Vendido</th><th class="num">N° de ventas</th><th class="num">Ticket promedio</th><th class="num">Mermas</th><th style="min-width:160px">Parte del total</th></tr></thead>
    <tbody>
    <?php foreach ($comparacion as $c): $pct = $totalTiendas > 0 ? $c['TOTAL'] / $totalTiendas * 100 : 0; ?>
        <tr>
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
    <?php if (!$ganancias): ?><tr><td colspan="7">Sin ventas en este periodo.</td></tr><?php endif; ?>
    </tbody>
</table></div>
<p class="sub" style="font-size:.8rem">Ganancia estimada = venta sin IGV − unidades × costo. El costo es el del último lote o compra del producto
    (si no tiene, se estima en 75 % del precio sin IGV).</p>

<h2 class="sec">Productos con menor movimiento</h2>
<p class="sub">Los que menos se vendieron en el periodo: conviene ponerlos en oferta o comprar menos.</p>
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
