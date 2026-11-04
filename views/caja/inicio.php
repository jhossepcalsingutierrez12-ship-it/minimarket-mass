<?php
/**
 * Al entrar, el cajero abre su caja: tienda, caja, turno y monto.
 * @var array $tiendas @var array $libres @var array $turnos @var array $errores @var array $form @var Usuario $u
 */
$titulo = 'Abre tu caja';
$seccion = 'caja';
require __DIR__ . '/../partials/cabecera.php';
?>
<h1>Abre tu caja</h1>
<p class="sub">Hola, <?= e(explode(' ', $u->getNombreCompleto())[0]) ?>. Para empezar a vender, di en qué tienda abres tu caja y con cuánto sencillo empiezas (R7: sin caja abierta no hay venta).</p>
<?= aviso() ?>

<form class="card form" method="post" action="?accion=caja_abrir" novalidate style="max-width:820px">
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
                        <small><?= $libresT ? $libresT . ' caja' . ($libresT === 1 ? '' : 's') . ' libre' . ($libresT === 1 ? '' : 's') : 'Sin cajas libres' ?></small>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>
    </fieldset>

    <div class="cols">
        <div class="<?= claseCampo($errores, 'caja') ?>">
            <label for="caja">2. Caja</label>
            <select id="caja" name="caja">
                <?php foreach ($tiendas as $t): ?>
                    <optgroup label="<?= e($t['NOMBRE']) ?>" data-tienda="<?= (int) $t['ID_TIENDA'] ?>">
                        <?php foreach ($libres as $c): if ((int) $c['ID_TIENDA'] !== (int) $t['ID_TIENDA']) continue; ?>
                            <option value="<?= (int) $c['ID_CAJA'] ?>" data-tienda="<?= (int) $c['ID_TIENDA'] ?>" <?= ($form['caja'] ?? '') === (string) $c['ID_CAJA'] ? 'selected' : '' ?>>
                                Caja N° <?= (int) $c['NUMERO_CAJA'] ?> · <?= e($c['ZONA'] ?? 'Zona de cajas') ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
            <?= error($errores, 'caja') ?>
        </div>
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
