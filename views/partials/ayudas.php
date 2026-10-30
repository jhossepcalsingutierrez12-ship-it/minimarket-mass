<?php
declare(strict_types=1);

// Funciones pequeñas que usan las vistas. Solo dan formato: no consultan la base.

// Escapa el texto antes de imprimirlo (evita XSS)
function e(string $texto): string
{
    return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
}

// 24.9 -> "S/ 24.90"
function sol(float $monto): string
{
    return 'S/ ' . number_format($monto, 2);
}

// La categoría sale de las 3 primeras letras del SKU: ABA-0001 -> Abarrotes
const CATEGORIAS = [
    'ABA' => ['nombre' => 'Abarrotes', 'icono' => '🍚'],
    'LAC' => ['nombre' => 'Lácteos',   'icono' => '🥛'],
    'BEB' => ['nombre' => 'Bebidas',   'icono' => '🥤'],
    'LIM' => ['nombre' => 'Limpieza',  'icono' => '🧴'],
];

function categoriaDe(string $sku): string
{
    return substr($sku, 0, 3);
}

// Foto en public/img/productos/<SKU>.jpg; si no existe, muestra el ícono de la categoría
function fotoProducto(Producto $p): string
{
    $sku = $p->getCodigoSku();
    if (is_file(__DIR__ . '/../../public/img/productos/' . $sku . '.jpg')) {
        return '<img src="img/productos/' . rawurlencode($sku) . '.jpg" alt="' . e($p->getNombre()) . '">';
    }
    return '<span aria-hidden="true">' . (CATEGORIAS[categoriaDe($sku)]['icono'] ?? '🛒') . '</span>';
}
