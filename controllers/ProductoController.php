<?php
declare(strict_types=1);

/**
 * Catálogo (S6), agregar / editar / dar de baja (S7) y catálogo en PDF (S8).
 * Solo el Administrador puede cambiar productos.
 */
class ProductoController extends Controlador
{
    private const FOTO_MAX_BYTES = 3_000_000;

    public function __construct(Usuario $usuario, array $tienda, private ProductoRepository $repo, private LoteRepository $lotes)
    {
        parent::__construct($usuario, $tienda);
    }

    public function listar(): void
    {
        $productos = $this->repo->obtenerTodos();
        $inactivos = $this->usuario->puedeAdministrarProductos() ? $this->repo->obtenerInactivos() : [];
        $this->vista('productos/lista', compact('productos', 'inactivos'));
    }

    public function ver(string $codigoSku): void
    {
        $producto = $this->repo->buscarPorSku($codigoSku);
        if ($producto === null) {
            $this->aviso('err', 'Producto no encontrado.');
            $this->redirigir('listar');
        }
        $lotes = $this->lotes->lotesDe($producto->getIdProducto());
        $this->vista('productos/detalle', compact('producto', 'lotes'));
    }

    public function formularioAgregar(array $errores = [], array $form = []): void
    {
        $this->exigir($this->usuario->puedeAdministrarProductos());
        $subcategorias = $this->repo->subcategoriasPorPrefijo();
        $marcas = $this->repo->marcas();
        $this->vista('productos/agregar', compact('errores', 'form', 'subcategorias', 'marcas'));
    }

    public function agregar(): void
    {
        $this->exigir($this->usuario->puedeAdministrarProductos());
        $f = [
            'sku' => strtoupper(self::texto('sku', 8)), 'nombre' => self::texto('nombre', 120),
            'precio' => self::texto('precio', 12), 'perecible' => ($_POST['perecible'] ?? 'N') === 'S' ? 'S' : 'N',
            'stock' => self::texto('stock', 9), 'minimo' => self::texto('minimo', 9), 'contenido' => self::texto('contenido', 12),
            'categoria' => self::texto('categoria', 9), 'marca' => self::texto('marca', 9),
        ];
        $errores = [];
        $prefijo = substr($f['sku'], 0, 3);
        if (preg_match('/^[A-Z]{3}-\d{4}$/', $f['sku']) !== 1) {
            $errores['sku'] = 'Usa el formato ABC-0000.';
        } elseif (!isset(CATEGORIAS[$prefijo])) {
            $errores['sku'] = 'Debe empezar con ABA, LAC, BEB o LIM.';
        } elseif ($this->repo->existeSku($f['sku'])) {
            $errores['sku'] = 'Ese SKU ya existe.';
        }
        if (mb_strlen($f['nombre']) < 3) {
            $errores['nombre'] = 'Escribe el nombre.';
        }
        $precio = self::decimal('precio');
        if ($precio === null || $precio <= 0) {
            $errores['precio'] = 'El precio debe ser mayor que 0 (CK_PRO_1).';
        }
        $contenido = (float) str_replace(',', '.', $f['contenido']);
        if (!is_numeric(str_replace(',', '.', $f['contenido'])) || $contenido <= 0) {
            $errores['contenido'] = 'Mayor que 0, en kg o litros (CK_PRO_2).';
        }
        $stock = self::entero('stock');
        $minimo = self::entero('minimo');
        if ($stock === null) {
            $errores['stock'] = 'Número entero desde 0.';
        }
        if ($minimo === null) {
            $errores['minimo'] = 'Número entero desde 0.';
        }
        $subs = isset(CATEGORIAS[$prefijo]) ? $this->repo->subcategorias($prefijo) : [];
        if (!isset($subs[(int) $f['categoria']])) {
            $errores['categoria'] = 'Elige la subcategoría que va con el SKU.';
        }
        if (!isset($this->repo->marcas()[(int) $f['marca']])) {
            $errores['marca'] = 'Elige la marca.';
        }
        $foto = $this->leerFoto('foto', $errores);

        if ($errores) {
            $this->formularioAgregar($errores, $f);
            return;
        }
        $this->repo->crear([
            'sku' => $f['sku'], 'nombre' => $f['nombre'], 'precio' => number_format($precio, 2, '.', ''),
            'perecible' => $f['perecible'] === 'S', 'stock' => $stock, 'minimo' => $minimo,
            'contenido' => number_format($contenido, 3, '.', ''), 'categoria' => (int) $f['categoria'], 'marca' => (int) $f['marca'],
        ], $this->usuario->getIdTienda());
        if ($foto !== null) {
            $this->guardarFoto($foto, $f['sku']);
        }
        $this->aviso('ok', "Producto {$f['sku']} agregado al catálogo." . ($f['perecible'] === 'S' ? ' Es perecible: registra su lote en «Lotes y vencimientos».' : ''));
        $this->redirigir('listar');
    }

    public function formularioEditar(array $errores = [], ?array $form = null): void
    {
        $this->exigir($this->usuario->puedeAdministrarProductos());
        $producto = $this->repo->buscarPorId(self::idGet());
        if ($producto === null) {
            $this->redirigir('listar');
        }
        $form ??= ['nombre' => $producto->getNombre(), 'precio' => number_format($producto->getPrecioVenta(), 2, '.', ''),
                   'minimo' => (string) $producto->getStockMinimo()];
        $this->vista('productos/editar', compact('producto', 'errores', 'form'));
    }

    public function editar(): void
    {
        $this->exigir($this->usuario->puedeAdministrarProductos());
        $producto = $this->repo->buscarPorId(self::idGet());
        if ($producto === null) {
            $this->redirigir('listar');
        }
        $form = ['nombre' => self::texto('nombre', 120), 'precio' => self::texto('precio', 12), 'minimo' => self::texto('minimo', 9)];
        $errores = [];
        if (mb_strlen($form['nombre']) < 3) {
            $errores['nombre'] = 'Escribe el nombre.';
        }
        $precio = self::decimal('precio');
        if ($precio === null || $precio <= 0) {
            $errores['precio'] = 'El precio debe ser mayor que 0.';
        }
        $minimo = self::entero('minimo');
        if ($minimo === null) {
            $errores['minimo'] = 'Número entero desde 0.';
        }
        $foto = $this->leerFoto('foto', $errores);
        if ($errores) {
            $this->formularioEditar($errores, $form);
            return;
        }
        $this->repo->actualizar($producto->getIdProducto(), $form['nombre'], $precio, $minimo);
        if ($foto !== null) {
            $this->guardarFoto($foto, $producto->getCodigoSku());
        }
        $this->aviso('ok', "Cambios de {$producto->getCodigoSku()} guardados.");
        $this->redirigir('listar');
    }

    public function confirmarBaja(): void
    {
        $this->exigir($this->usuario->puedeAdministrarProductos());
        $producto = $this->repo->buscarPorId(self::idGet());
        if ($producto === null || !$producto->estaActivo()) {
            $this->redirigir('listar');
        }
        $this->vista('productos/baja', compact('producto'));
    }

    // Dar de baja o reactivar: solo cambia ESTADO, el producto no se borra
    public function cambiarEstado(string $estado): void
    {
        $this->exigir($this->usuario->puedeAdministrarProductos());
        $producto = $this->repo->buscarPorId(self::entero('id') ?? 0);
        if ($producto === null) {
            $this->redirigir('listar');
        }
        $this->repo->cambiarEstado($producto->getIdProducto(), $estado);
        if ($estado === 'I') {
            unset($_SESSION['carrito'][$producto->getIdProducto()]);
        }
        $this->aviso('ok', $estado === 'I'
            ? "{$producto->getCodigoSku()} dado de baja. Aparece abajo en «Dados de baja»."
            : "{$producto->getCodigoSku()} vuelve a estar activo.");
        $this->redirigir('listar');
    }

    public function pdf(): void
    {
        $this->exigir($this->usuario->puedeAdministrarProductos());
        $productos = $this->repo->obtenerTodos();
        $this->vista('productos/pdf', compact('productos'));
    }

    /** Revisa la imagen subida: debe ser JPG, PNG o WEBP de hasta 3 MB. Devuelve la ruta temporal. */
    private function leerFoto(string $campo, array &$errores): ?string
    {
        $f = $_FILES[$campo] ?? null;
        if ($f === null || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > self::FOTO_MAX_BYTES) {
            $errores[$campo] = 'La foto debe pesar hasta 3 MB.';
            return null;
        }
        $info = @getimagesize($f['tmp_name']);
        if ($info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            $errores[$campo] = 'Elige una imagen JPG o PNG.';
            return null;
        }
        if ($info[2] !== IMAGETYPE_JPEG && !function_exists('imagecreatefromstring')) {
            $errores[$campo] = 'Sube la foto en JPG (para PNG hay que activar la extensión gd de PHP).';
            return null;
        }
        return $f['tmp_name'];
    }

    // Guarda la foto como public/img/productos/<SKU>.jpg, achicada a 360 × 360 con fondo blanco
    private function guardarFoto(string $temporal, string $sku): void
    {
        $carpeta = __DIR__ . '/../public/img/productos';
        if (!is_dir($carpeta)) {
            mkdir($carpeta, 0775, true);
        }
        $destino = $carpeta . '/' . $sku . '.jpg';
        if (!function_exists('imagecreatefromstring')) {
            move_uploaded_file($temporal, $destino) || copy($temporal, $destino);
            return;
        }
        $img = imagecreatefromstring((string) file_get_contents($temporal));
        if ($img === false) {
            return;
        }
        $lienzo = imagecreatetruecolor(360, 360);
        imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, 255, 255, 255));
        $k = min(330 / imagesx($img), 330 / imagesy($img));
        $w = (int) round(imagesx($img) * $k);
        $h = (int) round(imagesy($img) * $k);
        imagecopyresampled($lienzo, $img, (int) ((360 - $w) / 2), (int) ((360 - $h) / 2), 0, 0, $w, $h, imagesx($img), imagesy($img));
        imagejpeg($lienzo, $destino, 82);
    }
}
