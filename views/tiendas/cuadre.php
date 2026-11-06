<?php
/** Cuadre de stock del día (solo Administrador) @var array $filas */
$titulo = 'Cuadre de stock';
$seccion = 'cuadre';
require __DIR__ . '/../partials/cabecera.php';

$tot = ['RECIBIDO' => 0, 'VENDIDO' => 0, 'MERMAS' => 0];
foreach ($filas as $f) {
    foreach ($tot as $k => $v) {
        $tot[$k] += $f[$k];
    }
}
?>
<h1>Cuadre de stock</h1>
<p class="sub">Hoy, <?= date('d/m/Y') ?>. Inicio del día + recibido − vendido − mermas = stock en el sistema.
    Escribe lo que contaste en la tienda y el sistema te dice si cuadra.</p>
<?php $ayuda = [
    'Revisa si el <b>stock del día cuadra</b>: con cuánto empezó cada producto, cuánto entró (lotes y compras), cuánto se vendió y cuánto se perdió en mermas.',
    'Escribe lo que <b>contaste en la tienda</b> y el sistema te dice si cuadra o si <b>faltan</b> o <b>sobran</b> unidades.',
    'Si falta algo sin venta ni merma que lo explique, revisa <a href="?accion=incidentes">Incidentes</a>: puede ser un robo.',
]; require __DIR__ . '/../partials/ayuda.php'; ?>


<div class="kpis">
    <div class="card kpi"><b><?= $tot['RECIBIDO'] ?></b><span>Unidades recibidas hoy (lotes)</span></div>
    <div class="card kpi"><b><?= $tot['VENDIDO'] ?></b><span>Unidades vendidas hoy</span></div>
    <div class="card kpi"><b><?= $tot['MERMAS'] ?></b><span>Unidades en mermas hoy</span></div>
    <div class="card kpi"><b id="noCuadran">—</b><span>Productos que no cuadran</span></div>
</div>

<div class="tabla"><table id="tablaCuadre">
    <thead><tr><th>Producto</th><th class="num">Inicio del día</th><th class="num">+ Recibido</th><th class="num">− Vendido</th><th class="num">− Mermas</th><th class="num">Stock en sistema</th><th>Conteo físico</th><th>Resultado</th></tr></thead>
    <tbody>
    <?php foreach ($filas as $f): ?>
        <tr data-sistema="<?= $f['STOCK_ACTUAL'] ?>">
            <td><?= e($f['NOMBRE']) ?><br><span class="sku"><?= e($f['CODIGO_SKU']) ?></span></td>
            <td class="num"><?= $f['INICIO'] ?></td>
            <td class="num"><?= $f['RECIBIDO'] ? '+' . $f['RECIBIDO'] : '0' ?></td>
            <td class="num"><?= $f['VENDIDO'] ? '−' . $f['VENDIDO'] : '0' ?></td>
            <td class="num"><?= $f['MERMAS'] ? '−' . $f['MERMAS'] : '0' ?></td>
            <td class="num"><b><?= $f['STOCK_ACTUAL'] ?></b></td>
            <td><input class="qty conteo" type="number" min="0" inputmode="numeric" aria-label="Conteo físico de <?= e($f['NOMBRE']) ?>"></td>
            <td class="resultado"><span class="sub" style="margin:0">Sin contar</span></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<p class="sub" style="font-size:.8rem">El conteo físico no se guarda: sirve para revisar en el momento. Si algo falta y no hay venta ni merma que lo explique,
    revisa los incidentes (puede ser un robo) y pide al Supervisor que registre la merma.</p>

<?php require __DIR__ . '/../partials/pie.php'; ?>
