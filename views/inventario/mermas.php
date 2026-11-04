<?php
/**
 * @var array $lista @var Producto[] $productos @var array $lotesProducto @var array $zonas
 * @var array $motivos @var array $errores @var array $form @var Usuario $u
 */
$titulo = 'Mermas';
$seccion = 'mermas';
require __DIR__ . '/../partials/cabecera.php';

$alcance = $u->puedeVerTodasLasTiendas() ? 'Mermas de las 3 tiendas.' : ($u->puedeAutorizarMerma() ? 'Mermas de tu tienda.' : 'Las mermas que registraste tú.');
$colorMotivo = ['ROBO' => 'bad', 'VENCIMIENTO' => 'warn', 'DETERIORO' => 'gris'];
?>
<h1>Mermas</h1>
<p class="sub"><?= $alcance ?> Productos dañados, vencidos o robados. Las registran cajeros y supervisores; las autoriza un Supervisor, nunca quien la registró (CK_MER_6).</p>
<?= aviso() ?>
<div class="tabla"><table>
    <thead><tr><th>Fecha</th><th>Producto</th><th>Motivo</th><th class="num">Cant.</th><th class="num">Valor</th><th>Registró</th><th>Autorizó</th><th>Estado</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($lista as $m): ?>
        <tr>
            <td class="nw"><?= fecha($m['FECHA']) ?></td>
            <td><?= e($m['NOMBRE']) ?>
                <?php if ($m['CODIGO_LOTE']): ?><br><span class="sku">Lote <?= e($m['CODIGO_LOTE']) ?></span><?php endif; ?>
                <?php if ($m['OBSERVACION']): ?><br><span class="sub" style="font-size:.78rem"><?= e($m['OBSERVACION']) ?></span><?php endif; ?></td>
            <td><?= tag($m['MOTIVO'], $colorMotivo[$m['MOTIVO']] ?? 'gris') ?></td>
            <td class="num"><?= (float) $m['CANTIDAD'] + 0 ?></td>
            <td class="num"><?= sol((float) $m['VALOR_TOTAL']) ?></td>
            <td><?= e(nombreCorto($m['REG_NOMBRES'], $m['REG_APELLIDOS'])) ?></td>
            <td><?= e(nombreCorto($m['AUT_NOMBRES'], $m['AUT_APELLIDOS'])) ?></td>
            <td><?= tag($m['ESTADO'], $m['ESTADO'] === 'APROBADA' ? 'ok' : 'warn') ?></td>
            <td><?php if ($m['ESTADO'] === 'REGISTRADA' && $u->puedeAutorizarMerma()): ?>
                <form method="post" action="?accion=autorizar_merma">
                    <input type="hidden" name="csrf" value="<?= e(AuthController::token()) ?>">
                    <input type="hidden" name="id" value="<?= (int) $m['ID_MERMA'] ?>">
                    <button class="btn sm">Autorizar</button>
                </form>
            <?php endif; ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$lista): ?><tr><td colspan="9">No hay mermas registradas.</td></tr><?php endif; ?>
    </tbody>
</table></div>

<?php if ($u->puedeRegistrarMerma()): ?>
<h2 class="sec">Registrar merma</h2>
<form id="fCambiarProducto" method="get"><input type="hidden" name="accion" value="mermas"></form>
<form class="card form" method="post" action="?accion=mermas" novalidate>
    <input type="hidden" name="csrf" value="<?= e(AuthController::token()) ?>">
    <div class="cols">
        <div class="campo">
            <label for="producto">Producto *</label>
            <select id="producto" name="producto" data-recargar="fCambiarProducto">
                <?php foreach ($productos as $p): ?>
                    <option value="<?= $p->getIdProducto() ?>" <?= (string) $p->getIdProducto() === (string) $form['producto'] ? 'selected' : '' ?>><?= e($p->getCodigoSku() . ' · ' . $p->getNombre() . ' (stock ' . $p->getStockActual() . ')') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo">
            <label for="lote">Lote</label>
            <select id="lote" name="lote">
                <option value="">Sin lote</option>
                <?php foreach ($lotesProducto as $l): ?>
                    <option value="<?= (int) $l['ID_LOTE'] ?>" <?= (string) $l['ID_LOTE'] === (string) $form['lote'] ? 'selected' : '' ?>><?= e(($l['CODIGO_LOTE'] ?? 'Sin código') . ($l['FECHA_VENCIMIENTO'] ? ' · vence ' . fecha($l['FECHA_VENCIMIENTO']) : '')) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo">
            <label for="zona">Zona</label>
            <select id="zona" name="zona">
                <?php foreach ($zonas as $z): ?>
                    <option value="<?= (int) $z['ID_ZONA'] ?>" <?= (string) $z['ID_ZONA'] === (string) ($form['zona'] ?? '') ? 'selected' : '' ?>><?= e($z['NOMBRE']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="<?= claseCampo($errores, 'motivo') ?>">
            <label for="motivo">Motivo</label>
            <select id="motivo" name="motivo">
                <?php foreach ($motivos as $valor => $texto): ?>
                    <option value="<?= e($valor) ?>" <?= $valor === ($form['motivo'] ?? '') ? 'selected' : '' ?>><?= e($texto) ?></option>
                <?php endforeach; ?>
            </select>
            <?= error($errores, 'motivo') ?>
        </div>
        <div class="<?= claseCampo($errores, 'cantidad') ?>">
            <label for="cantidad">Cantidad (unidades) *</label>
            <input id="cantidad" name="cantidad" inputmode="numeric" value="<?= valor($form, 'cantidad') ?>">
            <?= error($errores, 'cantidad') ?>
        </div>
        <div class="campo">
            <label for="observacion">Observación</label>
            <input id="observacion" name="observacion" maxlength="200" value="<?= valor($form, 'observacion') ?>">
        </div>
    </div>
    <div class="msg info" style="margin:0">El costo sale del lote (o del último lote recibido). Valor total = cantidad × costo (CK_MER_4). Al registrar, el stock baja.</div>
    <div class="botones"><button class="btn" type="submit">Registrar merma</button></div>
</form>
<?php endif; ?>

<?php require __DIR__ . '/../partials/pie.php'; ?>
