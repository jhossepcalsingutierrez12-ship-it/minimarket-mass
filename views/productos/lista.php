<?php
/** @var Producto[] $productos */
$titulo = 'Catálogo';
$seccion = 'catalogo';
require __DIR__ . '/../partials/cabecera.php';

$porReponer = count(array_filter($productos, fn(Producto $p): bool => $p->estadoStock() !== 'Normal'));
?>
<h1>Catálogo</h1>
<p class="sub"><?= count($productos) ?> productos activos · <?= $porReponer ?> por reponer o agotados · precios con IGV 18 % incluido</p>

<!-- Filtros: los activa public/js/app.js. Sin JavaScript se ven todos los productos. -->
<div class="filtros solo-js" hidden>
    <input id="buscar" type="search" placeholder="Buscar por nombre o SKU (ej. LAC-0002)" aria-label="Buscar producto">
    <select id="filtroEstado" aria-label="Filtrar por estado">
        <option value="">Todos los estados</option>
        <option>Normal</option><option>Reponer</option><option>Agotado</option>
    </select>
</div>
<div class="chips solo-js" id="chips" hidden>
    <button type="button" class="chip active" data-cat="">Todas</button>
    <?php foreach (CATEGORIAS as $codigo => $cat): ?>
        <button type="button" class="chip" data-cat="<?= e($codigo) ?>"><?= e($cat['nombre']) ?></button>
    <?php endforeach; ?>
</div>

<div class="grid" id="grid">
    <?php foreach ($productos as $p): ?>
        <article class="card prod"
                 data-nombre="<?= e(strtolower($p->getNombre())) ?>"
                 data-sku="<?= e(strtolower($p->getCodigoSku())) ?>"
                 data-estado="<?= e($p->estadoStock()) ?>"
                 data-cat="<?= e(categoriaDe($p->getCodigoSku())) ?>">
            <div class="foto"><?= fotoProducto($p) ?></div>
            <span class="sku"><?= e($p->getCodigoSku()) ?></span>
            <h3><?= e($p->getNombre()) ?></h3>
            <div><span class="precio"><?= sol($p->getPrecioVenta()) ?></span> <span class="igv">IGV <?= sol($p->igv()) ?></span></div>
            <div class="etiquetas">
                <span class="tag <?= e($p->estadoStock()) ?>"><?= e($p->estadoStock()) ?> · <?= $p->getStockActual() ?> und.</span>
                <?php if ($p->esPerecible()): ?><span class="tag per">Perecible</span><?php endif; ?>
            </div>
            <div class="acciones">
                <a class="btn ghost sm" href="?accion=ver&amp;sku=<?= urlencode($p->getCodigoSku()) ?>">Ver detalle</a>
            </div>
        </article>
    <?php endforeach; ?>
</div>
<p id="vacio" class="sub" <?= $productos ? 'hidden' : '' ?>>No hay productos con ese filtro.</p>

<?php require __DIR__ . '/../partials/pie.php'; ?>
