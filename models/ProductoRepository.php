<?php
declare(strict_types=1);
require_once __DIR__ . '/Producto.php';

/**
 * Única clase que sabe de dónde salen los productos.
 * Hoy: lista fija. Sesión 5: SELECT con PDO sobre PRODUCTO JOIN INVENTARIO.
 */
class ProductoRepository
{
    /** @return Producto[] */
    public function obtenerTodos(): array
    {
        // id, CODIGO_SKU, NOMBRE, PRECIO_VENTA, ES_PERECIBLE, STOCK_ACTUAL, STOCK_MINIMO
        return [
            new Producto(1, 'ABA-0001', 'Arroz Costeño extra 5 kg',     24.90, false, 18, 10),
            new Producto(2, 'LAC-0001', 'Leche Gloria azul 400 g',       4.20, true,  75, 24),
            new Producto(3, 'LAC-0002', 'Yogurt Gloria fresa 1 L',        7.90, true,   6, 12),
            new Producto(4, 'BEB-0001', 'Inca Kola 1.5 L',                8.50, false,  0, 12),
            new Producto(5, 'ABA-0002', 'Azúcar rubia Cartavio 1 kg',     4.50, false, 60, 20),
            new Producto(6, 'ABA-0003', 'Aceite Primor 900 ml',          11.90, false, 30, 10),
        ];
    }

    public function buscarPorSku(string $codigoSku): ?Producto
    {
        foreach ($this->obtenerTodos() as $producto) {
            if ($producto->getCodigoSku() === $codigoSku) {
                return $producto;
            }
        }
        return null;
    }
}
