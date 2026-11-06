<?php
declare(strict_types=1);

/**
 * Funciones de control del Administrador:
 * alertas de inventario, revisión de cierres de caja (sin cerrar), proveedores y compras,
 * e historial de actividades. Los reportes con filtros están en TiendaController.
 */
class AdminController extends Controlador
{
    public function __construct(
        Usuario $usuario,
        array $tienda,
        private ReporteRepository $reportes,
        private CajaRepository $cajas,
        private ProveedorRepository $proveedores,
        private HistorialRepository $historial,
        private ProductoRepository $productos,
        private TiendaRepository $tiendas
    ) {
        parent::__construct($usuario, $tienda);
    }

    // 1. Alertas: agotados, poco stock con reposición recomendada y lotes por vencer
    public function alertas(): void
    {
        $this->exigir($this->usuario->puedeVerAlertas());
        $this->vista('inventario/alertas', ['a' => $this->reportes->alertasInventario()]);
    }

    // 2. Cierres de caja: el administrador los revisa, no cierra cajas
    public function cierres(): void
    {
        $this->exigir($this->usuario->puedeRevisarCierres());
        $f = [
            'tienda' => self::entero('tienda', $_GET),
            'desde' => self::fechaGet('desde'),
            'hasta' => self::fechaGet('hasta'),
            'diferencia' => ($_GET['diferencia'] ?? '') === '1',
        ];
        $this->vista('caja/cierres', ['lista' => $this->cajas->cierres($f), 'f' => $f, 'tiendas' => $this->tiendas->tiendas()]);
    }

    // 4. Proveedores
    public function proveedores(array $errores = [], array $form = []): void
    {
        $this->exigir($this->usuario->puedeGestionarProveedores());
        $lista = $this->conTablas(fn() => $this->proveedores->listar());
        $this->vista('proveedores/index', ['lista' => $lista ?? [], 'sinTablas' => $lista === null, 'errores' => $errores, 'form' => $form]);
    }

    public function crearProveedor(): void
    {
        $this->exigir($this->usuario->puedeGestionarProveedores());
        $form = ['ruc' => self::texto('ruc', 11), 'razon' => self::texto('razon', 120), 'rubro' => self::texto('rubro', 150), 'contacto' => self::texto('contacto', 80),
                 'telefono' => self::texto('telefono', 15), 'correo' => self::texto('correo', 120)];
        $errores = [];
        if (preg_match('/^(10|20)\d{9}$/', $form['ruc']) !== 1) {
            $errores['ruc'] = 'El RUC tiene 11 dígitos y empieza con 10 o 20 (CK_PRV_1).';
        } elseif ($this->proveedores->existeRuc($form['ruc'])) {
            $errores['ruc'] = 'Ya hay un proveedor con ese RUC.';
        }
        if (mb_strlen($form['razon']) < 3) {
            $errores['razon'] = 'Escribe la razón social.';
        }
        if ($form['telefono'] !== '' && preg_match('/^[0-9 -]{6,15}$/', $form['telefono']) !== 1) {
            $errores['telefono'] = 'Solo números (6 a 15).';
        }
        if ($form['correo'] !== '' && !filter_var($form['correo'], FILTER_VALIDATE_EMAIL)) {
            $errores['correo'] = 'Correo no válido.';
        }
        if ($errores) {
            $this->proveedores($errores, $form);
            return;
        }
        $this->proveedores->crear($form['ruc'], $form['razon'], $form['rubro'] ?: null, $form['contacto'] ?: null, $form['telefono'] ?: null, $form['correo'] ?: null);
        Historial::anotar($this->usuario, 'REGISTRAR_PROVEEDOR', "Proveedor {$form['razon']} (RUC {$form['ruc']})");
        $this->aviso('ok', "Proveedor {$form['razon']} registrado.");
        $this->redirigir('proveedores');
    }

    public function estadoProveedor(): void
    {
        $this->exigir($this->usuario->puedeGestionarProveedores());
        $id = self::entero('id') ?? 0;
        $estado = ($_POST['estado'] ?? '') === 'I' ? 'I' : 'A';
        if ($this->proveedores->buscar($id)) {
            $this->proveedores->cambiarEstado($id, $estado);
            $this->aviso('ok', $estado === 'I' ? 'Proveedor desactivado: ya no sale al registrar compras.' : 'Proveedor activo otra vez.');
        }
        $this->redirigir('proveedores');
    }

    // 4. Compras de mercadería: al recibirlas se crean los lotes y sube el stock
    public function compras(array $errores = [], array $form = []): void
    {
        $this->exigir($this->usuario->puedeGestionarProveedores());
        $compras = $this->conTablas(fn() => $this->proveedores->compras());
        $this->vista('proveedores/compras', [
            'compras' => $compras ?? [], 'sinTablas' => $compras === null,
            'proveedores' => $compras === null ? [] : $this->proveedores->activos(),
            'productos' => $this->productos->obtenerTodos(), 'tiendas' => $this->tiendas->tiendas(),
            'errores' => $errores, 'form' => $form + ['proveedor' => '', 'tienda' => '', 'documento' => '', 'lineas' => []],
        ]);
    }

    public function registrarCompra(): void
    {
        $this->exigir($this->usuario->puedeGestionarProveedores());
        $errores = [];
        $form = ['proveedor' => self::texto('proveedor', 9), 'tienda' => self::texto('tienda', 9), 'documento' => self::texto('documento', 20), 'lineas' => []];
        $proveedor = $this->proveedores->buscar((int) $form['proveedor']);
        if ($proveedor === null || $proveedor['ESTADO'] !== 'A') {
            $errores['proveedor'] = 'Elige el proveedor.';
        }
        if ($this->tiendas->tienda((int) $form['tienda']) === null) {
            $errores['tienda'] = 'Elige la tienda que recibe la mercadería.';
        }
        $productos = [];
        foreach ($this->productos->obtenerTodos() as $p) {
            $productos[$p->getIdProducto()] = $p;
        }
        $lineas = [];
        $usados = [];
        foreach ((array) ($_POST['producto'] ?? []) as $i => $idTxt) {
            $i = (int) $i;
            $fila = [
                'producto' => mb_substr(trim((string) $idTxt), 0, 9),
                'cantidad' => mb_substr(trim((string) ($_POST['cantidad'][$i] ?? '')), 0, 9),
                'costo' => mb_substr(str_replace(',', '.', trim((string) ($_POST['costo'][$i] ?? ''))), 0, 12),
                'vence' => mb_substr(trim((string) ($_POST['vence'][$i] ?? '')), 0, 10),
            ];
            $form['lineas'][$i] = $fila;
            if ($fila['producto'] === '' && $fila['cantidad'] === '' && $fila['costo'] === '') {
                continue;   // fila vacía
            }
            $p = $productos[(int) $fila['producto']] ?? null;
            if ($p === null) {
                $errores["linea$i"] = 'Elige el producto.';
                continue;
            }
            if (isset($usados[$p->getIdProducto()])) {
                $errores["linea$i"] = 'Ese producto ya está en otra fila: súmalo en una sola.';
                continue;
            }
            if (preg_match('/^\d{1,6}$/', $fila['cantidad']) !== 1 || (int) $fila['cantidad'] <= 0) {
                $errores["linea$i"] = 'Cantidad: entero mayor que 0.';
                continue;
            }
            if (preg_match('/^\d{1,7}(\.\d{1,2})?$/', $fila['costo']) !== 1) {
                $errores["linea$i"] = 'Costo unitario: monto desde 0 con hasta 2 decimales.';
                continue;
            }
            $vence = null;
            if ($fila['vence'] !== '') {
                $d = DateTime::createFromFormat('!Y-m-d', $fila['vence']);
                if (!$d || $d->format('Y-m-d') !== $fila['vence']) {
                    $errores["linea$i"] = 'Fecha de vencimiento no válida.';
                    continue;
                }
                $vence = $fila['vence'];
            } elseif ($p->esPerecible()) {
                $errores["linea$i"] = 'Es perecible: pon la fecha de vencimiento.';
                continue;
            }
            $usados[$p->getIdProducto()] = true;
            $lineas[] = ['producto' => $p->getIdProducto(), 'cantidad' => (int) $fila['cantidad'], 'costo' => (float) $fila['costo'], 'vence' => $vence];
        }
        if (!$lineas && !array_filter(array_keys($errores), fn($k) => str_starts_with($k, 'linea'))) {
            $errores['lineas'] = 'Agrega al menos un producto.';
        }
        if ($errores) {
            $this->compras($errores, $form);
            return;
        }
        $r = $this->proveedores->registrarCompra((int) $form['proveedor'], (int) $form['tienda'], $this->usuario, $form['documento'] ?: null, $lineas);
        $doc = $form['documento'] ?: 'sin documento';
        Historial::anotar($this->usuario, 'REGISTRAR_COMPRA', "Compra $doc a {$proveedor['RAZON_SOCIAL']} · " . count($lineas) . ' producto' . (count($lineas) === 1 ? '' : 's') . ' · ' . sol($r['total']));
        $this->aviso('ok', "Compra N° {$r['id']} registrada ({$doc}) por " . sol($r['total']) . '. Se crearon ' . count($lineas) . ' lote(s) y el stock ya subió.');
        $this->redirigir('compras');
    }

    // 5. Historial de actividades
    public function historial(): void
    {
        $this->exigir($this->usuario->puedeVerHistorial());
        $accion = (string) ($_GET['tipo'] ?? '');
        $f = [
            'accion' => isset(HistorialRepository::ACCIONES[$accion]) ? $accion : '',
            'empleado' => self::entero('empleado', $_GET),
            'desde' => self::fechaGet('desde'),
            'hasta' => self::fechaGet('hasta'),
        ];
        $lista = $this->conTablas(fn() => $this->historial->listar($f));
        $this->vista('tiendas/historial', [
            'lista' => $lista ?? [], 'sinTablas' => $lista === null, 'f' => $f,
            'empleados' => $lista === null ? [] : $this->historial->empleados(),
        ]);
    }

    // Fecha Y-m-d que llega por la dirección, o '' si no es válida
    private static function fechaGet(string $campo): string
    {
        $v = (string) ($_GET[$campo] ?? '');
        $d = DateTime::createFromFormat('!Y-m-d', $v);
        return $d && $d->format('Y-m-d') === $v ? $v : '';
    }

    // Si todavía no se corrió database/admin.sql, las tablas no existen: se avisa en la página
    private function conTablas(callable $consulta): ?array
    {
        try {
            return $consulta();
        } catch (PDOException $e) {
            error_log('Tablas del administrador: ' . $e->getMessage());
            return null;
        }
    }
}
