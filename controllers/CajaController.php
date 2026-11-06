<?php
declare(strict_types=1);

/**
 * Caja: abrir una sesión, ver el cuadre y cerrarla contando el efectivo.
 * Cualquiera abre su caja; solo Supervisor y Administrador la cierran.
 */
class CajaController extends Controlador
{
    public function __construct(Usuario $usuario, array $tienda, private CajaRepository $repo, private TiendaRepository $tiendas)
    {
        parent::__construct($usuario, $tienda);
    }

    public function index(array $errores = [], array $form = []): void
    {
        $mia = $this->usuario->puedeAbrirCaja() ? $this->repo->sesionAbiertaDe($this->usuario->getIdEmpleado()) : null;
        $this->vista('caja/index', [
            'mia' => $mia,
            'resumen' => $mia ? $this->repo->resumenVentas((int) $mia['ID_SESION']) : null,
            'efectivoMia' => $mia ? $this->repo->efectivoSistema((int) $mia['ID_SESION']) : 0.0,
            'libres' => $this->repo->cajasLibres(),
            'cajas' => $this->repo->cajasConEstado(),
            'abiertasTienda' => $this->repo->abiertasPorTienda(),
            'tiendas' => $this->tiendas->tiendas(),
            'turnos' => $this->repo->turnos(),
            'abiertas' => $this->usuario->puedeCerrarCaja() || $this->usuario->puedeVerDineroCajas() ? $this->repo->sesiones($this->usuario, true) : [],
            'vendidoCaja' => fn(int $id): array => $this->repo->resumenVentas($id),
            'historial' => $this->repo->sesiones($this->usuario),
            'efectivo' => fn(int $id): float => $this->repo->efectivoSistema($id),
            'errores' => $errores,
            'form' => $form,
        ]);
    }

    /**
     * Pantalla al iniciar sesión (solo cajero sin caja abierta): elige en qué tienda
     * abre (Cayma, Cerro Colorado o Yanahuara), la caja, el turno y el monto.
     */
    public function inicio(array $errores = [], array $form = []): void
    {
        if (!$this->usuario->puedeAbrirCaja() || $this->repo->sesionAbiertaDe($this->usuario->getIdEmpleado()) !== null) {
            $this->redirigir('listar');
        }
        // Turno sugerido según la hora: antes de las 3 p. m. Mañana, después Tarde
        $turnos = $this->repo->turnos();
        $sugerido = '';
        foreach ($turnos as $id => $t) {
            if (date('H:i') >= $t['HORA_INICIO'] && date('H:i') < $t['HORA_FIN']) {
                $sugerido = (string) $id;
            }
        }
        // Tienda marcada al inicio: la suya si tiene cajas para abrir; si no, la primera que tenga
        $libres = $this->repo->cajasLibres();
        $conCajas = array_unique(array_map(fn($c) => (string) $c['ID_TIENDA'], $libres));
        $tienda = in_array((string) $this->usuario->getIdTienda(), $conCajas, true) ? (string) $this->usuario->getIdTienda() : (string) (reset($conCajas) ?: '');
        $this->vista('caja/inicio', [
            'tiendas' => $this->tiendas->tiendas(), 'libres' => $libres, 'cajas' => $this->repo->cajasConEstado(), 'turnos' => $turnos,
            'abiertasTienda' => $this->repo->abiertasPorTienda(),
            'errores' => $errores, 'form' => $form + ['tienda' => $tienda, 'turno' => $sugerido ?: (string) array_key_first($turnos)],
        ]);
    }

    public function abrir(): void
    {
        $this->exigir($this->usuario->puedeAbrirCaja());
        $desdeInicio = ($_POST['desde'] ?? '') === 'inicio';
        $form = ['tienda' => self::texto('tienda', 9), 'caja' => self::texto('caja', 9), 'turno' => self::texto('turno', 9), 'monto' => self::texto('monto', 12)];
        $volver = fn(array $err) => $desdeInicio ? $this->inicio($err, $form) : $this->index($err, $form);
        $monto = self::decimal('monto');
        $libre = array_values(array_filter($this->repo->cajasLibres(), fn($c) => (string) $c['ID_CAJA'] === $form['caja']))[0] ?? null;
        if ($libre === null) {
            $volver(['caja' => 'Elige una caja libre.']);
            return;
        }
        if ($form['tienda'] !== '' && (string) $libre['ID_TIENDA'] !== $form['tienda']) {
            $volver(['caja' => 'Esa caja no es de la tienda que elegiste.']);
            return;
        }
        if (!isset($this->repo->turnos()[(int) $form['turno']])) {
            $volver(['turno' => 'Elige el turno.']);
            return;
        }
        if ($monto === null) {
            $volver(['monto' => 'Escribe el monto con el que abres (desde 0).']);
            return;
        }
        try {
            $id = $this->repo->abrir((int) $form['caja'], $this->usuario, (int) $form['turno'], $monto);
            Historial::anotar($this->usuario, 'ABRIR_CAJA', "Abrió {$libre['TIENDA']} · Caja N° {$libre['NUMERO_CAJA']} con " . sol($monto) . " (sesión $id)");
            $this->aviso('ok', "Caja abierta en {$libre['TIENDA']} · Caja N° {$libre['NUMERO_CAJA']} con " . sol($monto) . " (sesión $id). Ya puedes vender.");
        } catch (DomainException $e) {
            $this->aviso('err', $e->getMessage());
            $this->redirigir($desdeInicio ? 'caja_inicio' : 'caja');
        }
        $this->redirigir($desdeInicio ? 'venta' : 'caja');
    }

    public function cerrar(): void
    {
        $this->exigir($this->usuario->puedeCerrarCaja());
        $id = self::entero('sesion') ?? 0;
        $contado = self::decimal('contado');
        if ($contado === null) {
            $this->aviso('err', 'Escribe el efectivo contado para cerrar la caja.');
            $this->redirigir('caja');
        }
        try {
            [$sistema, $contado, $dif] = $this->repo->cerrar($id, $contado, $this->usuario);
            $nota = $dif < 0 ? ' (faltante)' : ($dif > 0 ? ' (sobrante)' : '');
            Historial::anotar($this->usuario, 'CERRAR_CAJA', "Cerró la sesión $id · sistema " . sol($sistema) . ' · contado ' . sol($contado) . ' · diferencia ' . sol($dif) . $nota);
            $this->aviso($dif < 0 ? 'err' : 'ok', "Sesión $id cerrada. Sistema " . sol($sistema) . ' · contado ' . sol($contado) . ' · diferencia ' . sol($dif) . $nota . '.');
        } catch (DomainException $e) {
            $this->aviso('err', $e->getMessage());
        }
        $this->redirigir('caja');
    }
}
