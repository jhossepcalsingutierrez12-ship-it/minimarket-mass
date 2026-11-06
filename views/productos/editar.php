<?php
/** @var Producto $producto @var array $errores @var array $form */
$titulo = 'Editar producto';
$seccion = 'listar';
require __DIR__ . '/../partials/cabecera.php';
?>
<?= migas([['Catálogo', '?accion=listar'], [$producto->getNombre(), '?accion=ver&sku=' . urlencode($producto->getCodigoSku())], ['Editar', null]]) ?>
<h1>Editar producto</h1>
<p class="sub">El formulario aparece con los datos actuales; cambia solo lo necesario.</p>

<form class="card form" method="post" action="?accion=editar&amp;id=<?= $producto->getIdProducto() ?>" enctype="multipart/form-data" novalidate>
    <input type="hidden" name="csrf" value="<?= e(AuthController::token()) ?>">
    <div class="cols">
        <div class="campo">
            <label for="sku">Código SKU</label>
            <input id="sku" value="<?= e($producto->getCodigoSku()) ?>" readonly>
            <span class="hint">El SKU no se cambia: identifica al producto en las ventas.</span>
        </div>
        <div class="<?= claseCampo($errores, 'nombre') ?>">
            <label for="nombre">Nombre</label>
            <input id="nombre" name="nombre" maxlength="120" value="<?= valor($form, 'nombre') ?>">
            <?= error($errores, 'nombre') ?>
        </div>
        <div class="<?= claseCampo($errores, 'precio') ?>">
            <label for="precio">Precio (IGV incluido)</label>
            <input id="precio" name="precio" inputmode="decimal" value="<?= valor($form, 'precio') ?>" data-igv="igvTxt">
            <span class="hint">Antes <?= sol($producto->getPrecioVenta()) ?> · <span id="igvTxt"></span></span>
            <?= error($errores, 'precio') ?>
        </div>
        <div class="<?= claseCampo($errores, 'minimo') ?>">
            <label for="minimo">Stock mínimo</label>
            <input id="minimo" name="minimo" inputmode="numeric" value="<?= valor($form, 'minimo') ?>">
            <?= error($errores, 'minimo') ?>
        </div>
        <div class="<?= claseCampo($errores, 'foto') ?>">
            <label for="foto">Foto del producto</label>
            <input id="foto" name="foto" type="file" accept="image/jpeg,image/png,image/webp" data-vista="fotoPrev">
            <div class="foto-prev" id="fotoPrev"><?= fotoSku($producto->getCodigoSku(), $producto->getNombre(), 'x') ?><span>Foto actual. Elige otra para cambiarla.</span></div>
            <?= error($errores, 'foto') ?>
        </div>
    </div>
    <div class="msg info" style="margin:0">El stock actual (<?= $producto->getStockActual() ?> und.) no se edita aquí: cambia con ventas, lotes recibidos y mermas.</div>
    <div class="botones">
        <button class="btn" type="submit">Guardar cambios</button>
        <a class="btn ghost" href="?accion=listar">Cancelar</a>
    </div>
</form>

<?php require __DIR__ . '/../partials/pie.php'; ?>
