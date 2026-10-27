<?php /** @var Producto $producto */
$e = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= $e($producto->getNombre()) ?> · Minimarket Mass</title>
</head>
<body>
    <h1><?= $e($producto->getNombre()) ?></h1>
    <p>SKU: <?= $e($producto->getCodigoSku()) ?></p>
    <p>Precio (IGV incluido): S/ <?= number_format($producto->getPrecioVenta(), 2) ?></p>
    <p>Subtotal: S/ <?= number_format($producto->subtotal(), 2) ?> · IGV: S/ <?= number_format($producto->igv(), 2) ?></p>
    <p>Perecible: <?= $producto->esPerecible() ? 'Sí' : 'No' ?></p>
    <p>Stock: <?= $producto->getStockActual() ?> (mínimo <?= $producto->getStockMinimo() ?>) · <?= $e($producto->estadoStock()) ?></p>
    <p><a href="?accion=listar">Volver al catálogo</a></p>
</body>
</html>
