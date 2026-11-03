<?php
/**
 * Paso 2: código de 6 dígitos que llega al correo del empleado.
 * @var string $error @var string $token @var int $esperaReenvio @var bool $porCorreo
 * @var string $destino @var string $avisoEnvio @var ?string $codigoSimulado
 */
$titulo = 'Verificación';
require __DIR__ . '/_cabeza.php';
?>
    <div class="login-box">
        <div class="login-head">
            <span class="badge">MASS</span><br>
            <span class="paso">Paso 2 de 2</span>
            <h1>Verificación</h1>
            <?php if ($porCorreo): ?>
                <p class="sub" style="margin:6px 0 0">Te enviamos un código de 6 dígitos a tu correo <b><?= e($destino) ?></b>. Revisa también «Spam».</p>
            <?php else: ?>
                <p class="sub" style="margin:6px 0 0">Escribe el código de 6 dígitos que aparece en el aviso de abajo.</p>
            <?php endif; ?>
        </div>
        <div class="login-body">
            <?php if ($error !== ''): ?><div class="msg err" id="errorPaso2"><?= e($error) ?></div><?php endif; ?>
            <?php if ($avisoEnvio !== ''): ?><div class="msg info"><?= e($avisoEnvio) ?></div><?php endif; ?>

            <form method="post" action="?accion=verificar" autocomplete="off" novalidate>
                <input type="hidden" name="csrf" value="<?= e($token) ?>">
                <div class="campo">
                    <label for="codigo">Código de verificación</label>
                    <input id="codigo" name="codigo" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]*">
                </div>
                <button class="btn amarillo" type="submit" style="width:100%">Verificar e ingresar</button>
            </form>

            <div class="fila-btn">
                <form method="post" action="?accion=reenviar" style="flex:1">
                    <input type="hidden" name="csrf" value="<?= e($token) ?>">
                    <button class="btn sm ghost" style="width:100%" id="reenviar" data-espera="<?= $esperaReenvio ?>" <?= $esperaReenvio > 0 ? 'disabled' : '' ?>>
                        <?= $esperaReenvio > 0 ? "Reenviar en $esperaReenvio s" : 'Reenviar código' ?>
                    </button>
                </form>
                <form method="post" action="?accion=cancelar_ingreso" style="flex:1">
                    <input type="hidden" name="csrf" value="<?= e($token) ?>">
                    <button class="btn sm ghost" style="width:100%">Volver</button>
                </form>
            </div>
            <p class="sub" style="font-size:.8rem;margin:12px 0 0">El código vence en 5 minutos. Tienes 3 intentos.</p>
        </div>
    </div>

    <?php if ($codigoSimulado !== null): ?>
        <!-- Modo simulado: no hay config/correo.php, así que el código se muestra aquí -->
        <div class="sms" role="status">
            <b>Código de ingreso · modo simulado</b>
            Tu código es <span class="sms-cod"><?= e($codigoSimulado) ?></span>. Vence en 5 minutos.
            <small>Para que llegue al correo, crea config/correo.php (mira config/correo.ejemplo.php).</small>
        </div>
    <?php endif; ?>
</body>
</html>
