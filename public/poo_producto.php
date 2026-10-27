<?php
declare(strict_types=1);
require __DIR__ . '/../models/Producto.php';

$p = new Producto(1, 'ABA-0001', 'Arroz Costeño extra 5 kg', 24.90, false, 18, 10);
$e = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>POO · Producto</title></head>
<body>
    <h1><?= $e($p->getNombre()) ?></h1>
    <p>SKU: <?= $e($p->getCodigoSku()) ?></p>
    <p>Precio (IGV incluido): S/ <?= number_format($p->getPrecioVenta(), 2) ?></p>
    <p>Subtotal: S/ <?= number_format($p->subtotal(), 2) ?></p>
    <p>IGV 18%: S/ <?= number_format($p->igv(), 2) ?></p>
    <p>Stock: <?= $p->getStockActual() ?> (mínimo <?= $p->getStockMinimo() ?>) · <?= $e($p->estadoStock()) ?></p>
    <p>¿Hay 5 unidades?: <?= $p->hayStock(5) ? 'Sí' : 'No' ?></p>
    <p>¿Hay 50 unidades?: <?= $p->hayStock(50) ? 'Sí' : 'No' ?></p>
</body>
</html>
