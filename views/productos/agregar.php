<?php
/** @var array $errores @var array $form @var array $subcategorias @var array $marcas @var array $sugeridos */
$titulo = 'Agregar producto';
$seccion = 'agregar';
require __DIR__ . '/../partials/cabecera.php';
?>
<?= migas([['Catálogo', '?accion=listar'], ['Agregar producto', null]]) ?>
<h1>Agregar producto</h1>
<p class="sub">Se guarda en PRODUCTO y en INVENTARIO con su stock inicial.</p>

<form class="card form" method="post" action="?accion=agregar" enctype="multipart/form-data" novalidate>
    <input type="hidden" name="csrf" value="<?= e(AuthController::token()) ?>">
    <?php if ($sugeridos): $faltanDatos = array_filter($sugeridos, fn($s) => $s['categoria'] === '' || $s['marca_id'] === ''); ?>
        <div class="sugeridos">
            <label for="sugerido">Productos nuevos: elige uno y el formulario se llena solo</label>
            <select id="sugerido">
                <option value="">— Escribir un producto a mano —</option>
                <?php foreach (CATEGORIAS as $pref => $cat):
                    $deCat = array_filter($sugeridos, fn($s) => str_starts_with($s['sku'], $pref));
                    if (!$deCat) continue; ?>
                    <optgroup label="<?= e($cat['nombre']) ?> (<?= e($pref) ?>)">
                        <?php foreach ($deCat as $s): ?>
                            <option value="<?= e($s['sku']) ?>" data-nombre="<?= e($s['nombre']) ?>" data-categoria="<?= e($s['categoria']) ?>" data-marca="<?= e($s['marca_id']) ?>"
                                    data-precio="<?= e($s['precio']) ?>" data-contenido="<?= e($s['contenido']) ?>" data-perecible="<?= e($s['perecible']) ?>"
                                    data-stock="<?= (int) $s['stock'] ?>" data-minimo="<?= (int) $s['minimo'] ?>" data-foto="<?= e($s['foto']) ?>"><?= e($s['sku'] . ' · ' . $s['nombre'] . ' · S/ ' . $s['precio']) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
            <span class="hint">Puedes cambiar cualquier dato antes de guardar. La foto ya está lista: no hace falta subirla.</span>
            <?php if ($faltanDatos): ?>
                <span class="hint" style="color:var(--bad)">Faltan marcas o subcategorías nuevas en la base: corre <code>database/productos_nuevos.sql</code>.</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <div class="cols">
        <div class="<?= claseCampo($errores, 'sku') ?>">
            <label for="sku">Código SKU *</label>
            <input id="sku" name="sku" maxlength="8" value="<?= valor($form, 'sku') ?>" autocomplete="off">
            <span class="hint">Formato ABC-0000. Las 3 letras dan la categoría:</span>
            <span class="guia-cat"><?php foreach (CATEGORIAS as $c => $cat): ?><span><?= imagenCategoria($c, 20) ?><b><?= e($c) ?></b> <?= e($cat['nombre']) ?></span><?php endforeach; ?></span>
            <?= error($errores, 'sku') ?>
        </div>
        <div class="<?= claseCampo($errores, 'nombre') ?>">
            <label for="nombre">Nombre *</label>
            <input id="nombre" name="nombre" maxlength="120" value="<?= valor($form, 'nombre') ?>">
            <?= error($errores, 'nombre') ?>
        </div>
        <div class="<?= claseCampo($errores, 'categoria') ?>">
            <label for="categoria">Subcategoría *</label>
            <select id="categoria" name="categoria">
                <option value="">Elige…</option>
                <?php foreach ($subcategorias as $prefijo => $subs): ?>
                    <optgroup label="<?= e(CATEGORIAS[$prefijo]['nombre']) ?> (<?= e($prefijo) ?>)" data-prefijo="<?= e($prefijo) ?>">
                        <?php foreach ($subs as $id => $nombre): ?>
                            <option value="<?= (int) $id ?>" <?= (string) ($form['categoria'] ?? '') === (string) $id ? 'selected' : '' ?>><?= e($nombre) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
            <?= error($errores, 'categoria') ?>
        </div>
        <div class="<?= claseCampo($errores, 'marca') ?>">
            <label for="marca">Marca *</label>
            <select id="marca" name="marca">
                <option value="">Elige…</option>
                <?php foreach ($marcas as $id => $nombre): ?>
                    <option value="<?= (int) $id ?>" <?= (string) ($form['marca'] ?? '') === (string) $id ? 'selected' : '' ?>><?= e($nombre) ?></option>
                <?php endforeach; ?>
            </select>
            <?= error($errores, 'marca') ?>
        </div>
        <div class="<?= claseCampo($errores, 'precio') ?>">
            <label for="precio">Precio de venta (IGV incluido) *</label>
            <input id="precio" name="precio" inputmode="decimal" value="<?= valor($form, 'precio') ?>" data-igv="igvTxt">
            <span class="hint" id="igvTxt"></span>
            <?= error($errores, 'precio') ?>
        </div>
        <div class="<?= claseCampo($errores, 'contenido') ?>">
            <label for="contenido">Contenido (kg o litros) *</label>
            <input id="contenido" name="contenido" inputmode="decimal" value="<?= valor($form, 'contenido') ?>">
            <span class="hint">Ej.: 0.5 para 500 g · 1.5 para 1.5 L</span>
            <?= error($errores, 'contenido') ?>
        </div>
        <div class="campo">
            <label for="perecible">¿Es perecible?</label>
            <select id="perecible" name="perecible">
                <option value="N">No</option>
                <option value="S" <?= ($form['perecible'] ?? '') === 'S' ? 'selected' : '' ?>>Sí</option>
            </select>
        </div>
        <div class="<?= claseCampo($errores, 'stock') ?>">
            <label for="stock">Stock inicial *</label>
            <input id="stock" name="stock" inputmode="numeric" value="<?= valor($form, 'stock') ?>">
            <?= error($errores, 'stock') ?>
        </div>
        <div class="<?= claseCampo($errores, 'minimo') ?>">
            <label for="minimo">Stock mínimo *</label>
            <input id="minimo" name="minimo" inputmode="numeric" value="<?= valor($form, 'minimo') ?>">
            <?= error($errores, 'minimo') ?>
        </div>
        <div class="<?= claseCampo($errores, 'foto') ?>">
            <label for="foto">Foto del producto</label>
            <input id="foto" name="foto" type="file" accept="image/jpeg,image/png,image/webp" data-vista="fotoPrev">
            <div class="foto-prev" id="fotoPrev"><span>Opcional. Sin foto se muestra el ícono de la categoría.</span></div>
            <?= error($errores, 'foto') ?>
        </div>
    </div>
    <div class="botones">
        <button class="btn" type="submit">Guardar producto</button>
        <a class="btn ghost" href="?accion=listar">Cancelar</a>
    </div>
</form>

<?php require __DIR__ . '/../partials/pie.php'; ?>
