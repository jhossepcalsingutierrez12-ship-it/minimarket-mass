<?php
/**
 * Cajero: lo que el Administrador le preguntó o reportó por un faltante de dinero en su caja, y su respuesta.
 * @var array $lista @var bool $hayConsultas @var array $errores @var array $form
 */
$titulo = 'Preguntas del Administrador';
$seccion = 'consultas';
require __DIR__ . '/../partials/cabecera.php';
$pendientes = count(array_filter($lista, fn($q) => $q['ESTADO'] === 'PENDIENTE'));
?>
<h1>Preguntas del Administrador</h1>
<p class="sub">Cuando tu caja se cierra con <b>menos dinero</b> del que dice el sistema, el Administrador puede preguntarte qué pasó
    o reportarlo. Responde con tu explicación: él la ve al momento.</p>
<?= aviso() ?>

<?php if (!$lista): ?>
    <?= vacio('No tienes preguntas', 'El Administrador no te ha preguntado ni reportado nada sobre tu caja.', 'ok',
        CAJERO_SIN_CAJA ? '<a class="btn" href="?accion=caja_inicio">' . icono('caja') . ' Abrir mi caja</a>' : '') ?>
<?php else: ?>
    <?php if ($pendientes): ?>
        <div class="msg warn"><?= icono('alerta') ?> Tienes <b><?= $pendientes ?></b> <?= $pendientes === 1 ? 'pregunta' : 'preguntas' ?> por responder.</div>
    <?php endif; ?>
    <div class="consultas">
    <?php foreach ($lista as $q): $pend = $q['ESTADO'] === 'PENDIENTE'; $errAqui = (int) ($errores['consulta'] ?? 0) === (int) $q['ID_CONSULTA']; ?>
        <article class="card consulta <?= $pend ? 'pendiente' : '' ?>" id="consulta-<?= (int) $q['ID_CONSULTA'] ?>">
            <div class="consulta-cab">
                <?= tag(NotificacionRepository::TIPOS[$q['TIPO']], $q['TIPO'] === 'REPORTE' ? 'bad' : 'warn') ?>
                <?= $pend ? tag('Por responder', 'bad') : tag('Respondida', 'ok') ?>
                <span class="consulta-fecha"><?= fecha($q['FECHA_ENVIO']) ?></span>
            </div>
            <p class="consulta-caja">
                <?= icono('caja') ?> <b><?= e($q['TIENDA']) ?> · Caja N° <?= (int) $q['NUMERO_CAJA'] ?></b>
                · cerrada el <?= fecha($q['FECHA_CIERRE']) ?>
                · sistema <?= sol((float) $q['EFECTIVO_SISTEMA']) ?> · contado <?= sol((float) $q['EFECTIVO_CONTADO']) ?>
                · <b class="tag bad">faltan <?= sol(-(float) $q['DIFERENCIA']) ?></b>
            </p>
            <blockquote class="consulta-msg">
                <?= nl2br(e($q['MENSAJE'])) ?>
                <cite>— <?= e(nombreCorto($q['ADMIN_NOMBRES'], $q['ADMIN_APELLIDOS'])) ?>, Administrador</cite>
            </blockquote>
            <?php if ($pend): ?>
                <form class="form" method="post" action="?accion=responder_consulta" novalidate>
                    <input type="hidden" name="csrf" value="<?= e(AuthController::token()) ?>">
                    <input type="hidden" name="consulta" value="<?= (int) $q['ID_CONSULTA'] ?>">
                    <div class="<?= $errAqui ? 'campo mal' : 'campo' ?>">
                        <label for="resp<?= (int) $q['ID_CONSULTA'] ?>">Tu respuesta: ¿qué pasó con el dinero? *</label>
                        <textarea id="resp<?= (int) $q['ID_CONSULTA'] ?>" name="respuesta" rows="3" maxlength="500"
                                  placeholder="Ejemplo: di S/ 5.00 de más de vuelto a un cliente a última hora."><?= $errAqui ? valor($form, 'respuesta') : '' ?></textarea>
                        <?= $errAqui ? error($errores, 'respuesta') : '' ?>
                    </div>
                    <div class="botones"><button class="btn amarillo" type="submit">Enviar respuesta</button></div>
                </form>
            <?php else: ?>
                <blockquote class="consulta-msg respuesta">
                    <?= nl2br(e($q['RESPUESTA'])) ?>
                    <cite>— Tú, el <?= fecha($q['FECHA_RESPUESTA']) ?></cite>
                </blockquote>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../partials/pie.php'; ?>
