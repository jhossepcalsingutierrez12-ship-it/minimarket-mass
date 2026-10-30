<?php
/** @var Producto $producto */
$titulo = $producto->getNombre();
$seccion = 'catalogo';
require __DIR__ . '/../partials/cabecera.php';

$sku = $producto->getCodigoSku();
?>
<a class="volver" href="?accion=listar">← Volver al catálogo</a>
<div class="card detalle">
    <div class="foto"><?= fotoProducto($producto) ?></div>
    <div>
        <span class="sku">SKU <?= e($sku) ?> · <?= e(CATEGORIAS[categoriaDe($sku)]['nombre'] ?? 'Sin categoría') ?></span>
        <h1><?= e($producto->getNombre()) ?></h1>
        <span class="precio"><?= sol($producto->getPrecioVenta()) ?></span>
        <dl class="datos">
            <dt>Precio (IGV incluido)</dt><dd><?= sol($producto->getPrecioVenta()) ?></dd>
            <dt>Subtotal</dt><dd><?= sol($producto->subtotal()) ?></dd>
            <dt>IGV 18 %</dt><dd><?= sol($producto->igv()) ?></dd>
            <dt>Perecible</dt><dd><?= $producto->esPerecible() ? 'Sí' : 'No' ?></dd>
            <dt>Stock</dt><dd><?= $producto->getStockActual() ?> (mínimo <?= $producto->getStockMinimo() ?>)</dd>
            <dt>Estado</dt><dd><span class="tag <?= e($producto->estadoStock()) ?>"><?= e($producto->estadoStock()) ?></span></dd>
        </dl>
    </div>
</div>

<?php require __DIR__ . '/../partials/pie.php'; ?>
