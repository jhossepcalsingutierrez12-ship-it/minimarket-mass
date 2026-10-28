<?php
// Barra con el usuario que inició sesión y el botón para salir
$sesion = AuthController::usuarioActual();
if ($sesion !== null): ?>
<div style="display:flex;gap:12px;align-items:center;justify-content:flex-end;padding:8px;background:#ffd200;font-family:Arial,sans-serif">
    <span><?= htmlspecialchars($sesion['nombre'], ENT_QUOTES, 'UTF-8') ?> · <b><?= htmlspecialchars($sesion['rol'], ENT_QUOTES, 'UTF-8') ?></b></span>
    <form method="post" action="?accion=logout" style="margin:0">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(AuthController::token(), ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit">Cerrar sesión</button>
    </form>
</div>
<?php endif; ?>