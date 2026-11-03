<?php
$titulo = 'Nueva venta';
$seccion = 'venta';
require __DIR__ . '/../partials/cabecera.php';
?>
<h1>Nueva venta</h1>
<?= aviso() ?>
<div class="vacio-box" style="margin-top:16px"><b>R7: necesitas una caja abierta para vender.</b><br><br><a class="btn" href="?accion=caja">Abrir caja</a></div>
<?php require __DIR__ . '/../partials/pie.php'; ?>
