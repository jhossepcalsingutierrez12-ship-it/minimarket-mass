<?php
/** @var Producto[] $productos @var Producto[] $inactivos @var Usuario $u */
$titulo = 'Catálogo';
$seccion = 'listar';
require __DIR__ . '/../partials/cabecera.php';

$admin = $u->puedeAdministrarProductos();
$porReponer = count(array_filter($productos, fn(Producto $p): bool => $p->estadoStock() !== 'Normal'));
?>
<div class="cab">
    <div>
        <h1>Catálogo</h1>
        <p class="sub"><?= count($productos) ?> productos activos · <?= $porReponer ?> por reponer o agotados · precios con IGV 18 % incluido</p>
    </div>
    <?php if ($admin): ?>
        <div class="botones" style="margin-bottom:18px">
            <a class="btn ghost" href="?accion=pdf">Descargar PDF</a>
            <a class="btn" href="?accion=agregar">+ Agregar producto</a>
        </div>
    <?php endif; ?>
</div>
<?= aviso() ?>
<?php if ($u->puedeVerAlertas() && $porReponer > 0): ?>
    <div class="msg info aviso-alertas"><span>⚠ Hay <b><?= $porReponer ?></b> producto<?= $porReponer === 1 ? '' : 's' ?> agotado<?= $porReponer === 1 ? '' : 's' ?> o con poco stock.</span>
        <a class="btn sm" href="?accion=alertas">Ver alertas de inventario</a></div>
<?php endif; ?>

<!-- Filtros: los activa public/js/app.js. Sin JavaScript se ven todos los productos. -->
<div class="filtros solo-js" hidden>
    <input id="buscar" type="search" placeholder="Buscar por nombre o SKU" aria-label="Buscar producto">
    <select id="filtroEstado" aria-label="Filtrar por estado">
        <option value="">Todos los estados</option>
        <option>Normal</option><option>Reponer</option><option>Agotado</option>
    </select>
</div>
<div class="chips solo-js" id="chips" hidden>
    <button type="button" class="chip active" data-cat="">Todas</button>
    <?php foreach (CATEGORIAS as $codigo => $cat): ?>
        <button type="button" class="chip chip-cat" data-cat="<?= e($codigo) ?>"><?= imagenCategoria($codigo, 22) ?><?= e($cat['nombre']) ?></button>
    <?php endforeach; ?>
</div>

<div class="grid" id="grid">
    <?php foreach ($productos as $p): ?>
        <article class="card prod"
                 data-nombre="<?= e(mb_strtolower($p->getNombre())) ?>"
                 data-sku="<?= e(strtolower($p->getCodigoSku())) ?>"
                 data-estado="<?= e($p->estadoStock()) ?>"
                 data-cat="<?= e(categoriaDe($p->getCodigoSku())) ?>">
            <div class="foto"><?= fotoProducto($p) ?></div>
            <div class="sku-cat"><span class="sku"><?= e($p->getCodigoSku()) ?></span><?= etiquetaCategoria($p->getCodigoSku()) ?></div>
            <h3><?= e($p->getNombre()) ?></h3>
            <div><span class="precio"><?= sol($p->getPrecioVenta()) ?></span> <span class="igv">IGV <?= sol($p->igv()) ?></span></div>
            <div class="etiquetas">
                <span class="tag <?= e($p->estadoStock()) ?>"><?= e($p->estadoStock()) ?></span>
                <?php if ($p->esPerecible()): ?><span class="tag per">Perecible</span><?php endif; ?>
            </div>
            <!-- Las unidades van aparte del estado: «Reponer» es lo que hay que hacer, no la cantidad -->
            <p class="stock-linea <?= $p->getStockActual() === 0 ? 'sin' : '' ?>">
                <?= $p->getStockActual() === 0 ? 'Sin unidades en stock' : 'Quedan <b>' . $p->getStockActual() . '</b> und.' ?>
                <span>· mínimo <?= $p->getStockMinimo() ?></span>
            </p>
            <div class="acciones">
                <a class="btn ghost sm" href="?accion=ver&amp;sku=<?= urlencode($p->getCodigoSku()) ?>">Ver detalle</a>
                <?php if ($admin): ?>
                    <a class="btn ghost sm" href="?accion=editar&amp;id=<?= $p->getIdProducto() ?>">Editar</a>
                    <a class="btn peligro sm" href="?accion=baja&amp;id=<?= $p->getIdProducto() ?>">Dar de baja</a>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
</div>
<p id="vacio" class="sub" <?= $productos ? 'hidden' : '' ?>>No hay productos con ese filtro.</p>

<?php if ($inactivos): ?>
    <div class="inactivos">
        <h3>Dados de baja (<?= count($inactivos) ?>)</h3>
        <p class="sub" style="margin:0 0 6px">No salen en el catálogo, pero siguen en la base con ESTADO = 'I'.</p>
        <ul>
            <?php foreach ($inactivos as $p): ?>
                <li>
                    <span><b><?= e($p->getCodigoSku()) ?></b> · <?= e($p->getNombre()) ?></span>
                    <form method="post" action="?accion=reactivar">
                        <input type="hidden" name="csrf" value="<?= e(AuthController::token()) ?>">
                        <input type="hidden" name="id" value="<?= $p->getIdProducto() ?>">
                        <button class="btn sm ghost">Reactivar</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../partials/pie.php'; ?>
