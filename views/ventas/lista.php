<?php
/** @var array $ventas @var int $confirmar @var Usuario $u */
$titulo = 'Ventas';
$seccion = 'ventas';
require __DIR__ . '/../partials/cabecera.php';

$quien = $u->puedeVerTodasLasTiendas() ? 'de las 3 tiendas' : ($u->puedeCerrarCaja() ? 'de tu tienda' : 'de tus cajas');
?>
<h1>Ventas</h1>
<p class="sub">Ventas <?= $quien ?>. <?= $u->puedeAnularVenta() ? 'R4: una venta no se borra, se anula, y su stock vuelve al inventario.' : 'Para anular una venta, pide ayuda a un Supervisor.' ?></p>
<?= aviso() ?>
<div class="tabla"><table>
    <thead><tr><th>Comprobante</th><th>Fecha</th><th>Tienda · caja</th><th>Cajero</th><th>Cliente</th><th class="num">Total</th><th>Estado</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($ventas as $v): $id = (int) $v['ID_VENTA']; ?>
        <tr>
            <td class="nw"><b><?= e($v['SERIE'] . '-' . $v['NUMERO']) ?></b></td>
            <td class="nw"><?= fecha($v['FECHA_HORA']) ?></td>
            <td><?= e($v['TIENDA']) ?> · N° <?= (int) $v['NUMERO_CAJA'] ?></td>
            <td><?= e(nombreCorto($v['CAJERO_NOMBRES'], $v['CAJERO_APELLIDOS'])) ?></td>
            <td><?= $v['ID_CLIENTE'] ? e($v['NOMBRE_RAZON_SOCIAL']) : 'Cliente varios' ?></td>
            <td class="num"><?= sol((float) $v['TOTAL']) ?></td>
            <td><?= tag($v['ESTADO'], $v['ESTADO'] === 'EMITIDA' ? 'ok' : 'bad') ?></td>
            <td class="nw"><div class="botones">
                <a class="btn sm ghost" href="?accion=comprobante&amp;id=<?= $id ?>">Ver</a>
                <?php if ($u->puedeAnularVenta() && $v['ESTADO'] === 'EMITIDA'): ?>
                    <?php if ($confirmar === $id): ?>
                        <form class="confirma" method="post" action="?accion=anular">
                            <input type="hidden" name="csrf" value="<?= e(AuthController::token()) ?>">
                            <input type="hidden" name="id" value="<?= $id ?>">
                            ¿Anular? <button class="btn sm rojo">Sí</button><a class="btn sm ghost" href="?accion=ventas">No</a>
                        </form>
                    <?php else: ?>
                        <a class="btn sm peligro" href="?accion=ventas&amp;id=<?= $id ?>">Anular</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$ventas): ?><tr><td colspan="8">Todavía no hay ventas.</td></tr><?php endif; ?>
    </tbody>
</table></div>

<?php require __DIR__ . '/../partials/pie.php'; ?>
