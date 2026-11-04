<?php
/** @var Producto $producto @var array $lotes */
$titulo = $producto->getNombre();
$seccion = 'listar';
require __DIR__ . '/../partials/cabecera.php';

$sku = $producto->getCodigoSku();
?>
<a class="volver" href="?accion=listar">← Volver al catálogo</a>
<div class="card detalle">
    <div class="foto"><?= fotoProducto($producto) ?></div>
    <div>
        <div class="sku-cat"><span class="sku">SKU <?= e($sku) ?></span><?= etiquetaCategoria($sku) ?></div>
        <h1><?= e($producto->getNombre()) ?></h1>
        <span class="precio"><?= sol($producto->getPrecioVenta()) ?></span>
        <dl class="datos">
            <dt>Precio (IGV incluido)</dt><dd><?= sol($producto->getPrecioVenta()) ?></dd>
            <dt>Subtotal</dt><dd><?= sol($producto->subtotal()) ?></dd>
            <dt>IGV 18 %</dt><dd><?= sol($producto->igv()) ?></dd>
            <dt>Perecible</dt><dd><?= $producto->esPerecible() ? 'Sí' : 'No' ?></dd>
            <dt>Stock</dt><dd><?= $producto->getStockActual() ?> (mínimo <?= $producto->getStockMinimo() ?>)</dd>
            <dt>Estado</dt><dd><span class="tag <?= e($producto->estadoStock()) ?>"><?= e($producto->estadoStock()) ?></span></dd>
            <?php if ($lotes): ?>
                <dt>Lotes</dt>
                <dd><?php foreach ($lotes as $l): ?><?= e($l['CODIGO_LOTE'] ?? 'Sin código') ?> · vence <?= fecha($l['FECHA_VENCIMIENTO']) ?><br><?php endforeach; ?></dd>
            <?php endif; ?>
        </dl>
    </div>
</div>

<?php require __DIR__ . '/../partials/pie.php'; ?>
