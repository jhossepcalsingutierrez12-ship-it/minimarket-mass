<?php /** @var string $error @var int $espera @var string $token */
$e = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión · Minimarket Mass</title>
    <style>
        body { font-family: Arial, sans-serif; background: #ffd200; margin: 0; min-height: 100vh; display: grid; place-items: center; }
        form { background: #fff; border: 3px solid #111; border-radius: 6px; padding: 24px; width: min(360px, 90vw); box-shadow: 6px 6px 0 #111; }
        h1 { margin: 0 0 16px; font-size: 22px; }
        label { display: block; font-weight: bold; font-size: 13px; margin-top: 12px; }
        input { width: 100%; box-sizing: border-box; padding: 9px; margin-top: 4px; border: 2px solid #111; border-radius: 4px; font-size: 15px; }
        button { width: 100%; margin-top: 18px; padding: 10px; background: #111; color: #ffd200; border: 0; border-radius: 4px; font-weight: bold; font-size: 15px; cursor: pointer; }
        button:disabled { background: #999; color: #fff; cursor: not-allowed; }
        .error { background: #fde2e2; color: #b42828; padding: 9px; border-radius: 4px; margin-bottom: 8px; font-weight: bold; font-size: 14px; }
    </style>
</head>
<body>
    <form method="post" action="?accion=login" autocomplete="off">
        <h1>Minimarket Mass · Iniciar sesión</h1>

        <?php if ($espera > 0): ?>
            <div class="error">Demasiados intentos. Espera <?= $espera ?> segundos y vuelve a intentar.</div>
        <?php elseif ($error !== ''): ?>
            <div class="error"><?= $e($error) ?></div>
        <?php endif; ?>

        <input type="hidden" name="csrf" value="<?= $e($token) ?>">

        <label for="usuario">Usuario</label>
        <input id="usuario" name="usuario" maxlength="30" required autofocus>

        <label for="clave">Contraseña</label>
        <input id="clave" name="clave" type="password" maxlength="72" required>

        <button type="submit" <?= $espera > 0 ? 'disabled' : '' ?>>Ingresar</button>
    </form>
    <?php if ($espera > 0): ?>
    <script>
        // Cuenta regresiva: al llegar a 0 vuelve a cargar el login para poder intentar otra vez
        let s = <?= (int) $espera ?>;
        const aviso = document.querySelector('.error');
        const t = setInterval(() => {
            s--;
            aviso.textContent = 'Demasiados intentos. Espera ' + s + ' segundos y vuelve a intentar.';
            if (s <= 0) { clearInterval(t); location.href = '?accion=login'; }
        }, 1000);
    </script>
    <?php endif; ?>
</body>
</html>