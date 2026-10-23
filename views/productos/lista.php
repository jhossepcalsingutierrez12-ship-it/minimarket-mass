<?php /** @var Producto[] $productos */
$e = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Catálogo · Minimarket Mass Arequipa</title>
</head>
<body>
    <h1>Catálogo · Minimarket Mass Arequipa</h1>
    <table border="1" cellpadding="6">
        <tr><th>SKU</th><th>Producto</th><th>Precio</th><th>IGV</th><th>Stock</th><th>Estado</th></tr>
        <?php foreach ($productos as $p): ?>
            <tr>
                <td><a href="?accion=ver&amp;sku=<?= urlencode($p->getCodigoSku()) ?>"><?= $e($p->getCodigoSku()) ?></a></td>
                <td><?= $e($p->getNombre()) ?></td>
                <td>S/ <?= number_format($p->getPrecioVenta(), 2) ?></td>
                <td>S/ <?= number_format($p->igv(), 2) ?></td>
                <td><?= $p->getStockActual() ?></td>
                <td><?= $e($p->estadoStock()) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
