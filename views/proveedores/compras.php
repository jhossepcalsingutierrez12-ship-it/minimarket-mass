<?php
/**
 * Compras de mercadería (solo Administrador)
 * @var array $compras @var bool $sinTablas @var array $proveedores @var Producto[] $productos @var array $tiendas @var array $errores @var array $form
 */
$titulo = 'Compras';
$seccion = 'compras';
require __DIR__ . '/../partials/cabecera.php';
$filas = max(5, count($form['lineas']));
?>
<h1>Compras de mercadería</h1>
<p class="sub">Registra lo que llegó de un proveedor. Cada producto crea su lote con la fecha de recepción de hoy y su cantidad
    <b>se suma sola al inventario</b>.</p>
<?php $ayuda = [
    'Aquí registras la <b>mercadería que llegó</b> de un proveedor: qué productos, cuántas unidades y a qué costo.',
    'Al registrarla, el sistema <b>crea el lote</b> de cada producto con la fecha de hoy y <b>suma las unidades al stock</b> automáticamente.',
    'El costo unitario sirve para calcular las <b>ganancias</b> en Reportes y el valor de las <b>mermas</b>.',
]; require __DIR__ . '/../partials/ayuda.php'; ?>
<?= aviso() ?>

<?php if ($sinTablas): ?>
    <?php require __DIR__ . '/../partials/falta_admin_sql.php'; ?>
<?php else: ?>
    <h2 class="sec">Registrar compra</h2>
    <?php if (!$proveedores): ?>
        <div class="vacio-box">Primero registra un proveedor en <a href="?accion=proveedores">Proveedores</a>.</div>
    <?php else: ?>
    <form class="card form" method="post" action="?accion=compras" novalidate id="fCompra">
        <input type="hidden" name="csrf" value="<?= e(AuthController::token()) ?>">
        <div class="cols">
            <div class="<?= claseCampo($errores, 'proveedor') ?>">
                <label for="proveedor">Proveedor *</label>
                <select id="proveedor" name="proveedor">
                    <option value="">Elige…</option>
                    <?php foreach ($proveedores as $p): ?>
                        <option value="<?= (int) $p['ID_PROVEEDOR'] ?>" <?= $form['proveedor'] === (string) $p['ID_PROVEEDOR'] ? 'selected' : '' ?>><?= e($p['RAZON_SOCIAL']) ?><?= $p['RUBRO'] ? ' · ' . e($p['RUBRO']) : '' ?></option>
                    <?php endforeach; ?>
                </select>
                <?= error($errores, 'proveedor') ?>
            </div>
            <div class="<?= claseCampo($errores, 'tienda') ?>">
                <label for="tienda">Tienda que recibe *</label>
                <select id="tienda" name="tienda">
                    <option value="">Elige…</option>
                    <?php foreach ($tiendas as $t): ?>
                        <option value="<?= (int) $t['ID_TIENDA'] ?>" <?= $form['tienda'] === (string) $t['ID_TIENDA'] ? 'selected' : '' ?>><?= e($t['NOMBRE']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= error($errores, 'tienda') ?>
            </div>
            <div class="campo">
                <label for="documento">N° de factura o guía</label>
                <input id="documento" name="documento" maxlength="20" value="<?= valor($form, 'documento') ?>" placeholder="F001-000123">
            </div>
        </div>

        <div class="tabla" style="margin:6px 0 0"><table class="lineas-compra">
            <thead><tr><th>Producto</th><th class="num">Cantidad</th><th class="num">Costo unitario (S/)</th><th>Vence</th><th class="num">Importe</th></tr></thead>
            <tbody>
            <?php for ($i = 0; $i < $filas; $i++): $l = $form['lineas'][$i] ?? ['producto' => '', 'cantidad' => '', 'costo' => '', 'vence' => '']; ?>
                <tr class="<?= isset($errores["linea$i"]) ? 'fila-mal' : '' ?>">
                    <td>
                        <select name="producto[<?= $i ?>]" aria-label="Producto de la fila <?= $i + 1 ?>">
                            <option value="">—</option>
                            <?php foreach ($productos as $p): ?>
                                <option value="<?= $p->getIdProducto() ?>" data-per="<?= $p->esPerecible() ? 1 : 0 ?>" <?= $l['producto'] === (string) $p->getIdProducto() ? 'selected' : '' ?>><?= e($p->getCodigoSku() . ' · ' . $p->getNombre()) ?> (stock <?= $p->getStockActual() ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <?= isset($errores["linea$i"]) ? '<span class="err">' . e($errores["linea$i"]) . '</span>' : '' ?>
                    </td>
                    <td class="num"><input class="qty" name="cantidad[<?= $i ?>]" inputmode="numeric" value="<?= e($l['cantidad']) ?>" aria-label="Cantidad de la fila <?= $i + 1 ?>"></td>
                    <td class="num"><input class="qty" name="costo[<?= $i ?>]" inputmode="decimal" value="<?= e($l['costo']) ?>" aria-label="Costo unitario de la fila <?= $i + 1 ?>"></td>
                    <td><input type="date" name="vence[<?= $i ?>]" value="<?= e($l['vence']) ?>" aria-label="Vencimiento de la fila <?= $i + 1 ?>"></td>
                    <td class="num importe">—</td>
                </tr>
            <?php endfor; ?>
            </tbody>
            <tfoot><tr><td colspan="4" class="num"><b>Total de la compra</b></td><td class="num"><b id="totalCompra">S/ 0.00</b></td></tr></tfoot>
        </table></div>
        <?= error($errores, 'lineas') ?>
        <p class="sub" style="font-size:.8rem;margin:8px 0 0">Deja vacías las filas que no uses. Los perecibles necesitan fecha de vencimiento.
            El costo unitario es lo que pagaste por unidad: sirve para calcular las ganancias y el valor de las mermas.</p>
        <div class="botones"><button class="btn" type="submit">Registrar compra y sumar al stock</button></div>
    </form>
    <?php endif; ?>

    <h2 class="sec">Compras registradas</h2>
    <div class="tabla"><table>
        <thead><tr><th>N°</th><th>Fecha de recepción</th><th>Proveedor</th><th>Documento</th><th>Tienda</th><th>Productos</th><th class="num">Total</th><th>Registró</th></tr></thead>
        <tbody>
        <?php foreach ($compras as $c): ?>
            <tr>
                <td><?= (int) $c['ID_COMPRA'] ?></td>
                <td class="nw"><?= fecha($c['FECHA_RECEPCION']) ?></td>
                <td><?= e($c['RAZON_SOCIAL']) ?></td>
                <td class="nw sku"><?= e($c['NUMERO_DOC'] ?? '—') ?></td>
                <td><?= e($c['TIENDA']) ?></td>
                <td style="font-size:.84rem">
                    <?php foreach ($c['DETALLE'] as $d): ?>
                        <?= (int) $d['CANTIDAD'] ?> × <?= e($d['NOMBRE']) ?> a <?= sol((float) $d['COSTO_UNITARIO']) ?>
                        <span class="sku">· lote <?= e($d['CODIGO_LOTE'] ?? '—') ?><?= $d['FECHA_VENCIMIENTO'] ? ' · vence ' . fecha($d['FECHA_VENCIMIENTO']) : '' ?></span><br>
                    <?php endforeach; ?>
                </td>
                <td class="num"><b><?= sol((float) $c['TOTAL']) ?></b></td>
                <td><?= e(nombreCorto($c['NOMBRES'], $c['APELLIDOS'])) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$compras): ?><tr><td colspan="8">Todavía no hay compras.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
<?php endif; ?>

<?php require __DIR__ . '/../partials/pie.php'; ?>
