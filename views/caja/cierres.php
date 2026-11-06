<?php
/** Cierres de caja: el Administrador los revisa, no cierra cajas @var array $lista @var array $f @var array $tiendas */
$titulo = 'Cierres de caja';
$seccion = 'cierres';
require __DIR__ . '/../partials/cabecera.php';

$faltante = $sobrante = 0.0;
$cuadran = 0;
foreach ($lista as $s) {
    $d = (float) $s['DIFERENCIA'];
    if ($d < 0) {
        $faltante += $d;
    } elseif ($d > 0) {
        $sobrante += $d;
    } else {
        $cuadran++;
    }
}
?>
<h1>Cierres de caja</h1>
<p class="sub">Revisa cómo se cerraron las cajas de las 3 tiendas: cuánto debía haber, cuánto se contó, la diferencia y quién cerró.
    Tú solo revisas: contar y cerrar lo hace el Supervisor.</p>
<?php $ayuda = [
    'Muestra cómo se <b>cerró cada caja</b> de las 3 tiendas: con cuánto abrió, cuánto debía haber, cuánto contó el Supervisor y la <b>diferencia</b>.',
    '<b>Faltante</b> (rojo) = falta dinero; <b>sobrante</b> = hay de más; <b>cuadra</b> = exacto. También dice <b>quién cerró</b> la caja.',
    'Tú solo <b>revisas</b>: contar el dinero y cerrar la caja lo hace el Supervisor. Usa «Solo los que no cuadraron» para auditar rápido.',
]; require __DIR__ . '/../partials/ayuda.php'; ?>
<?= aviso() ?>

<form class="filtros" method="get">
    <input type="hidden" name="accion" value="cierres">
    <select name="tienda" aria-label="Tienda">
        <option value="">Las 3 tiendas</option>
        <?php foreach ($tiendas as $t): ?>
            <option value="<?= (int) $t['ID_TIENDA'] ?>" <?= $f['tienda'] === (int) $t['ID_TIENDA'] ? 'selected' : '' ?>><?= e($t['NOMBRE']) ?></option>
        <?php endforeach; ?>
    </select>
    <label class="fecha-filtro">Desde <input type="date" name="desde" value="<?= e($f['desde']) ?>"></label>
    <label class="fecha-filtro">Hasta <input type="date" name="hasta" value="<?= e($f['hasta']) ?>"></label>
    <label class="check-filtro"><input type="checkbox" name="diferencia" value="1" <?= $f['diferencia'] ? 'checked' : '' ?>> Solo los que no cuadraron</label>
    <button class="btn sm" type="submit">Filtrar</button>
    <a class="btn sm ghost" href="?accion=cierres">Limpiar</a>
</form>

<div class="kpis">
    <div class="card kpi"><b><?= count($lista) ?></b><span>Cierres</span></div>
    <div class="card kpi"><b><?= $cuadran ?></b><span>Cuadraron exacto</span></div>
    <div class="card kpi <?= $faltante < 0 ? 'kpi-mal' : '' ?>"><b><?= sol($faltante) ?></b><span>Total faltante</span></div>
    <div class="card kpi"><b><?= sol($sobrante) ?></b><span>Total sobrante</span></div>
</div>

<div class="tabla"><table>
    <thead><tr><th>Sesión</th><th>Tienda · caja</th><th>Cajero</th><th>Turno</th><th>Cierre</th><th class="num">Apertura</th>
        <th class="num">Ventas en efectivo</th><th class="num">Debía haber</th><th class="num">Contado</th><th class="num">Diferencia</th><th>Resultado</th><th>Cerró</th></tr></thead>
    <tbody>
    <?php foreach ($lista as $s):
        $d = (float) $s['DIFERENCIA'];
        $sistema = (float) $s['EFECTIVO_SISTEMA']; ?>
        <tr>
            <td><?= (int) $s['ID_SESION'] ?></td>
            <td><?= e($s['TIENDA']) ?> · N° <?= (int) $s['NUMERO_CAJA'] ?></td>
            <td><?= e(nombreCorto($s['CAJERO_NOMBRES'], $s['CAJERO_APELLIDOS'])) ?></td>
            <td><?= e($s['TURNO']) ?></td>
            <td class="nw"><?= fecha($s['FECHA_CIERRE']) ?></td>
            <td class="num"><?= sol((float) $s['MONTO_APERTURA']) ?></td>
            <td class="num"><?= sol($sistema - (float) $s['MONTO_APERTURA']) ?></td>
            <td class="num"><b><?= sol($sistema) ?></b></td>
            <td class="num"><?= sol((float) $s['EFECTIVO_CONTADO']) ?></td>
            <td class="num <?= $d < 0 ? 'neg' : ($d > 0 ? 'posi' : '') ?>"><b><?= sol($d) ?></b></td>
            <td><?= $d < 0 ? tag('Faltante', 'bad') : ($d > 0 ? tag('Sobrante', 'warn') : tag('Cuadra', 'ok')) ?></td>
            <td><?= e(nombreCorto($s['CERRO_NOMBRES'] ?? null, $s['CERRO_APELLIDOS'] ?? null)) ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$lista): ?><tr><td colspan="12">No hay cierres con ese filtro.</td></tr><?php endif; ?>
    </tbody>
</table></div>
<p class="sub" style="font-size:.8rem">Debía haber = monto de apertura + ventas pagadas en efectivo. Diferencia = contado − debía haber:
    negativa es <b>faltante</b>, positiva es <b>sobrante</b>. Yape, Plin y tarjeta no entran a la caja.</p>

<?php require __DIR__ . '/../partials/pie.php'; ?>
