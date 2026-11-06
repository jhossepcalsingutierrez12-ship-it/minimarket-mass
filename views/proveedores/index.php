<?php
/** Proveedores (solo Administrador) @var array $lista @var bool $sinTablas @var array $errores @var array $form */
$titulo = 'Proveedores';
$seccion = 'proveedores';
require __DIR__ . '/../partials/cabecera.php';
$csrf = e(AuthController::token());
$ayuda = [
    'Aquí están las <b>empresas que le venden mercadería a Mass</b> (lácteos, abarrotes, bebidas, limpieza), con su RUC y a quién llamar.',
    'Cada proveedor tiene su <b>categoría</b> (Abarrotes, Lácteos, Bebidas o Limpieza): al comprarle, solo salen los productos de esa categoría.',
    'Cada proveedor muestra <b>qué nos vende</b>, cuántas compras se le hicieron, cuánto se le compró en total y la fecha de la última compra.',
    'Al registrar una compra en <a href="?accion=compras">Compras</a> eliges a uno de ellos. Si dejas de trabajar con uno, <b>desactívalo</b>: no se borra (sus compras siguen en el historial), solo deja de salir al comprar.',
];
?>
<h1>Proveedores</h1>
<p class="sub">Empresas que le venden mercadería a Mass.</p>
<?php require __DIR__ . '/../partials/ayuda.php'; ?>
<?= aviso() ?>

<?php if ($sinTablas): ?>
    <?php require __DIR__ . '/../partials/falta_admin_sql.php'; ?>
<?php else: ?>
    <div class="tabla"><table>
        <thead><tr><th>Proveedor y qué nos vende</th><th>RUC</th><th>Contacto</th><th class="num">Compras</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($lista as $p): ?>
            <tr>
                <td><b><?= e($p['RAZON_SOCIAL']) ?></b>
                    <?php $cp = $p['CATEGORIA_SKU'] ?? null; ?>
                    <span class="prov-rubro"><?= $cp && isset(CATEGORIAS[$cp]) ? '<span class="cat-ref cat-' . e($cp) . '">' . imagenCategoria($cp, 18) . e(CATEGORIAS[$cp]['nombre']) . '</span> ' : '' ?><?= e($p['RUBRO'] ?? 'Sin rubro registrado') ?></span></td>
                <td class="nw sku"><?= e($p['RUC']) ?></td>
                <td><?= e($p['CONTACTO'] ?? '—') ?>
                    <?= $p['TELEFONO'] ? '<br><span class="sub" style="font-size:.8rem">Tel. ' . e($p['TELEFONO']) . '</span>' : '' ?>
                    <?= $p['CORREO'] ? '<br><span class="sub" style="font-size:.8rem">' . e($p['CORREO']) . '</span>' : '' ?></td>
                <td class="num nw"><?= (int) $p['COMPRAS'] ?> · <b><?= sol((float) $p['COMPRADO']) ?></b>
                    <br><span class="sub" style="font-size:.78rem"><?= $p['ULTIMA'] ? 'Última: ' . fecha($p['ULTIMA']) : 'Sin compras aún' ?></span></td>
                <td><?= $p['ESTADO'] === 'A' ? tag('Activo', 'ok') : tag('Inactivo', 'gris') ?></td>
                <td>
                    <form method="post" action="?accion=estado_proveedor">
                        <input type="hidden" name="csrf" value="<?= $csrf ?>">
                        <input type="hidden" name="id" value="<?= (int) $p['ID_PROVEEDOR'] ?>">
                        <input type="hidden" name="estado" value="<?= $p['ESTADO'] === 'A' ? 'I' : 'A' ?>">
                        <button class="btn sm ghost" type="submit"><?= $p['ESTADO'] === 'A' ? 'Desactivar' : 'Activar' ?></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$lista): ?><tr><td colspan="6">Todavía no hay proveedores.</td></tr><?php endif; ?>
        </tbody>
    </table></div>

    <h2 class="sec">Registrar proveedor</h2>
    <form class="card form" method="post" action="?accion=proveedores" novalidate>
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <div class="cols">
            <div class="<?= claseCampo($errores, 'ruc') ?>">
                <label for="ruc">RUC *</label>
                <input id="ruc" name="ruc" inputmode="numeric" maxlength="11" value="<?= valor($form, 'ruc') ?>" placeholder="20601234567">
                <span class="hint">11 dígitos: empieza con 20 (empresa) o 10 (persona con negocio).</span>
                <?= error($errores, 'ruc') ?>
            </div>
            <div class="<?= claseCampo($errores, 'razon') ?>">
                <label for="razon">Razón social *</label>
                <input id="razon" name="razon" maxlength="120" value="<?= valor($form, 'razon') ?>" placeholder="Distribuidora ... S.A.C.">
                <?= error($errores, 'razon') ?>
            </div>
            <div class="<?= claseCampo($errores, 'categoria') ?>">
                <label for="categoria">Categoría de sus productos</label>
                <select id="categoria" name="categoria">
                    <option value="">De todo (varias categorías)</option>
                    <?php foreach (CATEGORIAS as $c => $cat): ?>
                        <option value="<?= e($c) ?>" <?= ($form['categoria'] ?? '') === $c ? 'selected' : '' ?>><?= e($cat['nombre']) ?> (<?= e($c) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <span class="hint">Al registrar una compra a este proveedor solo saldrán productos de esta categoría.</span>
                <?= error($errores, 'categoria') ?>
            </div>
            <div class="campo">
                <label for="rubro">¿Qué nos vende?</label>
                <input id="rubro" name="rubro" maxlength="150" value="<?= valor($form, 'rubro') ?>" placeholder="Ej.: gaseosas, agua y refrescos">
            </div>
            <div class="campo">
                <label for="contacto">Persona de contacto</label>
                <input id="contacto" name="contacto" maxlength="80" value="<?= valor($form, 'contacto') ?>">
            </div>
            <div class="<?= claseCampo($errores, 'telefono') ?>">
                <label for="telefono">Teléfono</label>
                <input id="telefono" name="telefono" inputmode="tel" maxlength="15" value="<?= valor($form, 'telefono') ?>">
                <?= error($errores, 'telefono') ?>
            </div>
            <div class="<?= claseCampo($errores, 'correo') ?>">
                <label for="correo">Correo</label>
                <input id="correo" name="correo" type="email" maxlength="120" value="<?= valor($form, 'correo') ?>">
                <?= error($errores, 'correo') ?>
            </div>
        </div>
        <div class="botones"><button class="btn" type="submit">Registrar proveedor</button></div>
    </form>
<?php endif; ?>

<?php require __DIR__ . '/../partials/pie.php'; ?>
