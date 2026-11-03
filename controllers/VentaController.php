<?php
declare(strict_types=1);

/**
 * Nueva venta, cobro, comprobante, lista de ventas y anular.
 * El carrito vive en la sesión de PHP ($_SESSION['carrito'] = [idProducto => cantidad]).
 */
class VentaController extends Controlador
{
    public function __construct(
        Usuario $usuario,
        array $tienda,
        private VentaRepository $ventas,
        private CajaRepository $cajas,
        private ProductoRepository $productos
    ) {
        parent::__construct($usuario, $tienda);
    }

    public function nueva(): void
    {
        $sesion = $this->cajas->sesionAbiertaDe($this->usuario->getIdEmpleado());
        if ($sesion === null) {
            $this->vista('ventas/sin_caja');
            return;
        }
        $productos = $this->productos->obtenerTodos();
        $porId = [];
        foreach ($productos as $p) {
            $porId[$p->getIdProducto()] = $p;
        }
        // Si algo del carrito se dio de baja, se quita
        $carrito = array_intersect_key($_SESSION['carrito'] ?? [], $porId);
        $_SESSION['carrito'] = $carrito;
        $form = ($_SESSION['venta_form'] ?? []) + ['tipo' => 'BOLETA', 'doc' => '', 'nombre' => '', 'metodo' => '1'];
        $this->vista('ventas/nueva', [
            'sesion' => $sesion, 'productos' => $productos, 'porId' => $porId, 'carrito' => $carrito,
            'form' => $form, 'metodos' => $this->ventas->metodos(), 'turnos' => $this->cajas->turnos(),
        ]);
    }

    // Un solo formulario: agregar, sumar, restar, quitar o pasar a cobrar
    public function actualizar(): void
    {
        $_SESSION['venta_form'] = [
            'tipo' => ($_POST['tipo'] ?? '') === 'FACTURA' ? 'FACTURA' : 'BOLETA',
            'doc' => preg_replace('/\D/', '', self::texto('doc', 11)),
            'nombre' => self::texto('nombre', 120),
            'metodo' => (string) (self::entero('metodo') ?? 1),
        ];
        $carrito = $_SESSION['carrito'] ?? [];

        if (isset($_POST['agregar'])) {
            $id = (int) $_POST['agregar'];
            $cant = self::entero((string) $id, $_POST['cant'] ?? []) ?? 0;
            $this->sumar($carrito, $id, $cant);
        } elseif (isset($_POST['mas'])) {
            $this->sumar($carrito, (int) $_POST['mas'], 1);
        } elseif (isset($_POST['menos'])) {
            $id = (int) $_POST['menos'];
            if (isset($carrito[$id]) && --$carrito[$id] <= 0) {
                unset($carrito[$id]);
            }
        } elseif (isset($_POST['quitar'])) {
            unset($carrito[(int) $_POST['quitar']]);
        } elseif (isset($_POST['cobrar'])) {
            $_SESSION['carrito'] = $carrito;
            $this->pasarACobro();
            return;
        }
        $_SESSION['carrito'] = $carrito;
        $this->redirigir('venta');
    }

    // R5: no se puede pedir más de lo que hay en inventario
    private function sumar(array &$carrito, int $id, int $cant): void
    {
        $p = $this->productos->buscarPorId($id);
        if ($p === null || !$p->estaActivo()) {
            return;
        }
        if ($cant < 1) {
            $this->aviso('err', 'La cantidad debe ser 1 o más.');
            return;
        }
        $total = ($carrito[$id] ?? 0) + $cant;
        if (!$p->hayStock($total)) {
            $this->aviso('err', $p->getStockActual() === 0
                ? "R5: {$p->getNombre()} está agotado."
                : "R5: solo hay {$p->getStockActual()} unidades de {$p->getNombre()} (pediste $total).");
            return;
        }
        $carrito[$id] = $total;
    }

    /**
     * Revisa el cliente: 8 dígitos = DNI · 11 dígitos (10/20...) = RUC · 9 = carné de extranjería.
     * @return array{tipo: ?string, doc: string, cliente: ?array, error: ?string}
     */
    public function leerDocumento(string $doc): array
    {
        $tipo = match (true) {
            $doc === '' => null,
            preg_match('/^\d{8}$/', $doc) === 1 => 'DNI',
            preg_match('/^(10|20)\d{9}$/', $doc) === 1 => 'RUC',
            preg_match('/^\d{9}$/', $doc) === 1 => 'CE',
            default => false,
        };
        if ($tipo === false) {
            return ['tipo' => null, 'doc' => $doc, 'cliente' => null,
                    'error' => strlen($doc) === 11 ? 'El RUC empieza con 10 o 20.' : 'El DNI tiene 8 dígitos, el RUC 11 y el carné de extranjería 9.'];
        }
        return ['tipo' => $tipo, 'doc' => $doc, 'cliente' => $tipo ? $this->ventas->clientePorDocumento($doc) : null, 'error' => null];
    }

    // Para el aviso en vivo al escribir el DNI/RUC (lo usa public/js/app.js)
    public function buscarCliente(): void
    {
        $r = $this->leerDocumento(preg_replace('/\D/', '', (string) ($_GET['doc'] ?? '')));
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['tipo' => $r['tipo'], 'error' => $r['error'],
                          'nombre' => $r['cliente']['NOMBRE_RAZON_SOCIAL'] ?? null], JSON_UNESCAPED_UNICODE);
    }

    private function pasarACobro(): void
    {
        $f = $_SESSION['venta_form'];
        $carrito = $_SESSION['carrito'] ?? [];
        if (!$carrito) {
            $this->redirigir('venta');
        }
        $r = $this->leerDocumento($f['doc']);
        $error = $r['error'];
        if (!$error && $f['tipo'] === 'FACTURA' && $r['tipo'] !== 'RUC') {
            $error = 'La factura necesita el RUC del cliente (regla CK_VEN_3).';
        }
        if (!$error && $r['tipo'] && !$r['cliente'] && mb_strlen($f['nombre']) < 3) {
            $error = 'Escribe ' . ($r['tipo'] === 'RUC' ? 'la razón social' : 'el nombre') . ' del cliente nuevo.';
        }
        if (!$error && !isset($this->ventas->metodos()[(int) $f['metodo']])) {
            $error = 'Elige el método de pago.';
        }
        if ($error) {
            $this->aviso('err', $error);
            $this->redirigir('venta');
        }
        $_SESSION['cobro'] = [
            'cliente' => $r['tipo'] ? ['tipo' => $r['tipo'], 'doc' => $r['doc'],
                                       'nombre' => $r['cliente']['NOMBRE_RAZON_SOCIAL'] ?? $f['nombre']] : null,
            'ref' => 'MASS-' . substr((string) time(), -6),
        ];
        $this->redirigir('cobro');
    }

    // Pantalla de cobro: QR de Yape/Plin, efectivo con vuelto o POS de tarjeta
    public function cobro(string $error = ''): void
    {
        $sesion = $this->cajas->sesionAbiertaDe($this->usuario->getIdEmpleado());
        $carrito = $_SESSION['carrito'] ?? [];
        if ($sesion === null || !$carrito || !isset($_SESSION['cobro'])) {
            $this->redirigir('venta');
        }
        $metodo = (int) $_SESSION['venta_form']['metodo'];
        $this->vista('ventas/cobro', [
            'total' => $this->totalCarrito($carrito), 'metodo' => $metodo, 'nombreMetodo' => $this->ventas->metodos()[$metodo] ?? '',
            'ref' => $_SESSION['cobro']['ref'], 'error' => $error,
        ]);
    }

    public function cancelarCobro(): void
    {
        unset($_SESSION['cobro']);
        $this->aviso('info', 'Cobro cancelado. La venta no se registró y el carrito sigue igual.');
        $this->redirigir('venta');
    }

    public function confirmarPago(): void
    {
        $sesion = $this->cajas->sesionAbiertaDe($this->usuario->getIdEmpleado());
        $carrito = $_SESSION['carrito'] ?? [];
        $cobro = $_SESSION['cobro'] ?? null;
        if ($sesion === null || !$carrito || $cobro === null) {
            $this->redirigir('venta');
        }
        $metodo = (int) $_SESSION['venta_form']['metodo'];
        $nombre = $this->ventas->metodos()[$metodo] ?? '';
        $operacion = null;
        $recibido = null;

        if ($nombre === 'Efectivo') {
            $recibido = self::decimal('recibido');
            $total = $this->totalCarrito($carrito);
            if ($recibido === null || $recibido < $total) {
                $this->cobro('El efectivo recibido debe ser al menos ' . sol($total) . '.');
                return;
            }
        } elseif ($nombre === 'Yape' || $nombre === 'Plin') {
            $operacion = preg_replace('/\D/', '', self::texto('operacion', 12));
            if (strlen($operacion) < 6) {
                $this->cobro('Escribe el N° de operación que sale en la app (6 a 12 dígitos).');
                return;
            }
        } else {
            $operacion = (string) random_int(100000, 999999);   // POS simulado
        }

        try {
            $id = $this->ventas->registrar([
                'sesion' => $sesion, 'tipo' => $_SESSION['venta_form']['tipo'], 'cliente' => $cobro['cliente'],
                'lineas' => $carrito, 'metodo' => $metodo, 'operacion' => $operacion, 'recibido' => $recibido,
            ]);
        } catch (DomainException $e) {
            $this->cobro($e->getMessage());
            return;
        } catch (PDOException $e) {
            // Un trigger o CHECK de la base rechazó la venta (R5, R7, CK_PAG_2...)
            error_log('Venta rechazada: ' . $e->getMessage());
            $this->cobro('La base de datos rechazó la venta: ' . self::mensajeBase($e));
            return;
        }
        $_SESSION['carrito'] = [];
        unset($_SESSION['cobro'], $_SESSION['venta_form']);
        $_SESSION['recien_pagada'] = $id;
        $this->redirigir('comprobante', ['id' => $id]);
    }

    public function comprobante(): void
    {
        $venta = $this->ventas->buscar(self::idGet(), $this->usuario);
        if ($venta === null) {
            $this->redirigir('ventas');
        }
        $recien = ($_SESSION['recien_pagada'] ?? 0) === (int) $venta['ID_VENTA'];
        unset($_SESSION['recien_pagada']);
        $this->vista('ventas/comprobante', compact('venta', 'recien'));
    }

    public function lista(): void
    {
        $this->vista('ventas/lista', ['ventas' => $this->ventas->listar($this->usuario), 'confirmar' => self::idGet()]);
    }

    public function anular(): void
    {
        $this->exigir($this->usuario->puedeAnularVenta());
        try {
            $v = $this->ventas->anular(self::entero('id') ?? 0, $this->usuario);
            $this->aviso('ok', "{$v['SERIE']}-{$v['NUMERO']} anulada por {$this->usuario->getNombreCompleto()}. El stock volvió al inventario.");
        } catch (DomainException $e) {
            $this->aviso('err', $e->getMessage());
        }
        $this->redirigir('ventas');
    }

    // Total con los precios actuales de la base (el mismo cálculo que hace registrar())
    private function totalCarrito(array $carrito): float
    {
        $total = 0.0;
        foreach ($carrito as $id => $cant) {
            $p = $this->productos->buscarPorId((int) $id);
            $total += $p ? round($p->getPrecioVenta() * $cant, 2) : 0;
        }
        return round($total, 2);
    }

    // Los triggers mandan su texto en el mensaje de error de MySQL (ej. "R7: la sesion de caja esta cerrada")
    private static function mensajeBase(PDOException $e): string
    {
        return preg_match('/(R\d+:[^\n]+)/', $e->getMessage(), $m) === 1 ? $m[1] : 'revisa el stock y la caja.';
    }
}
