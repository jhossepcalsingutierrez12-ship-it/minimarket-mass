<?php
/** @var Producto[] $productos @var Producto[] $inactivos @var Usuario $u */
$titulo = 'Catálogo';
$seccion = 'listar';
require __DIR__ . '/../partials/cabecera.php';

$admin = $u->puedeAdministrarProductos();
$porReponer = count(array_filter($productos, fn(Producto $p): bool => $p->estadoStock() !== 'Normal'));
?>
<?php
$porCategoria = [];
foreach ($productos as $p) {
    $porCategoria[categoriaDe($p->getCodigoSku())] = ($porCategoria[categoriaDe($p->getCodigoSku())] ?? 0) + 1;
}
$agotados = count(array_filter($productos, fn(Producto $p): bool => $p->estadoStock() === 'Agotado'));
?>
<div class="cab">
    <div>
        <h1>Catálogo</h1>
        <p class="sub"><?= count($productos) ?> productos activos · <?= $porReponer ?> por reponer o agotados · precios con IGV 18 % incluido</p>
    </div>
    <?php if ($admin): ?>
        <div class="botones" style="margin-bottom:18px">
            <a class="btn ghost" href="?accion=pdf"><?= icono('pdf') ?>Descargar PDF</a>
            <a class="btn" href="?accion=agregar"><?= icono('mas') ?>Agregar producto</a>
        </div>
    <?php endif; ?>
</div>
<?= aviso() ?>
<?php if ($u->puedeVerAlertas() && $porReponer > 0): ?>
    <div class="msg info aviso-alertas"><span><?= icono('alerta') ?> Hay <b><?= $porReponer ?></b> producto<?= $porReponer === 1 ? '' : 's' ?> agotado<?= $porReponer === 1 ? '' : 's' ?> o con poco stock.</span>
        <a class="btn sm" href="?accion=alertas">Ver alertas de inventario</a></div>
<?php endif; ?>

<!-- Filtros: los activa public/js/app.js. Sin JavaScript se ven todos los productos. -->
<div class="filtros filtros-catalogo solo-js" hidden>
    <label class="buscador"><?= icono('buscar') ?><input id="buscar" type="search" placeholder="Buscar por nombre o SKU" aria-label="Buscar producto"></label>
    <select id="filtroEstado" aria-label="Filtrar por estado de stock">
        <option value="">Todos los estados</option>
        <option>Normal</option><option>Reponer</option><option>Agotado</option>
    </select>
    <select id="ordenar" aria-label="Ordenar productos">
        <option value="nombre">Ordenar: nombre (A–Z)</option>
        <option value="precio-asc">Precio: menor a mayor</option>
        <option value="precio-desc">Precio: mayor a menor</option>
        <option value="stock-asc">Stock: menos primero</option>
        <option value="stock-desc">Stock: más primero</option>
    </select>
</div>
<div class="chips solo-js" id="chips" hidden role="group" aria-label="Filtrar por categoría">
    <button type="button" class="chip active" data-cat="" aria-pressed="true">Todas <span class="chip-n"><?= count($productos) ?></span></button>
    <?php foreach (CATEGORIAS as $codigo => $cat): ?>
        <button type="button" class="chip chip-cat" data-cat="<?= e($codigo) ?>" aria-pressed="false"><?= imagenCategoria($codigo, 22) ?><?= e($cat['nombre']) ?> <span class="chip-n"><?= $porCategoria[$codigo] ?? 0 ?></span></button>
    <?php endforeach; ?>
</div>
<p class="sub resultado solo-js" id="resultado" hidden aria-live="polite"></p>

<div class="grid" id="grid">
    <?php foreach ($productos as $p): $estado = $p->estadoStock(); ?>
        <article class="card prod estado-<?= e(strtolower($estado)) ?>"
                 data-nombre="<?= e(mb_strtolower($p->getNombre())) ?>"
                 data-sku="<?= e(strtolower($p->getCodigoSku())) ?>"
                 data-estado="<?= e($estado) ?>"
                 data-precio="<?= number_format($p->getPrecioVenta(), 2, '.', '') ?>"
                 data-stock="<?= $p->getStockActual() ?>"
                 data-cat="<?= e(categoriaDe($p->getCodigoSku())) ?>">
            <div class="foto"><?= fotoProducto($p) ?></div>
            <div class="sku-cat"><span class="sku"><?= e($p->getCodigoSku()) ?></span><?= etiquetaCategoria($p->getCodigoSku()) ?></div>
            <h3><?= e($p->getNombre()) ?></h3>
            <div class="precio-fila"><span class="precio"><?= sol($p->getPrecioVenta()) ?></span><span class="igv">incluye IGV <?= sol($p->igv()) ?></span></div>
            <div class="etiquetas">
                <span class="tag estado <?= e($estado) ?>"><?= e($estado) ?></span>
                <?php if ($p->esPerecible()): ?><span class="tag per">Perecible</span><?php endif; ?>
            </div>
            <!-- Las unidades van aparte del estado: «Reponer» es lo que hay que hacer, no la cantidad -->
            <p class="stock-linea <?= $p->getStockActual() === 0 ? 'sin' : '' ?>">
                <?= $p->getStockActual() === 0 ? 'Sin unidades en stock' : 'Quedan <b>' . $p->getStockActual() . '</b> und.' ?>
                <span>· mínimo <?= $p->getStockMinimo() ?></span>
            </p>
            <div class="acciones">
                <a class="btn ghost sm" href="?accion=ver&amp;sku=<?= urlencode($p->getCodigoSku()) ?>"><?= icono('ojo', 16) ?>Ver detalle</a>
                <?php if ($admin): ?>
                    <a class="btn ghost sm" href="?accion=editar&amp;id=<?= $p->getIdProducto() ?>"><?= icono('lapiz', 16) ?>Editar</a>
                    <a class="btn peligro sm" href="?accion=baja&amp;id=<?= $p->getIdProducto() ?>"><?= icono('basura', 16) ?>Dar de baja</a>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
</div>
<div id="vacio" <?= $productos ? 'hidden' : '' ?>>
    <?= $productos
        ? vacio('No hay productos con ese filtro', 'Prueba otra categoría, otro estado o borra lo que escribiste en el buscador.', 'buscar', '<button type="button" class="btn ghost sm" id="limpiarFiltros">' . icono('cerrar', 16) . 'Limpiar filtros</button>')
        : vacio('Todavía no hay productos', $admin ? 'Agrega el primero con «Agregar producto».' : 'Cuando el administrador agregue productos, aparecerán aquí.') ?>
</div>

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
