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

    // 3. Reportes con filtros: periodo (hoy, 7 días, 30 días, este mes, todo o un rango), tienda y cómo agrupar
    public function reportes(): void
    {
        $this->exigir($this->usuario->puedeVerReportes());
        $periodo = (string) ($_GET['periodo'] ?? '30');
        $hoy = date('Y-m-d');
        $fecha = function (string $campo): ?string {
            $v = (string) ($_GET[$campo] ?? '');
            $d = DateTime::createFromFormat('!Y-m-d', $v);
            return $d && $d->format('Y-m-d') === $v ? $v : null;
        };
        [$desde, $hasta] = match ($periodo) {
            'hoy'   => [$hoy, $hoy],
            '7'     => [date('Y-m-d', strtotime('-6 days')), $hoy],
            'mes'   => [date('Y-m-01'), $hoy],
            'todo'  => ['2000-01-01', $hoy],
            'rango' => [$fecha('desde') ?? date('Y-m-d', strtotime('-29 days')), $fecha('hasta') ?? $hoy],
            default => [date('Y-m-d', strtotime('-29 days')), $hoy],
        };
        if (!in_array($periodo, ['hoy', '7', '30', 'mes', 'todo', 'rango'], true)) {
            $periodo = '30';
        }
        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }
        $agrupar = in_array($_GET['agrupar'] ?? '', ['dia', 'semana', 'mes'], true) ? $_GET['agrupar'] : 'dia';
        $tienda = self::entero('tienda', $_GET);
        $tiendas = $this->tiendas->tiendas();
        if ($tienda !== null && !in_array($tienda, array_map('intval', array_column($tiendas, 'ID_TIENDA')), true)) {
            $tienda = null;
        }
        $f = ['desde' => $desde, 'hasta' => $hasta, 'tienda' => $tienda];
        $productos = $this->reportes->porProducto($f);
        $menor = $productos;
        usort($menor, fn($a, $b) => $a['UNIDADES'] <=> $b['UNIDADES'] ?: $b['STOCK_ACTUAL'] <=> $a['STOCK_ACTUAL']);
        $this->vista('tiendas/reportes', [
            'f' => $f + ['periodo' => $periodo, 'agrupar' => $agrupar], 'tiendas' => $tiendas,
            'resumen' => $this->reportes->resumen($f),
            'serie' => $this->reportes->ventasPorPeriodo($f, $agrupar),
            'comparacion' => $this->reportes->comparacionTiendas($f),
            'metodos' => $this->reportes->metodosDePago($f),
            'top' => array_slice(array_filter($productos, fn($x) => $x['UNIDADES'] > 0), 0, 6),
            'menor' => array_slice($menor, 0, 6),
            'productos' => $productos,
        ]);
    }
}
