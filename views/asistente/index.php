<?php
/** @var bool $configurado @var array $historial @var Usuario $u */
$titulo = 'Asistente IA';
$seccion = 'asistente';
require __DIR__ . '/../partials/cabecera.php';

$sugerencias = ['¿Cuánto stock queda de arroz?', '¿Qué productos están por reponer o agotados?', '¿Cuánto se vendió hoy?', '¿Cómo está mi caja?', '¿Cómo registro una merma?'];
if ($u->puedeVerLotes()) {
    $sugerencias[] = '¿Qué lotes vencen este mes?';
}
?>
<div class="cab">
    <div>
        <h1>Asistente IA</h1>
        <p class="sub">Pregunta por stock, ventas, caja, lotes y mermas, o cómo usar el sistema. Responde con los datos de la base según tu rol (<?= e($u->rol()) ?>).</p>
    </div>
    <?php if ($historial): ?>
        <form method="post" action="?accion=asistente_borrar" style="margin-bottom:18px">
            <input type="hidden" name="csrf" value="<?= e(AuthController::token()) ?>">
            <button class="btn sm ghost">Nueva conversación</button>
        </form>
    <?php endif; ?>
</div>

<?php if (!$configurado): ?>
    <div class="card" style="max-width:720px">
        <h3 style="margin-top:0">Falta la clave de Gemini</h3>
        <ol style="margin:0;padding-left:20px;line-height:1.8">
            <li>Entra a <b>aistudio.google.com/apikey</b> con tu cuenta de Google y toca <b>Create API key</b>.</li>
            <li>En la carpeta <code>config</code> copia <code>ia.ejemplo.php</code> y llámalo <code>ia.php</code>.</li>
            <li>Pega la clave en <code>'clave'</code>, guarda y recarga esta página.</li>
        </ol>
    </div>
<?php else: ?>
    <div class="chat card" id="chat" data-csrf="<?= e(AuthController::token()) ?>">
        <div class="chat-mensajes" id="chatMensajes" aria-live="polite">
            <div class="burbuja ia">Hola, <?= e(explode(' ', $u->getNombreCompleto())[0]) ?>. Soy el asistente de Minimarket Mass. ¿En qué te ayudo?</div>
            <?php foreach ($historial as $h): ?>
                <div class="burbuja <?= $h['rol'] === 'user' ? 'yo' : 'ia' ?>" <?= $h['rol'] === 'model' ? 'data-md' : '' ?>><?= e($h['texto']) ?></div>
            <?php endforeach; ?>
        </div>
        <div class="chips chat-sugerencias">
            <?php foreach ($sugerencias as $s): ?><button type="button" class="chip" data-pregunta="<?= e($s) ?>"><?= e($s) ?></button><?php endforeach; ?>
        </div>
        <form class="chat-form" id="chatForm" autocomplete="off">
            <input id="chatPregunta" maxlength="500" placeholder="Escribe tu pregunta…" aria-label="Tu pregunta">
            <button class="btn" type="submit">Enviar</button>
        </form>
    </div>
    <p class="sub" style="font-size:.78rem">Usa Google Gemini. Solo consulta datos (no cambia nada) y cada rol ve únicamente lo que ya puede ver en el sistema.</p>
<?php endif; ?>

<?php require __DIR__ . '/../partials/pie.php'; ?>
