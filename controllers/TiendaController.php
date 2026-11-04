<?php
declare(strict_types=1);

/** Incidentes (todos reportan), tiendas y personal, y reportes (Administrador) */
class TiendaController extends Controlador
{
    public function __construct(
        Usuario $usuario,
        array $tienda,
        private TiendaRepository $tiendas,
        private IncidenteRepository $incidentes,
        private ReporteRepository $reportes,
        private ProductoRepository $productos,
        private CajaRepository $cajas
    ) {
        parent::__construct($usuario, $tienda);
    }

    public function incidentes(array $errores = [], array $form = []): void
    {
        $filtro = $this->usuario->puedeVerTodasLasTiendas() ? self::entero('tienda', $_GET) : null;
        $lista = $this->incidentes->listar($this->usuario);
        if ($filtro) {
            $lista = array_values(array_filter($lista, fn($i) => (int) $i['ID_TIENDA'] === $filtro));
        }
        $this->vista('tiendas/incidentes', [
            'lista' => $lista, 'tiendas' => $this->tiendas->tiendas(), 'filtro' => $filtro, 'protocolos' => $this->incidentes->protocolos(),
            'zonas' => $this->tiendas->zonas($this->usuario->getIdTienda()), 'errores' => $errores, 'form' => $form,
        ]);
    }

    public function reportarIncidente(): void
    {
        $this->exigir($this->usuario->puedeReportarIncidente());
        $form = ['protocolo' => self::texto('protocolo', 9), 'zona' => self::texto('zona', 9), 'descripcion' => self::texto('descripcion', 400),
                 'heridos' => ($_POST['heridos'] ?? 'N') === 'S' ? 'S' : 'N', 'perdida' => self::texto('perdida', 12), 'pnp' => self::texto('pnp', 20)];
        $errores = [];
        if (mb_strlen($form['descripcion']) < 10) {
            $errores['descripcion'] = 'Cuenta un poco más (mínimo 10 letras).';
        }
        $perdida = $form['perdida'] === '' ? 0.0 : self::decimal('perdida');
        if ($perdida === null) {
            $errores['perdida'] = 'Desde 0, con hasta 2 decimales (CK_INC_2).';
        }
        $ids = array_map('intval', array_column($this->incidentes->protocolos(), 'ID_PROTOCOLO'));
        if (!in_array((int) $form['protocolo'], $ids, true)) {
            $errores['protocolo'] = 'Elige el protocolo.';
        }
        if (!$errores) {
            try {
                $this->incidentes->reportar($this->usuario, (int) $form['protocolo'], (int) $form['zona'], $form['descripcion'],
                    $form['heridos'] === 'S', $perdida, $form['pnp'] ?: null);
                $this->aviso('ok', 'Incidente reportado. Queda «Abierto» hasta que el responsable lo revise.');
                $this->redirigir('incidentes');
            } catch (DomainException $e) {
                $errores['zona'] = $e->getMessage();
            }
        }
        $this->incidentes($errores, $form);
    }

    public function avanzarIncidente(): void
    {
        $this->exigir($this->usuario->puedeGestionarIncidentes());
        try {
            $id = self::entero('id') ?? 0;
            $estado = $this->incidentes->avanzar($id, $this->usuario);
            $this->aviso('ok', "Incidente $id: ahora está «" . ($estado === 'CERRADO' ? 'Cerrado' : 'En investigación') . '».');
        } catch (DomainException $e) {
            $this->aviso('err', $e->getMessage());
        }
        $this->redirigir('incidentes');
    }

    public function tiendas(): void
    {
        $this->exigir($this->usuario->puedeVerPersonal());
        $filtro = self::entero('tienda', $_GET);
        $this->vista('tiendas/tiendas', [
            'tiendas' => $this->tiendas->tiendas(), 'zonas' => $this->tiendas->zonas(),
            'personal' => $this->tiendas->personal($filtro ?: null), 'filtro' => $filtro,
        ]);
    }

    // Cuadre de stock del día: lo que había al empezar, lo que entró y salió hoy y con cuánto quedó
    public function cuadreStock(): void
    {
        $this->exigir($this->usuario->puedeVerCuadreStock());
        $this->vista('tiendas/cuadre', ['filas' => $this->reportes->cuadreStockHoy()]);
    }

    public function reportes(): void
    {
        $this->exigir($this->usuario->puedeVerReportes());
        $this->vista('tiendas/reportes', [
            'resumen' => $this->reportes->resumen(), 'porTienda' => $this->reportes->ventasPorTienda(),
            'metodos' => $this->reportes->metodosDePago(), 'top' => $this->reportes->masVendidos(),
            'reponer' => array_filter($this->productos->obtenerTodos(), fn(Producto $p) => $p->estadoStock() !== 'Normal'),
        ]);
    }
}
