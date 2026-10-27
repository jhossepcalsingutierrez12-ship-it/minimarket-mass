<?php
declare(strict_types=1);

require __DIR__ . '/../models/Producto.php';
require __DIR__ . '/../models/ProductoRepository.php';
require __DIR__ . '/../controllers/ProductoController.php';

$accion = $_GET['accion'] ?? 'listar';
$controlador = new ProductoController(new ProductoRepository());

switch ($accion) {
    case 'listar':
        $controlador->listar();
        break;
    case 'ver':
        // Solo se acepta un SKU con el formato de CODIGO_SKU (letras, números y guion)
        $sku = (string) ($_GET['sku'] ?? '');
        if (preg_match('/^[A-Z0-9-]{1,20}$/', $sku) !== 1) {
            http_response_code(404);
            echo 'Página no encontrada';
            break;
        }
        $controlador->ver($sku);
        break;
    default:
        http_response_code(404);
        echo 'Página no encontrada';
}
