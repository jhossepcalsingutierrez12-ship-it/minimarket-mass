<?php
// Nombre y rol de quien inició sesión, y el botón para salir (va dentro del encabezado amarillo)
$sesion = AuthController::usuarioActual();
if ($sesion !== null): ?>
<div class="rol">
    <span class="quien"><?= e($sesion['nombre']) ?></span>
    <span class="cargo"><?= e($sesion['rol']) ?></span>
    <form method="post" action="?accion=logout">
        <input type="hidden" name="csrf" value="<?= e(AuthController::token()) ?>">
        <button type="submit" class="btn sm ghost">Cerrar sesión</button>
    </form>
</div>
<?php endif; ?>
