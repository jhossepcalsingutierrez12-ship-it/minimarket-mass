<?php
/** @var array $lista @var Producto[] $productos @var Producto $seleccionado @var array $errores @var array $form */
$titulo = 'Lotes y vencimientos';
$seccion = 'lotes';
require __DIR__ . '/../partials/cabecera.php';

$estados = array_map(fn($l) => estadoLote($l['FECHA_VENCIMIENTO'])[1], $lista);
$vencidos = count(array_keys($estados, 'bad'));
$pronto = count(array_keys($estados, 'warn'));
?>
<h1>Lotes y vencimientos</h1>
<p class="sub"><?= $vencidos ?> vencido<?= $vencidos === 1 ? '' : 's' ?> · <?= $pronto ?> por vencer en 30 días. Lo vencido se retira y se registra como merma.</p>
<?= aviso() ?>
<div class="tabla"><table>
    <thead><tr><th>Producto</th><th>Lote</th><th>Vence</th><th>Estado</th><th class="num">Recibido</th><th class="num">Costo unit.</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($lista as $l): [$texto, $clase] = estadoLote($l['FECHA_VENCIMIENTO']); ?>
        <tr>
            <td><?= e($l['NOMBRE']) ?><br><span class="sku"><?= e($l['CODIGO_SKU']) ?></span></td>
            <td class="nw sku"><?= e($l['CODIGO_LOTE'] ?? '—') ?></td>
            <td class="nw"><?= fecha($l['FECHA_VENCIMIENTO']) ?></td>
            <td><?= tag($texto, $clase) ?></td>
            <td class="num"><?= (int) $l['CANTIDAD_RECIBIDA'] ?></td>
            <td class="num"><?= sol((float) $l['COSTO_UNITARIO']) ?></td>
            <td><?php if ($clase === 'bad'): ?><a class="btn sm peligro" href="?accion=mermas&amp;producto=<?= (int) $l['ID_PRODUCTO'] ?>&amp;lote=<?= (int) $l['ID_LOTE'] ?>">Registrar merma</a><?php endif; ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>

<h2 class="sec">Recibir lote</h2>
<!-- Al cambiar el producto se recarga la página (GET) para sugerir su código de lote -->
<form id="fCambiarProducto" method="get"><input type="hidden" name="accion" value="lotes"></form>
<form class="card form" method="post" action="?accion=lotes" novalidate>
    <input type="hidden" name="csrf" value="<?= e(AuthController::token()) ?>">
    <div class="cols">
        <div class="<?= claseCampo($errores, 'producto') ?>">
            <label for="producto">Producto *</label>
            <select id="producto" name="producto" data-recargar="fCambiarProducto">
                <?php foreach ($productos as $p): ?>
                    <option value="<?= $p->getIdProducto() ?>" <?= (string) $p->getIdProducto() === (string) $form['producto'] ? 'selected' : '' ?>><?= e($p->getCodigoSku() . ' · ' . $p->getNombre()) ?></option>
                <?php endforeach; ?>
            </select>
            <?= error($errores, 'producto') ?>
        </div>
        <div class="campo">
            <label for="codigo">Código de lote</label>
            <input id="codigo" name="codigo" maxlength="20" value="<?= valor($form, 'codigo') ?>">
            <span class="hint">Se arma con el SKU de <b><?= e($seleccionado->getNombre()) ?></b> + año y mes + letra. Puedes cambiarlo por el del proveedor.</span>
        </div>
        <div class="<?= claseCampo($errores, 'vence') ?>">
            <label for="vence">Fecha de vencimiento</label>
            <input id="vence" name="vence" type="date" value="<?= valor($form, 'vence') ?>">
            <span class="hint">Déjalo vacío si el producto no vence.</span>
            <?= error($errores, 'vence') ?>
        </div>
        <div class="<?= claseCampo($errores, 'cantidad') ?>">
            <label for="cantidad">Cantidad recibida *</label>
            <input id="cantidad" name="cantidad" inputmode="numeric" value="<?= valor($form, 'cantidad') ?>">
            <?= error($errores, 'cantidad') ?>
        </div>
        <div class="<?= claseCampo($errores, 'costo') ?>">
            <label for="costo">Costo unitario *</label>
            <input id="costo" name="costo" inputmode="decimal" value="<?= valor($form, 'costo') ?>">
            <?= error($errores, 'costo') ?>
        </div>
    </div>
    <div class="botones"><button class="btn" type="submit">Recibir lote</button><span class="hint" style="font-size:.8rem;color:var(--muted)">Suma la cantidad al stock del producto.</span></div>
</form>

<?php require __DIR__ . '/../partials/pie.php'; ?>
