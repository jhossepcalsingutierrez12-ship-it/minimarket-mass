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

    public function abrir(): void
    {
        $this->exigir($this->usuario->puedeAbrirCaja());
        $form = ['caja' => self::texto('caja', 9), 'turno' => self::texto('turno', 9), 'monto' => self::texto('monto', 12)];
        $monto = self::decimal('monto');
        if ($monto === null) {
            $this->index(['monto' => 'Escribe el monto con el que abres (desde 0).'], $form);
            return;
        }
        if (!isset($this->repo->turnos()[(int) $form['turno']])) {
            $this->index(['turno' => 'Elige el turno.'], $form);
            return;
        }
        try {
            $id = $this->repo->abrir((int) $form['caja'], $this->usuario, (int) $form['turno'], $monto);
            $this->aviso('ok', "Caja abierta: sesión $id. Ya puedes vender.");
        } catch (DomainException $e) {
            $this->aviso('err', $e->getMessage());
        }
        $this->redirigir('caja');
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
            $this->aviso($dif < 0 ? 'err' : 'ok', "Sesión $id cerrada. Sistema " . sol($sistema) . ' · contado ' . sol($contado) . ' · diferencia ' . sol($dif) . $nota . '.');
        } catch (DomainException $e) {
            $this->aviso('err', $e->getMessage());
        }
        $this->redirigir('caja');
    }
}
