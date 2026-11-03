<?php
/**
 * Datos de la cuenta que envía el código de ingreso por correo.
 *
 * 1. Copia este archivo y llámalo correo.php (en esta misma carpeta config/).
 * 2. Pon tu Gmail y una "contraseña de aplicación" (NO tu contraseña normal):
 *    Cuenta de Google -> Seguridad -> Verificación en 2 pasos (activarla) ->
 *    Contraseñas de aplicaciones -> crear una llamada "Minimarket Mass" -> copia las 16 letras.
 * 3. config/correo.php está en .gitignore: tu contraseña NO se sube a GitHub.
 *
 * Si correo.php no existe, el sistema muestra el código en pantalla (modo simulado).
 */
return [
    'host'      => 'smtp.gmail.com',
    'puerto'    => 465,
    'seguridad' => 'ssl',                       // ssl con 465 · tls con 587
    'usuario'   => 'tu_correo@gmail.com',
    'clave'     => 'xxxx xxxx xxxx xxxx',       // contraseña de aplicación de 16 letras
    'nombre'    => 'Minimarket Mass',
];
