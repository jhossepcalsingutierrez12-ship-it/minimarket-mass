<?php
/** @var string $error @var int $espera @var string $token */
require_once __DIR__ . '/../partials/ayudas.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión · Minimarket Mass</title>
    <link href="https://fonts.googleapis.com/css2?family=Archivo+Black&family=Archivo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/estilos.css">
    <script src="js/app.js" defer></script>
</head>
<body class="pagina-login">
    <form class="login-box" method="post" action="?accion=login" autocomplete="off">
        <div class="login-head">
            <span class="badge">MASS</span>
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
                <input id="usuario" name="usuario" maxlength="30" placeholder="ej. rquispe" required autofocus>
            </div>
            <div class="campo">
                <label for="clave">Contraseña</label>
                <div class="clave">
                    <input id="clave" name="clave" type="password" maxlength="72" required>
                    <button type="button" class="btn sm ghost" id="verClave" hidden>Ver</button>
                </div>
            </div>

            <button class="btn amarillo" type="submit" style="width:100%" <?= $espera > 0 ? 'disabled' : '' ?>>Ingresar</button>
            <p class="sub" style="font-size:.8rem;margin:12px 0 0">Después de 3 intentos fallidos el ingreso se bloquea 30 segundos.</p>
        </div>
    </form>
</body>
</html>
