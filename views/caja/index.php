<?php
/**
 * @var ?array $mia @var ?array $resumen @var float $efectivoMia @var array $libres @var array $turnos
 * @var array $abiertas @var array $historial @var callable $efectivo @var array $errores @var array $form @var Usuario $u
 */
$titulo = 'Caja';
$seccion = 'caja';
require __DIR__ . '/../partials/cabecera.php';
$csrf = e(AuthController::token());
?>
<h1>Caja</h1>
<p class="sub"><?= $u->puedeAbrirCaja()
    ? 'R7: solo se puede vender con una sesión de caja abierta. Al terminar tu turno, el Supervisor cuenta el efectivo y cierra la caja.'
    : 'Los cajeros abren su caja y venden. Aquí cuentas el efectivo y cierras las cajas abiertas; la diferencia muestra si falta o sobra.' ?></p>
<?= aviso() ?>

<?php if ($mia): ?>
    <div class="card" style="margin-bottom:18px">
        <div class="cab"><h3 style="margin:0">Tu caja está abierta <?= tag('ABIERTA', 'ok') ?></h3><a class="btn" href="?accion=venta">Ir a vender</a></div>
        <div class="estado-caja">
            <div><span>Caja</span><b><?= e($mia['TIENDA']) ?> · N° <?= (int) $mia['NUMERO_CAJA'] ?></b></div>
            <div><span>Turno</span><b><?= e($mia['TURNO']) ?></b></div>
            <div><span>Abierta</span><b><?= fecha($mia['FECHA_APERTURA']) ?></b></div>
            <div><span>Monto de apertura</span><b><?= sol((float) $mia['MONTO_APERTURA']) ?></b></div>
            <div><span>Ventas emitidas</span><b><?= $resumen['cantidad'] ?> · <?= sol($resumen['total']) ?></b></div>
            <div><span>Efectivo que debe haber</span><b><?= sol($efectivoMia) ?></b></div>
        </div>
        <p class="sub" style="margin:0"><?= $u->puedeCerrarCaja() ? 'Puedes cerrarla abajo, en «Cajas abiertas».' : 'Al terminar tu turno, el Supervisor cierra la caja y cuenta el efectivo.' ?></p>
    </div>
<?php elseif ($u->puedeAbrirCaja()): ?>
    <h2 class="sec">Abrir caja</h2>
    <?php if ($libres): ?>
        <form class="card form" method="post" action="?accion=caja_abrir" novalidate>
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <div class="cols">
                <div class="campo">
                    <label for="caja">Caja</label>
                    <select id="caja" name="caja">
                        <?php foreach ($libres as $c): ?>
                            <option value="<?= (int) $c['ID_CAJA'] ?>"><?= e($c['TIENDA']) ?> · Caja N° <?= (int) $c['NUMERO_CAJA'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="<?= claseCampo($errores, 'turno') ?>">
                    <label for="turno">Turno</label>
                    <select id="turno" name="turno">
                        <?php foreach ($turnos as $id => $t): ?>
                            <option value="<?= (int) $id ?>" <?= ($form['turno'] ?? '') == $id ? 'selected' : '' ?>><?= e($t['NOMBRE']) ?> (<?= e($t['HORA_INICIO']) ?> – <?= e($t['HORA_FIN']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <?= error($errores, 'turno') ?>
                </div>
                <div class="<?= claseCampo($errores, 'monto') ?>">
                    <label for="monto">Monto de apertura (sencillo) *</label>
                    <input id="monto" name="monto" inputmode="decimal" value="<?= valor($form, 'monto') ?>">
                    <?= error($errores, 'monto') ?>
                </div>
            </div>
            <div class="botones"><button class="btn" type="submit">Abrir caja</button></div>
        </form>
    <?php else: ?>
        <div class="vacio-box">No hay cajas libres en tu tienda.</div>
    <?php endif; ?>
<?php endif; ?>

<?php if ($u->puedeCerrarCaja()): ?>
    <h2 class="sec">Cajas abiertas</h2>
    <?php if ($abiertas): ?>
        <div class="tabla"><table>
            <thead><tr><th>Sesión</th><th>Tienda · caja</th><th>Cajero</th><th>Turno</th><th>Abierta</th><th class="num">Apertura</th><th class="num">Efectivo sistema</th><th>Contar y cerrar</th></tr></thead>
            <tbody>
            <?php foreach ($abiertas as $s): ?>
                <tr>
                    <td><?= (int) $s['ID_SESION'] ?></td>
                    <td><?= e($s['TIENDA']) ?> · N° <?= (int) $s['NUMERO_CAJA'] ?></td>
                    <td><?= e(nombreCorto($s['CAJERO_NOMBRES'], $s['CAJERO_APELLIDOS'])) ?></td>
                    <td><?= e($s['TURNO']) ?></td>
                    <td class="nw"><?= fecha($s['FECHA_APERTURA']) ?></td>
                    <td class="num"><?= sol((float) $s['MONTO_APERTURA']) ?></td>
                    <td class="num"><?= sol($efectivo((int) $s['ID_SESION'])) ?></td>
                    <td>
                        <form class="botones" method="post" action="?accion=caja_cerrar" novalidate>
                            <input type="hidden" name="csrf" value="<?= $csrf ?>">
                            <input type="hidden" name="sesion" value="<?= (int) $s['ID_SESION'] ?>">
                            <input class="qty" style="width:100px" name="contado" placeholder="Contado" inputmode="decimal" aria-label="Efectivo contado de la sesión <?= (int) $s['ID_SESION'] ?>">
                            <button class="btn sm" type="submit">Cerrar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php else: ?>
        <div class="vacio-box">No hay cajas abiertas.</div>
    <?php endif; ?>
<?php endif; ?>

<h2 class="sec">Historial y cuadre</h2>
<p class="sub">Diferencia = efectivo contado − efectivo del sistema (negativa = faltante).</p>
<?php require __DIR__ . '/tabla_sesiones.php'; ?>

<?php require __DIR__ . '/../partials/pie.php'; ?>
