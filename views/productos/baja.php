<?php
/** @var Producto $producto */
$titulo = 'Dar de baja';
$seccion = 'listar';
require __DIR__ . '/../partials/cabecera.php';
?>
<?= migas([['Catálogo', '?accion=listar'], [$producto->getNombre(), '?accion=ver&sku=' . urlencode($producto->getCodigoSku())], ['Dar de baja', null]]) ?>
<h1>Dar de baja</h1>
<p class="sub"></p>
<div class="baja">
    <div class="top">¿Dar de baja este producto?</div>
    <div class="cuerpo">
        <div class="foto"><?= fotoProducto($producto) ?></div>
        <div style="flex:1 1 240px;min-width:0">
            <span class="sku"><?= e($producto->getCodigoSku()) ?></span>
            <div style="font-size:1.2rem;font-weight:800"><?= e($producto->getNombre()) ?></div>
        </div>
    </div>
    <ul>
        <li>Deja de aparecer en el catálogo y en la venta.</li>
        <li><b>No se borra:</b> su ESTADO pasa de «A» a «I».</li>
        <li>Las ventas donde aparece se conservan.</li>
    </ul>
    <form class="botones" method="post" action="?accion=baja">
        <input type="hidden" name="csrf" value="<?= e(AuthController::token()) ?>">
        <input type="hidden" name="id" value="<?= $producto->getIdProducto() ?>">
        <button class="btn rojo" type="submit">Sí, dar de baja</button>
        <a class="btn ghost" href="?accion=listar">Cancelar</a>
    </form>
</div>

<?php require __DIR__ . '/../partials/pie.php'; ?>
