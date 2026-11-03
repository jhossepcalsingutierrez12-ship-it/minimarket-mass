<?php
/** @var array $tiendas @var array $zonas @var array $personal @var ?int $filtro */
$titulo = 'Tiendas y personal';
$seccion = 'tiendas';
require __DIR__ . '/../partials/cabecera.php';

$itse = function (array $t): string {
    if (!$t['NRO_CERTIFICADO_ITSE']) {
        return tag('Sin certificado ITSE', 'bad');
    }
    $d = diasHasta($t['FECHA_VENC_ITSE']);
    return $d < 0 ? tag('ITSE vencido', 'bad') : ($d <= 60 ? tag("ITSE vence en $d días", 'warn') : tag('ITSE vigente', 'ok'));
};
?>
<h1>Tiendas y personal</h1>
<p class="sub">Las 3 tiendas de Arequipa, sus zonas y quién trabaja en cada una.</p>
<div class="tiendas">
    <?php foreach ($tiendas as $t): ?>
        <div class="card">
            <span class="cod"><?= e($t['CODIGO_TIENDA']) ?></span>
            <h3 style="margin:8px 0 6px"><?= e($t['NOMBRE']) ?></h3>
            <p><?= e($t['DIRECCION']) ?>, <?= e($t['DISTRITO']) ?></p>
            <p>Horario <?= e($t['HORA_APERTURA']) ?> – <?= e($t['HORA_CIERRE']) ?></p>
            <p><?= $t['TELEFONO'] ? 'Tel. ' . e($t['TELEFONO']) : 'Sin teléfono registrado' ?></p>
            <p><?= (float) $t['AREA_M2'] + 0 ?> m² · aforo <?= (int) $t['AFORO'] ?> personas · desde <?= fecha($t['FECHA_APERTURA']) ?></p>
            <p><?= $itse($t) ?> <?= $t['NRO_CERTIFICADO_ITSE'] ? '<span class="sku">' . e($t['NRO_CERTIFICADO_ITSE']) . ' · vence ' . fecha($t['FECHA_VENC_ITSE']) . '</span>' : '' ?></p>
            <p style="margin-top:8px"><b><?= (int) $t['EMPLEADOS'] ?></b> empleados activos · <b><?= (int) $t['CAJAS'] ?></b> cajas</p>
            <div class="zonas">
                <?php foreach ($zonas as $z): if ((int) $z['ID_TIENDA'] !== (int) $t['ID_TIENDA']) continue; ?>
                    <?= tag($z['NOMBRE'], $z['ES_ZONA_SEGURA'] === 'S' ? 'ok' : 'gris') ?>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<h2 class="sec">Personal</h2>
<form class="filtros" method="get">
    <input type="hidden" name="accion" value="tiendas">
    <select name="tienda" aria-label="Filtrar por tienda" data-enviar>
        <option value="">Todas las tiendas</option>
        <?php foreach ($tiendas as $t): ?>
            <option value="<?= (int) $t['ID_TIENDA'] ?>" <?= $filtro === (int) $t['ID_TIENDA'] ? 'selected' : '' ?>><?= e($t['NOMBRE']) ?></option>
        <?php endforeach; ?>
    </select>
    <noscript><button class="btn sm">Filtrar</button></noscript>
</form>
<div class="tabla"><table>
    <thead><tr><th>Empleado</th><th>Cargo</th><th>Tienda</th><th>Jefe directo</th><th>Ingreso</th><th>Estado</th></tr></thead>
    <tbody>
    <?php foreach ($personal as $p): ?>
        <tr>
            <td><?= e($p['NOMBRES'] . ' ' . $p['APELLIDOS']) ?></td>
            <td><?= e($p['CARGO']) ?></td>
            <td><?= e($p['TIENDA']) ?></td>
            <td><?= e(nombreCorto($p['JEFE_NOMBRES'], $p['JEFE_APELLIDOS'])) ?></td>
            <td class="nw"><?= fecha($p['FECHA_INGRESO']) ?></td>
            <td><?= tag($p['ESTADO'] === 'A' ? 'Activo' : 'Inactivo', $p['ESTADO'] === 'A' ? 'ok' : 'gris') ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<p class="sub" style="font-size:.8rem">Por privacidad no se muestran DNI, sueldo, grupo sanguíneo ni contacto familiar, aunque estén en la tabla EMPLEADO.</p>

<?php require __DIR__ . '/../partials/pie.php'; ?>
