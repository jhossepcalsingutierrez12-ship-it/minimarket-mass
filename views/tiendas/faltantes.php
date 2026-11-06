<?php
/**
 * Faltantes de caja (solo Administrador): cajas que el Supervisor cerró con menos efectivo del que dice el sistema.
 * Desde aquí el Administrador puede PREGUNTAR al cajero qué pasó con el dinero o REPORTAR el faltante.
 * @var array $lista @var string $desde @var int $nuevos @var float $total
 * @var array $consultas @var bool $hayConsultas @var array $errores @var array $form
 */
$titulo = 'Faltantes de caja';
$seccion = 'faltantes';
require __DIR__ . '/../partials/cabecera.php';
$tiendasCon = count(array_unique(array_column($lista, 'TIENDA')));
$csrf = e(AuthController::token());
$sugerencias = [
    'PREGUNTA' => ['Hola, al cerrar tu caja faltó dinero. ¿Qué pasó? ¿Diste algún vuelto de más?',
                   '¿Hubo alguna venta que cobraste mal o un pago con tarjeta/Yape que registraste como efectivo?'],
    'REPORTE'  => ['Se reporta el faltante de tu caja. Por favor cuenta bien los vueltos y revisa cada pago antes de cerrar.',
                   'Es el segundo faltante este mes. Conversaremos con tu supervisor.'],
];
?>
<h1>Faltantes de caja</h1>
<p class="sub">Cada vez que un Supervisor cierra una caja y cuenta <b>menos dinero</b> del que dice el sistema, te llega un aviso aquí (la campana de arriba) y por correo.</p>
<?php $ayuda = [
    'Al cerrar una caja, el Supervisor cuenta el efectivo. Si cuenta <b>menos</b> de lo que dice el sistema, es un <b>faltante</b> y te llega el aviso al momento.',
    'La <b>campana</b> de arriba muestra cuántos faltantes nuevos hay. Al abrir esta página quedan como vistos.',
    'Con <b>Preguntar o reportar al cajero</b> le escribes al cajero de esa caja: le llega al entrar al sistema (y por correo) y te responde aquí mismo.',
]; require __DIR__ . '/../partials/ayuda.php'; ?>
<?= aviso() ?>

<div class="kpis">
    <div class="card kpi <?= $nuevos ? 'kpi-mal' : '' ?>"><b><?= $nuevos ?></b><span>Nuevos desde tu última revisión</span></div>
    <div class="card kpi <?= $lista ? 'kpi-ojo' : '' ?>"><b><?= count($lista) ?></b><span>Faltantes en 30 días</span></div>
    <div class="card kpi <?= $total > 0 ? 'kpi-mal' : '' ?>"><b><?= sol($total) ?></b><span>Dinero que faltó</span></div>
    <div class="card kpi"><b><?= $tiendasCon ?></b><span>Tiendas con faltantes</span></div>
</div>

<?php if (!$hayConsultas && $lista): ?>
    <div class="msg err">Para preguntar o reportar al cajero falta la tabla de preguntas. Corre una vez en la terminal (carpeta del proyecto):<br>
        <code>mysql -u root -P 3306 --default-character-set=utf8mb4 minimarket_mass -e "source database/consultas_caja.sql"</code></div>
<?php endif; ?>

<?php if (!$lista): ?>
    <?= vacio('Sin faltantes de caja', 'En los últimos 30 días todas las cajas se cerraron completas o con sobrante.', 'ok') ?>
<?php else: ?>
    <div class="tabla"><table>
        <thead><tr><th>Cierre</th><th>Tienda y caja</th><th>Cajero</th><th>Cerró</th>
            <th class="num">Sistema</th><th class="num">Contado</th><th class="num">Falta</th></tr></thead>
        <tbody>
        <?php foreach ($lista as $f): $nuevo = $f['FECHA_CIERRE'] > $desde; ?>
            <tr class="<?= $nuevo ? 'fila-nueva' : '' ?>">
                <td class="nw"><?= fecha($f['FECHA_CIERRE']) ?><?= $nuevo ? '<br>' . tag('Nuevo', 'bad') : '' ?></td>
                <td><b><?= e($f['TIENDA']) ?></b><br><span class="sub" style="font-size:.8rem">Caja N° <?= (int) $f['NUMERO_CAJA'] ?> · sesión <?= (int) $f['ID_SESION'] ?></span></td>
                <td><?= e(nombreCorto($f['CAJERO_NOMBRES'], $f['CAJERO_APELLIDOS'])) ?><br><span class="sub" style="font-size:.8rem">Turno <?= e($f['TURNO']) ?></span></td>
                <td><?= e(nombreCorto($f['SUP_NOMBRES'], $f['SUP_APELLIDOS'])) ?></td>
                <td class="num"><?= sol((float) $f['EFECTIVO_SISTEMA']) ?></td>
                <td class="num"><?= sol((float) $f['EFECTIVO_CONTADO']) ?></td>
                <td class="num"><b class="tag bad"><?= sol(-(float) $f['DIFERENCIA']) ?></b></td>
            </tr>
            <?php if ($hayConsultas): $id = (int) $f['ID_SESION']; $hilo = $consultas[$id] ?? [];
                $errAqui = (int) ($errores['sesion'] ?? 0) === $id; $tipoSel = $errAqui ? ($form['tipo'] ?? 'PREGUNTA') : 'PREGUNTA'; ?>
            <tr class="fila-consulta <?= $nuevo ? 'fila-nueva' : '' ?>" id="sesion-<?= $id ?>"><td colspan="7">
                <?php foreach ($hilo as $q): $respNueva = $q['ESTADO'] === 'RESPONDIDA' && $q['FECHA_RESPUESTA'] > $desde; ?>
                    <div class="hilo">
                        <p class="hilo-msg"><?= tag(NotificacionRepository::TIPOS[$q['TIPO']], $q['TIPO'] === 'REPORTE' ? 'bad' : 'warn') ?>
                            <b><?= e(nombreCorto($q['ADMIN_NOMBRES'], $q['ADMIN_APELLIDOS'])) ?></b> <span class="sub">· <?= fecha($q['FECHA_ENVIO']) ?></span><br>
                            <?= e($q['MENSAJE']) ?></p>
                        <?php if ($q['ESTADO'] === 'RESPONDIDA'): ?>
                            <p class="hilo-msg respuesta"><?= $respNueva ? tag('Respuesta nueva', 'ok') : tag('Respondió', 'ok') ?>
                                <b><?= e(nombreCorto($q['CAJERO_NOMBRES'], $q['CAJERO_APELLIDOS'])) ?></b> <span class="sub">· <?= fecha($q['FECHA_RESPUESTA']) ?></span><br>
                                <?= e($q['RESPUESTA']) ?></p>
                        <?php else: ?>
                            <p class="hilo-espera"><?= icono('reloj', 16) ?> Esperando la respuesta de <?= e(nombreCorto($q['CAJERO_NOMBRES'], $q['CAJERO_APELLIDOS'])) ?>…</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <details class="consultar"<?= $errAqui ? ' open' : '' ?>>
                    <summary class="btn sm ghost"><?= icono('lapiz', 16) ?> <?= $hilo ? 'Volver a escribirle' : 'Preguntar o reportar' ?> a <?= e(nombreCorto($f['CAJERO_NOMBRES'], $f['CAJERO_APELLIDOS'])) ?></summary>
                    <form class="form consultar-form" method="post" action="?accion=consultar_faltante" novalidate>
                        <input type="hidden" name="csrf" value="<?= $csrf ?>">
                        <input type="hidden" name="sesion" value="<?= $id ?>">
                        <fieldset class="<?= $errAqui && isset($errores['tipo']) ? 'campo mal' : 'campo' ?>">
                            <legend>¿Qué quieres hacer?</legend>
                            <label class="opcion-tipo"><input type="radio" name="tipo" value="PREGUNTA" <?= $tipoSel === 'PREGUNTA' ? 'checked' : '' ?>> <b>Preguntar</b> qué pasó con el dinero</label>
                            <label class="opcion-tipo"><input type="radio" name="tipo" value="REPORTE" <?= $tipoSel === 'REPORTE' ? 'checked' : '' ?>> <b>Reportar</b> el faltante (llamada de atención)</label>
                            <?= $errAqui ? error($errores, 'tipo') : '' ?>
                        </fieldset>
                        <div class="<?= $errAqui && isset($errores['mensaje']) ? 'campo mal' : 'campo' ?>">
                            <label for="msg<?= $id ?>">Mensaje para el cajero *</label>
                            <textarea id="msg<?= $id ?>" name="mensaje" rows="3" maxlength="500"><?= $errAqui ? valor($form, 'mensaje') : '' ?></textarea>
                            <span class="hint">Usa un mensaje rápido o escribe el tuyo:</span>
                            <div class="sugerencias">
                                <?php foreach ($sugerencias as $t => $textos): foreach ($textos as $txt): ?>
                                    <button type="button" class="chip sm" data-tipo="<?= $t ?>" data-texto="<?= e($txt) ?>"><?= e(mb_strimwidth($txt, 0, 46, '…')) ?></button>
                                <?php endforeach; endforeach; ?>
                            </div>
                            <?= $errAqui ? error($errores, 'mensaje') : '' ?>
                        </div>
                        <div class="botones"><button class="btn amarillo" type="submit">Enviar al cajero</button></div>
                    </form>
                </details>
            </td></tr>
            <?php endif; ?>
        <?php endforeach; ?>
        </tbody>
    </table></div>
<?php endif; ?>
<p><a class="btn sm ghost" href="?accion=caja"><?= icono('caja') ?> Ver el dinero de las cajas</a></p>

<?php require __DIR__ . '/../partials/pie.php'; ?>
