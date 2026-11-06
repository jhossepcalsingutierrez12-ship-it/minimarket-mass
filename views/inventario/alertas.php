<?php
/** Alertas inteligentes de inventario (solo Administrador) @var array $a */
$titulo = 'Alertas de inventario';
$seccion = 'alertas';
require __DIR__ . '/../partials/cabecera.php';
$total = count($a['agotados']) + count($a['poco']) + count($a['vencidos']) + count($a['porVencer']);

// Tabla de productos con reposición recomendada
$tablaReponer = function (array $filas): string {
    $h = '<div class="tabla"><table><thead><tr><th>Producto</th><th class="num">Stock</th><th class="num">Mínimo</th>'
       . '<th class="num">Vende por día</th><th class="num">Meta</th><th class="num">Reponer</th></tr></thead><tbody>';
    foreach ($filas as $x) {
        $h .= '<tr><td>' . e($x['NOMBRE']) . '<br><span class="sku">' . e($x['CODIGO_SKU']) . '</span></td>'
            . '<td class="num">' . (int) $x['STOCK_ACTUAL'] . '</td><td class="num">' . (int) $x['STOCK_MINIMO'] . '</td>'
            . '<td class="num">' . number_format((float) $x['POR_DIA'], 1) . '</td><td class="num">' . (int) $x['META'] . '</td>'
            . '<td class="num"><b class="tag warn">+' . (int) $x['REPONER'] . ' und.</b></td></tr>';
    }
    return $h . '</tbody></table></div>';
};
?>
<h1>Alertas de inventario</h1>
<p class="sub">Se revisan solas cada vez que abres esta página: productos agotados, con poco stock y lotes vencidos o por vencer.</p>
<?php $ayuda = [
    'Te avisa <b>sola</b> qué productos están <b>agotados</b> (0 unidades) o con <b>poco stock</b> (en su mínimo o debajo), sin tener que revisar el catálogo uno por uno.',
    'Para cada uno te <b>recomienda cuántas unidades reponer</b>, según su stock mínimo y lo que se vende por día.',
    'También muestra los <b>lotes vencidos o que vencen en 30 días</b> y qué hacer con cada uno (retirarlo, ponerlo en oferta o venderlo primero).',
]; require __DIR__ . '/../partials/ayuda.php'; ?>
<?= aviso() ?>

<div class="kpis">
    <div class="card kpi <?= $a['agotados'] ? 'kpi-mal' : '' ?>"><b><?= count($a['agotados']) ?></b><span>Agotados</span></div>
    <div class="card kpi <?= $a['poco'] ? 'kpi-ojo' : '' ?>"><b><?= count($a['poco']) ?></b><span>Con poco stock</span></div>
    <div class="card kpi <?= $a['vencidos'] ? 'kpi-mal' : '' ?>"><b><?= count($a['vencidos']) ?></b><span>Lotes vencidos</span></div>
    <div class="card kpi <?= $a['porVencer'] ? 'kpi-ojo' : '' ?>"><b><?= count($a['porVencer']) ?></b><span>Lotes por vencer (30 días)</span></div>
</div>

<?php if ($total === 0): ?>
    <div class="msg ok">Todo en orden: no hay productos agotados, con poco stock ni lotes por vencer.</div>
<?php endif; ?>

<?php if ($a['agotados']): ?>
    <h2 class="sec">Agotados</h2>
    <p class="sub">No queda nada en stock: no se pueden vender.</p>
    <?= $tablaReponer($a['agotados']) ?>
<?php endif; ?>

<?php if ($a['poco']): ?>
    <h2 class="sec">Poco stock</h2>
    <p class="sub">Están en su stock mínimo o debajo.</p>
    <?= $tablaReponer($a['poco']) ?>
<?php endif; ?>

<?php if ($a['agotados'] || $a['poco']): ?>
    <div class="msg info">Cantidad recomendada para reponer = meta − stock actual. La meta es lo mayor entre <b>2 × el stock mínimo</b> y lo que se vende en
        <b>14 días</b> (promedio de ventas de los últimos 30 días). Regístralo en <a href="?accion=lotes">Lotes y vencimientos</a> cuando llegue la mercadería.</div>
<?php endif; ?>

<?php if ($a['vencidos'] || $a['porVencer']): ?>
    <h2 class="sec">Vencidos y próximos a vencer</h2>
    <div class="tabla"><table>
        <thead><tr><th>Producto</th><th>Lote</th><th>Vence</th><th>Estado</th><th class="num">Recibido</th><th>Qué hacer</th></tr></thead>
        <tbody>
        <?php foreach (array_merge($a['vencidos'], $a['porVencer']) as $l): $d = (int) $l['DIAS']; ?>
            <tr>
                <td><?= e($l['NOMBRE']) ?><br><span class="sku"><?= e($l['CODIGO_SKU']) ?></span></td>
                <td class="nw sku"><?= e($l['CODIGO_LOTE'] ?? '—') ?></td>
                <td class="nw"><?= fecha($l['FECHA_VENCIMIENTO']) ?></td>
                <td><?= $d < 0 ? tag('Vencido hace ' . (-$d) . ' día' . ($d === -1 ? '' : 's'), 'bad')
                        : tag($d === 0 ? 'Vence hoy' : 'Vence en ' . $d . ' día' . ($d === 1 ? '' : 's'), $d <= 7 ? 'bad' : 'warn') ?></td>
                <td class="num"><?= (int) $l['CANTIDAD_RECIBIDA'] ?></td>
                <td style="font-size:.85rem"><?= $d < 0 ? 'Retirar de góndola; el Supervisor registra la merma.' : ($d <= 7 ? 'Poner adelante en góndola o en oferta.' : 'Vigilar: vender primero este lote.') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
<?php endif; ?>

<?php require __DIR__ . '/../partials/pie.php'; ?>
