<?php
/**
 * @var array $sesion @var Producto[] $productos @var Producto[] $porId @var array $carrito
 * @var array $form @var array $metodos
 */
$titulo = 'Nueva venta';
$seccion = 'venta';
require __DIR__ . '/../partials/cabecera.php';

$total = 0.0;
$unidades = 0;
foreach ($carrito as $id => $cant) {
    $total += round($porId[$id]->getPrecioVenta() * $cant, 2);
    $unidades += $cant;
}
$igv = round($total * 18 / 118, 2);
$factura = $form['tipo'] === 'FACTURA';
$serie = str_pad((string) $sesion['ID_TIENDA'], 3, '0', STR_PAD_LEFT);
?>
<div class="cab"><div>
    <h1>Nueva venta</h1>
    <p class="sub"><?= e($sesion['TIENDA']) ?> · Caja N° <?= (int) $sesion['NUMERO_CAJA'] ?> · sesión <?= (int) $sesion['ID_SESION'] ?> · <?= e($sesion['TURNO']) ?>. R5: solo se vende lo que hay en inventario.</p>
</div></div>
<?= aviso() ?>

<!-- Un solo formulario: cada botón manda su acción (agregar, mas, menos, quitar o cobrar) -->
<form class="dos" method="post" action="?accion=venta" novalidate id="fVenta">
    <input type="hidden" name="csrf" value="<?= e(AuthController::token()) ?>">
    <!-- Si se presiona Enter en un campo, se usa este botón (solo guarda lo escrito) -->
    <button name="guardar" value="1" tabindex="-1" aria-hidden="true" style="position:absolute;left:-9999px">Guardar</button>
    <div class="card">
        <div class="filtros" style="margin-bottom:10px">
            <input id="buscarVenta" type="search" placeholder="Buscar por nombre o SKU" aria-label="Buscar producto">
        </div>
        <div class="tabla" style="margin:0"><table>
            <thead><tr><th>Producto</th><th class="num">Precio</th><th class="num">Stock</th><th>Cant.</th><th></th></tr></thead>
            <tbody id="filasVenta">
            <?php foreach ($productos as $p): $id = $p->getIdProducto(); ?>
                <tr data-buscar="<?= e(mb_strtolower($p->getNombre() . ' ' . $p->getCodigoSku())) ?>">
                    <td><?= fotoSku($p->getCodigoSku(), '', 'mini') ?><?= e($p->getNombre()) ?><br><span class="sku"><?= e($p->getCodigoSku()) ?></span> <?= etiquetaCategoria($p->getCodigoSku()) ?></td>
                    <td class="num"><?= sol($p->getPrecioVenta()) ?></td>
                    <td class="num"><span class="tag <?= e($p->estadoStock()) ?>"><?= $p->getStockActual() ?></span></td>
                    <td><input class="qty" type="number" min="1" value="1" name="cant[<?= $id ?>]" aria-label="Cantidad de <?= e($p->getNombre()) ?>"></td>
                    <td><button class="btn sm" name="agregar" value="<?= $id ?>" <?= $p->getStockActual() === 0 ? 'disabled' : '' ?> aria-label="Agregar <?= e($p->getNombre()) ?>">+</button></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>

    <div class="card">
        <h3 style="margin-top:0">Comprobante</h3>
        <div class="campo">
            <label for="tipo">Tipo de comprobante</label>
            <select id="tipo" name="tipo">
                <option value="BOLETA">Boleta (serie B<?= $serie ?>)</option>
                <option value="FACTURA" <?= $factura ? 'selected' : '' ?>>Factura (serie F<?= $serie ?>)</option>
            </select>
        </div>
        <div class="campo">
            <label for="doc" id="docLabel"><?= $factura ? 'RUC del cliente *' : 'DNI o RUC del cliente' ?></label>
            <input id="doc" name="doc" inputmode="numeric" maxlength="11" autocomplete="off" value="<?= valor($form, 'doc') ?>">
            <div id="cliInfo"><span class="hint">Vacío = «Cliente varios». Escribe el DNI si el cliente lo pide en su boleta.</span></div>
        </div>
        <div class="campo" id="nombreWrap">
            <label for="nombre"><?= $factura ? 'Razón social (si es cliente nuevo)' : 'Nombre (si es cliente nuevo)' ?></label>
            <input id="nombre" name="nombre" maxlength="120" value="<?= valor($form, 'nombre') ?>">
        </div>
        <div class="campo">
            <label for="metodo">Método de pago</label>
            <select id="metodo" name="metodo">
                <?php foreach ($metodos as $id => $nombre): ?>
                    <option value="<?= (int) $id ?>" <?= (string) $form['metodo'] === (string) $id ? 'selected' : '' ?>><?= e($nombre) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="card ticket" style="box-shadow:none;margin:6px 0 12px;padding:12px">
            <?php foreach ($carrito as $id => $cant): $p = $porId[$id]; ?>
                <div style="margin-bottom:6px">
                    <div><?= e($p->getNombre()) ?></div>
                    <div class="l">
                        <span class="cant">
                            <button name="menos" value="<?= $id ?>" aria-label="Quitar una unidad de <?= e($p->getNombre()) ?>">−</button><?= $cant ?><button name="mas" value="<?= $id ?>" aria-label="Agregar una unidad de <?= e($p->getNombre()) ?>">+</button>
                            × <?= number_format($p->getPrecioVenta(), 2) ?>
                        </span>
                        <span><?= sol(round($p->getPrecioVenta() * $cant, 2)) ?>
                            <button class="btn sm ghost" style="padding:0 6px" name="quitar" value="<?= $id ?>" aria-label="Quitar <?= e($p->getNombre()) ?>">✕</button></span>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (!$carrito): ?><div class="c" style="color:var(--muted)">Sin productos. Agrega desde la lista.</div><?php endif; ?>
            <hr>
            <div class="l"><span><?= $unidades ?> artículo<?= $unidades === 1 ? '' : 's' ?></span><span></span></div>
            <div class="l"><span>Subtotal</span><span><?= sol($total - $igv) ?></span></div>
            <div class="l"><span>IGV 18 %</span><span><?= sol($igv) ?></span></div>
            <div class="l total"><span>TOTAL</span><span><?= sol($total) ?></span></div>
        </div>
        <button class="btn amarillo" name="cobrar" value="1" style="width:100%" <?= $carrito ? '' : 'disabled' ?>>Cobrar <?= $carrito ? sol($total) : '' ?></button>
    </div>
</form>

<?php require __DIR__ . '/../partials/pie.php'; ?>
