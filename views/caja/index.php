<?php
/**
 * @var ?array $mia @var ?array $resumen @var float $efectivoMia @var array $libres @var array $turnos
 * @var array $abiertas @var array $historial @var callable $efectivo @var callable $vendidoCaja
 * @var array $cajas @var array $tiendas @var array $errores @var array $form @var Usuario $u
 */
$titulo = 'Caja';
$seccion = 'caja';
require __DIR__ . '/../partials/cabecera.php';
$csrf = e(AuthController::token());
// Lugar de cada caja: dirección de la tienda + zona de cajas
$lugar = [];
foreach ($cajas as $c) {
    $lugar[(int) $c['ID_CAJA']] = $c['DIRECCION'] . ', ' . $c['DISTRITO'] . ' · ' . ($c['ZONA'] ?? 'Zona de cajas');
}
?>
<h1>Caja</h1>
<p class="sub"><?= $u->puedeAbrirCaja()
    ? 'R7: solo se puede vender con una sesión de caja abierta. Al terminar tu turno, el Supervisor cuenta el efectivo y cierra la caja.'
    : ($u->puedeVerDineroCajas()
        ? 'Cuánto dinero en efectivo tiene ahora cada caja abierta de las 3 tiendas. Contar y cerrar las cajas lo hace el Supervisor.'
        : 'Los cajeros abren su caja y venden. Aquí cuentas el efectivo y cierras las cajas abiertas; la diferencia muestra si falta o sobra.') ?></p>
<?= aviso() ?>

<?php if ($mia): ?>
    <div class="card" style="margin-bottom:18px">
        <div class="cab"><h3 style="margin:0">Tu caja está abierta <?= tag('ABIERTA', 'ok') ?></h3><a class="btn" href="?accion=venta">Ir a vender</a></div>
        <div class="estado-caja">
            <div><span>Caja</span><b><?= e($mia['TIENDA']) ?> · N° <?= (int) $mia['NUMERO_CAJA'] ?></b></div>
            <div><span>Lugar</span><b><?= e($lugar[(int) $mia['ID_CAJA']] ?? '') ?></b></div>
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
                <div class="campo todo">
                    <label for="caja">Tienda y caja</label>
                    <select id="caja" name="caja">
                        <?php foreach ($tiendas as $t): ?>
                            <optgroup label="<?= e($t['NOMBRE'] . ' · ' . $t['DIRECCION'] . ', ' . $t['DISTRITO']) ?>">
                                <?php foreach ($libres as $c): if ((int) $c['ID_TIENDA'] !== (int) $t['ID_TIENDA']) continue; ?>
                                    <option value="<?= (int) $c['ID_CAJA'] ?>" <?= (string) ($form['caja'] ?? '') === (string) $c['ID_CAJA'] || (!isset($form['caja']) && (int) $c['ID_TIENDA'] === $u->getIdTienda()) ? 'selected' : '' ?>>
                                        <?= e($t['NOMBRE']) ?> · Caja N° <?= (int) $c['NUMERO_CAJA'] ?> · <?= e($c['ZONA'] ?? 'Zona de cajas') ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                    <span class="hint">Solo salen las cajas libres. Abajo ves todas las cajas de las 3 tiendas y dónde están.</span>
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
        <div class="vacio-box">No hay cajas libres en este momento.</div>
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

<?php if ($u->puedeVerDineroCajas()): $totalCajas = 0.0; ?>
    <h2 class="sec">Dinero en cada caja · 3 tiendas</h2>
    <div class="tabla"><table>
        <thead><tr><th>Caja</th><th>Lugar</th><th>Cajero</th><th>Turno</th><th>Abierta desde</th><th class="num">Apertura</th><th class="num">Efectivo en caja</th></tr></thead>
        <tbody>
        <?php foreach ($tiendas as $t):
            $deTienda = array_filter($abiertas, fn($x) => (int) $x['ID_TIENDA'] === (int) $t['ID_TIENDA']);
            $subtotal = 0.0; ?>
            <tr class="fila-tienda"><td colspan="7"><b><?= e($t['NOMBRE']) ?></b> · <?= e($t['DIRECCION'] . ', ' . $t['DISTRITO']) ?></td></tr>
            <?php foreach ($deTienda as $s): $dinero = $efectivo((int) $s['ID_SESION']); $subtotal += $dinero; ?>
                <tr>
                    <td>N° <?= (int) $s['NUMERO_CAJA'] ?></td>
                    <td><?= e($lugar[(int) $s['ID_CAJA']] ?? '') ?></td>
                    <td><?= e(nombreCorto($s['CAJERO_NOMBRES'], $s['CAJERO_APELLIDOS'])) ?></td>
                    <td><?= e($s['TURNO']) ?></td>
                    <td class="nw"><?= fecha($s['FECHA_APERTURA']) ?></td>
                    <td class="num"><?= sol((float) $s['MONTO_APERTURA']) ?></td>
                    <td class="num"><b><?= sol($dinero) ?></b></td>
                </tr>
            <?php endforeach; $totalCajas += $subtotal; ?>
            <?php if (!$deTienda): ?><tr><td colspan="7" class="sub" style="margin:0">Sin cajas abiertas ahora.</td></tr><?php endif; ?>
            <tr><td colspan="6" class="num">Total <?= e($t['NOMBRE']) ?></td><td class="num"><b><?= sol($subtotal) ?></b></td></tr>
        <?php endforeach; ?>
        <tr class="fila-total"><td colspan="6"><b>Total en las 3 tiendas</b></td><td class="num"><b><?= sol($totalCajas) ?></b></td></tr>
        </tbody>
    </table></div>
    <p class="sub" style="font-size:.8rem">Efectivo en caja = monto de apertura + pagos en efectivo de las ventas emitidas. Yape, Plin y tarjeta no entran a la caja.</p>
<?php else: ?>
    <h2 class="sec">Cajas de las 3 tiendas</h2>
    <div class="tabla"><table>
        <thead><tr><th>Tienda</th><th>Caja</th><th>Lugar</th><th>Estado</th></tr></thead>
        <tbody>
        <?php foreach ($cajas as $c): ?>
            <tr>
                <td><b><?= e($c['TIENDA']) ?></b></td>
                <td>N° <?= (int) $c['NUMERO_CAJA'] ?></td>
                <td><?= e($lugar[(int) $c['ID_CAJA']]) ?></td>
                <td><?= $c['ESTADO_CAJA'] !== 'A' ? tag('Fuera de servicio', 'gris')
                    : ($c['ID_SESION'] ? tag('Abierta · ' . nombreCorto($c['CAJERO_NOMBRES'], $c['CAJERO_APELLIDOS']), 'warn') : tag('Libre', 'ok')) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>

    <h2 class="sec">Historial y cuadre</h2>
    <p class="sub">Diferencia = efectivo contado − efectivo del sistema (negativa = faltante).</p>
    <?php require __DIR__ . '/tabla_sesiones.php'; ?>
<?php endif; ?>

<?php require __DIR__ . '/../partials/pie.php'; ?>
