<?php
/**
 * Al entrar, el cajero abre su caja: elige la tienda, una de sus 3 cajas, el turno y el monto.
 * @var array $tiendas @var array $libres @var array $cajas @var array $turnos @var array $abiertasTienda
 * @var array $errores @var array $form @var Usuario $u
 */
$titulo = 'Abre tu caja';
$seccion = 'caja';
require __DIR__ . '/../partials/cabecera.php';
$idsLibres = array_map(fn($c) => (int) $c['ID_CAJA'], $libres);
// Caja marcada: la que eligió antes o la primera libre de la tienda marcada
$cajaSel = $form['caja'] ?? '';
if ($cajaSel === '') {
    foreach ($libres as $c) {
        if ((string) $c['ID_TIENDA'] === $form['tienda']) {
            $cajaSel = (string) $c['ID_CAJA'];
            break;
        }
    }
}
?>
<h1>Abre tu caja</h1>
<p class="sub">Hola, <?= e(explode(' ', $u->getNombreCompleto())[0]) ?>. Para empezar a vender, elige la tienda, una de sus 3 cajas y con cuánto sencillo empiezas
    (R7: sin caja abierta no hay venta).</p>
<?= aviso() ?>

<form class="card form" method="post" action="?accion=caja_abrir" novalidate style="max-width:860px" id="fInicioCaja">
    <input type="hidden" name="csrf" value="<?= e(AuthController::token()) ?>">
    <input type="hidden" name="desde" value="inicio">

    <fieldset class="elegir-tienda">
        <legend>1. ¿En qué tienda abres tu caja?</legend>
        <div class="tiendas-opciones">
            <?php foreach ($tiendas as $t): $libresT = count(array_filter($libres, fn($c) => (int) $c['ID_TIENDA'] === (int) $t['ID_TIENDA'])); ?>
                <label class="opcion-tienda">
                    <input type="radio" name="tienda" value="<?= (int) $t['ID_TIENDA'] ?>" <?= $form['tienda'] === (string) $t['ID_TIENDA'] ? 'checked' : '' ?> <?= $libresT ? '' : 'disabled' ?>>
                    <span>
                        <b><?= e(str_replace('Mass ', '', $t['NOMBRE'])) ?></b>
                        <small><?= e($t['DIRECCION'] . ', ' . $t['DISTRITO']) ?></small>
                        <small><?= $libresT ? $libresT . ' de 3 cajas libres' : 'Las 3 cajas están ocupadas' ?></small>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>
    </fieldset>

    <fieldset class="elegir-tienda" style="margin-top:16px">
        <legend>2. Elige tu caja</legend>
        <div class="tiendas-opciones cajas-opciones">
            <?php foreach ($cajas as $c): $libre = in_array((int) $c['ID_CAJA'], $idsLibres, true); ?>
                <label class="opcion-tienda opcion-caja" data-tienda="<?= (int) $c['ID_TIENDA'] ?>">
                    <input type="radio" name="caja" value="<?= (int) $c['ID_CAJA'] ?>" <?= $cajaSel === (string) $c['ID_CAJA'] && $libre ? 'checked' : '' ?> <?= $libre ? '' : 'disabled' ?>>
                    <span>
                        <b>Caja N° <?= (int) $c['NUMERO_CAJA'] ?></b>
                        <small><?= e(str_replace('Mass ', '', $c['TIENDA'])) ?> · <?= e($c['ZONA'] ?? 'Zona de cajas') ?></small>
                        <small><?= $c['ESTADO_CAJA'] !== 'A' ? 'Fuera de servicio'
                            : ($c['ID_SESION'] ? 'Ocupada · ' . e(nombreCorto($c['CAJERO_NOMBRES'], $c['CAJERO_APELLIDOS'])) : 'Libre') ?></small>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>
        <?= error($errores, 'caja') ?>
    </fieldset>

    <div class="cols" style="margin-top:16px">
        <div class="<?= claseCampo($errores, 'turno') ?>">
            <label for="turno">3. Turno</label>
            <select id="turno" name="turno">
                <?php foreach ($turnos as $id => $t): ?>
                    <option value="<?= (int) $id ?>" <?= $form['turno'] === (string) $id ? 'selected' : '' ?>><?= e($t['NOMBRE']) ?> (<?= e($t['HORA_INICIO']) ?> – <?= e($t['HORA_FIN']) ?>)</option>
                <?php endforeach; ?>
            </select>
            <?= error($errores, 'turno') ?>
        </div>
        <div class="<?= claseCampo($errores, 'monto') ?>">
            <label for="monto">4. Monto de apertura (sencillo) *</label>
            <input id="monto" name="monto" inputmode="decimal" value="<?= valor($form, 'monto') ?>" autofocus>
            <span class="hint">El dinero en monedas y billetes con el que empiezas, por ejemplo 200.00</span>
            <?= error($errores, 'monto') ?>
        </div>
    </div>
    <div class="botones">
        <button class="btn amarillo" type="submit">Abrir caja y empezar a vender</button>
        <a class="btn ghost" href="?accion=listar">Ahora no</a>
    </div>
</form>

<?php require __DIR__ . '/../partials/pie.php'; ?>
