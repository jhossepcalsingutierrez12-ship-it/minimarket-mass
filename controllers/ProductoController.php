<?php
declare(strict_types=1);

class ProductoController
{
    public function __construct(private ProductoRepository $repo) {}

    public function listar(): void
    {
        $productos = $this->repo->obtenerTodos();
        require __DIR__ . '/../views/productos/lista.php';
    }

    public function ver(string $codigoSku): void
    {
        $producto = $this->repo->buscarPorSku($codigoSku);
        if ($producto === null) {
            http_response_code(404);
            echo 'Producto no encontrado';
            return;
        }
        require __DIR__ . '/../views/productos/detalle.php';
    }
}
