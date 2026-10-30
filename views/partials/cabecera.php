<?php
/**
 * Parte de arriba de cada página con sesión: <head>, encabezado amarillo y menú lateral.
 * Antes de incluirla, la vista define $titulo (texto de la pestaña) y $seccion (opción activa).
 * @var string $titulo @var string $seccion
 */
require_once __DIR__ . '/ayudas.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo) ?> · Minimarket Mass</title>
    <link href="https://fonts.googleapis.com/css2?family=Archivo+Black&family=Archivo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/estilos.css">
    <script src="js/app.js" defer></script>
</head>
<body>
<div class="topbar"><span>Abierto todos los días de 7:00 a 22:00</span><span>3 tiendas en Arequipa</span></div>
<header>
    <a class="logo" href="?accion=listar"><span class="badge">MASS</span><span class="txt">Minimarket<br>Arequipa</span></a>
    <?php require __DIR__ . '/barra_sesion.php'; ?>
</header>
<div class="panel">
    <nav class="lateral" aria-label="Menú">
        <span class="tit">Menú</span>
        <a href="?accion=listar" class="<?= $seccion === 'catalogo' ? 'activo' : '' ?>">
            <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
            Catálogo
        </a>
        <!-- S7: aquí se agrega «Agregar producto». S8: «Catálogo en PDF». -->
    </nav>
    <main class="contenido">
