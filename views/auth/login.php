<?php
/** @var string $error @var int $espera @var string $token */
$titulo = 'Iniciar sesión';
require __DIR__ . '/_cabeza.php';
?>
    <form class="login-box" method="post" action="?accion=login" autocomplete="off">
        <div class="login-head">
            <span class="badge">MASS</span><br>
            <span class="paso">Paso 1 de 2</span>
            <h1>Iniciar sesión</h1>
            <p class="sub" style="margin:6px 0 0">Sistema de tiendas · Minimarket Mass Arequipa</p>
        </div>
        <div class="login-body">
            <?php if ($espera > 0): ?>
                <div class="msg err" data-espera="<?= (int) $espera ?>">Demasiados intentos. Espera <?= (int) $espera ?> segundos y vuelve a intentar.</div>
            <?php elseif ($error !== ''): ?>
                <div class="msg err"><?= e($error) ?></div>
            <?php endif; ?>

            <input type="hidden" name="csrf" value="<?= e($token) ?>">

            <div class="campo">
                <label for="usuario">Usuario</label>
                <input id="usuario" name="usuario" maxlength="30" value="<?= e((string) ($_POST['usuario'] ?? '')) ?>" required autofocus>
            </div>
            <div class="campo">
                <label for="clave">Contraseña</label>
                <div class="clave">
                    <input id="clave" name="clave" type="password" maxlength="72" required>
                    <button type="button" class="btn sm ghost" id="verClave" hidden>Ver</button>
                </div>
            </div>

            <!-- Verifica que eres humano: la imagen girada la arma PHP (?accion=captcha) -->
            <div class="captcha">
                <span class="captcha-tit">Verifica que eres humano</span>
                <div class="captcha-img"><img id="captchaImg" src="?accion=captcha&amp;v=<?= bin2hex(random_bytes(4)) ?>" alt="Logo de Mass girado" width="120" height="120"></div>
                <label for="giro">Gira el logo hasta que quede derecho</label>
                <input id="giro" name="giro" type="range" min="0" max="359" value="0" step="1">
                <a class="captcha-otro" href="?accion=login">Ver otra imagen</a>
            </div>

            <button class="btn amarillo" type="submit" style="width:100%" <?= $espera > 0 ? 'disabled' : '' ?>>Ingresar</button>
            <p class="sub" style="font-size:.8rem;margin:12px 0 0">Después de 3 intentos fallidos el ingreso se bloquea 30 segundos. Luego se pide un código que llega a tu correo.</p>
        </div>
    </form>
</body>
</html>
