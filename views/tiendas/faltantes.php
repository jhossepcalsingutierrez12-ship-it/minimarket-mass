<?php
/**
 * Faltantes de caja (solo Administrador): cajas que el Supervisor cerró con menos efectivo del que dice el sistema.
 * @var array $lista @var string $desde @var int $nuevos @var float $total
 */
$titulo = 'Faltantes de caja';
$seccion = 'faltantes';
require __DIR__ . '/../partials/cabecera.php';
$tiendasCon = count(array_unique(array_column($lista, 'TIENDA')));
?>
<h1>Faltantes de caja</h1>
<p class="sub">Cada vez que un Supervisor cierra una caja y cuenta <b>menos dinero</b> del que dice el sistema, te llega un aviso aquí (la campana de arriba) y por correo.</p>
<?php $ayuda = [
    'Al cerrar una caja, el Supervisor cuenta el efectivo. Si cuenta <b>menos</b> de lo que dice el sistema, es un <b>faltante</b> y te llega el aviso al momento.',
    'La <b>campana</b> de arriba muestra cuántos faltantes nuevos hay. Al abrir esta página quedan como vistos.',
    'Revisa con el Supervisor y el cajero de esa caja qué pasó (un vuelto mal dado, una venta mal cobrada o un posible robo).',
]; require __DIR__ . '/../partials/ayuda.php'; ?>
<?= aviso() ?>

<div class="kpis">
    <div class="card kpi <?= $nuevos ? 'kpi-mal' : '' ?>"><b><?= $nuevos ?></b><span>Nuevos desde tu última revisión</span></div>
    <div class="card kpi <?= $lista ? 'kpi-ojo' : '' ?>"><b><?= count($lista) ?></b><span>Faltantes en 30 días</span></div>
    <div class="card kpi <?= $total > 0 ? 'kpi-mal' : '' ?>"><b><?= sol($total) ?></b><span>Dinero que faltó</span></div>
    <div class="card kpi"><b><?= $tiendasCon ?></b><span>Tiendas con faltantes</span></div>
</div>

<?php if (!$lista): ?>
    <?= vacio('Sin faltantes de caja', 'En los últimos 30 días todas las cajas se cerraron completas o con sobrante.', 'ok') ?>
<?php else: ?>
    <div class="tabla"><table>
        <thead><tr><th>Cierre</th><th>Tienda y caja</th><th>Cajero</th><th>Cerró</th>
            <th class="num">Sistema</th><th class="num">Contado</th><th class="num">Falta</th></tr></thead>
        <tbody>
        <?php foreach ($lista as $f): $nuevo = $f['FECHA_CIERRE'] > $desde; ?>
            <tr class="<?= $nuevo ? 'fila-nueva' : '' ?>">
                <td class="nw"><?= fecha($f['FECHA_CIERRE']) ?><?= $nuevo ? '<br>' . tag('Nuevo', 'bad') : '' ?></td>
                <td><b><?= e($f['TIENDA']) ?></b><br><span class="sub" style="font-size:.8rem">Caja N° <?= (int) $f['NUMERO_CAJA'] ?> · sesión <?= (int) $f['ID_SESION'] ?></span></td>
                <td><?= e(nombreCorto($f['CAJERO_NOMBRES'], $f['CAJERO_APELLIDOS'])) ?><br><span class="sub" style="font-size:.8rem">Turno <?= e($f['TURNO']) ?></span></td>
                <td><?= e(nombreCorto($f['SUP_NOMBRES'], $f['SUP_APELLIDOS'])) ?></td>
                <td class="num"><?= sol((float) $f['EFECTIVO_SISTEMA']) ?></td>
                <td class="num"><?= sol((float) $f['EFECTIVO_CONTADO']) ?></td>
                <td class="num"><b class="tag bad"><?= sol(-(float) $f['DIFERENCIA']) ?></b></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
<?php endif; ?>
<p><a class="btn sm ghost" href="?accion=caja"><?= icono('caja') ?> Ver el dinero de las cajas</a></p>

<?php require __DIR__ . '/../partials/pie.php'; ?>
