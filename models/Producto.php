<?php
declare(strict_types=1);

/**
 * Una fila de PRODUCTO junto con su stock de INVENTARIO (relación 1 a 1 por ID_PRODUCTO).
 * Columnas de schema.sql:
 *   PRODUCTO.ID_PRODUCTO, CODIGO_SKU, NOMBRE, PRECIO_VENTA, ES_PERECIBLE
 *   INVENTARIO.STOCK_ACTUAL, STOCK_MINIMO
 */
class Producto
{
    public function __construct(
        private int $idProducto,
        private string $codigoSku,
        private string $nombre,
        private float $precioVenta,
        private bool $esPerecible,
        private int $stockActual,
        private int $stockMinimo
    ) {}

    public function getIdProducto(): int    { return $this->idProducto; }
    public function getCodigoSku(): string  { return $this->codigoSku; }
    public function getNombre(): string     { return $this->nombre; }
    public function getPrecioVenta(): float { return $this->precioVenta; }
    public function esPerecible(): bool     { return $this->esPerecible; }
    public function getStockActual(): int   { return $this->stockActual; }
    public function getStockMinimo(): int   { return $this->stockMinimo; }

    // Regla: PRECIO_VENTA ya incluye IGV 18% (igual que VENTA: TOTAL = SUBTOTAL + IGV)
    public function igv(): float      { return round($this->precioVenta * 18 / 118, 2); }
    public function subtotal(): float { return round($this->precioVenta - $this->igv(), 2); }

    // Regla R5: solo se vende lo que hay en inventario
    public function hayStock(int $cantidad): bool
    {
        return $cantidad > 0 && $cantidad <= $this->stockActual;
    }

    // Regla de reposición: STOCK_ACTUAL en o por debajo de STOCK_MINIMO
    public function estadoStock(): string
    {
        if ($this->stockActual === 0) {
            return 'Agotado';
        }
        if ($this->stockActual <= $this->stockMinimo) {
            return 'Reponer';
        }
        return 'Normal';
    }
}
