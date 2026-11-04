<?php
declare(strict_types=1);

/**
 * Inventario: lotes y vencimientos (Supervisor y Administrador) y mermas (todos registran;
 * Supervisor y Administrador autorizan, nunca quien la registró).
 */
class InventarioController extends Controlador
{
    private const MOTIVOS = ['VENCIMIENTO' => 'Vencimiento', 'DETERIORO' => 'Deterioro', 'ROBO' => 'Robo'];

    public function __construct(
        Usuario $usuario,
        array $tienda,
        private LoteRepository $lotes,
        private MermaRepository $mermas,
        private ProductoRepository $productos,
        private TiendaRepository $tiendas
    ) {
        parent::__construct($usuario, $tienda);
    }

    /* ---------- Lotes ---------- */

    public function lotes(array $errores = [], array $form = []): void
    {
        $this->exigir($this->usuario->puedeVerLotes());
        $productos = $this->productos->obtenerTodos();
        $idSel = (int) ($form['producto'] ?? $_GET['producto'] ?? $productos[0]->getIdProducto());
        $sel = $this->productos->buscarPorId($idSel) ?? $productos[0];
        $form += ['producto' => (string) $sel->getIdProducto(), 'codigo' => $this->lotes->codigoSugerido($sel->getCodigoSku())];
        $this->vista('inventario/lotes', [
            'lista' => $this->lotes->listar(), 'productos' => $productos, 'seleccionado' => $sel,
            'errores' => $errores, 'form' => $form,
        ]);
    }

    public function recibirLote(): void
    {
        $this->exigir($this->usuario->puedeVerLotes());
        $form = ['producto' => self::texto('producto', 9), 'codigo' => strtoupper(self::texto('codigo', 20)),
                 'vence' => self::texto('vence', 10), 'cantidad' => self::texto('cantidad', 9)];
        $p = $this->productos->buscarPorId((int) $form['producto']);
        $errores = [];
        $cant = self::entero('cantidad');
        if ($p === null || !$p->estaActivo()) {
            $errores['producto'] = 'Elige un producto activo.';
        }
        if ($cant === null || $cant <= 0) {
            $errores['cantidad'] = 'Debe ser un entero mayor que 0 (CK_LOT_1).';
        }
        if ($form['vence'] !== '' && DateTime::createFromFormat('!Y-m-d', $form['vence']) === false) {
            $errores['vence'] = 'Fecha no válida.';
        }
        if ($p !== null && $p->esPerecible() && $form['vence'] === '') {
            $errores['vence'] = 'Es perecible: pon la fecha de vencimiento.';
        }
        if ($errores) {
            $this->lotes($errores, $form);
            return;
        }
        $this->lotes->recibir($p->getIdProducto(), $form['codigo'] ?: null, $form['vence'] ?: null, $cant);
        $this->aviso('ok', 'Lote ' . ($form['codigo'] ?: 'sin código') . " recibido: +$cant und. de {$p->getNombre()} (stock ahora " . ($p->getStockActual() + $cant) . ').');
        $this->redirigir('lotes');
    }

    /* ---------- Mermas ---------- */

    public function mermas(array $errores = [], array $form = []): void
    {
        $productos = $this->productos->obtenerTodos();
        $idSel = (int) ($form['producto'] ?? $_GET['producto'] ?? $productos[0]->getIdProducto());
        if ($this->productos->buscarPorId($idSel) === null) {
            $idSel = $productos[0]->getIdProducto();
        }
        // Desde «Lotes» llega ?lote=3 para retirar un lote vencido
        $form += ['producto' => (string) $idSel, 'lote' => (string) ($_GET['lote'] ?? ''),
                  'motivo' => isset($_GET['lote']) ? 'VENCIMIENTO' : '', 'observacion' => isset($_GET['lote']) ? 'Lote vencido retirado de góndola' : ''];
        $this->vista('inventario/mermas', [
            'lista' => $this->mermas->listar($this->usuario), 'productos' => $productos,
            'lotesProducto' => $this->lotes->lotesDe($idSel), 'zonas' => $this->tiendas->zonas($this->usuario->getIdTienda()),
            'motivos' => self::MOTIVOS, 'errores' => $errores, 'form' => $form,
        ]);
    }

    public function registrarMerma(): void
    {
        $this->exigir($this->usuario->puedeRegistrarMerma());
        $form = ['producto' => self::texto('producto', 9), 'lote' => self::texto('lote', 9), 'zona' => self::texto('zona', 9),
                 'motivo' => self::texto('motivo', 11), 'cantidad' => self::texto('cantidad', 9), 'observacion' => self::texto('observacion', 200)];
        $errores = [];
        $cant = self::entero('cantidad');
        if ($cant === null || $cant <= 0) {
            $errores['cantidad'] = 'Unidades enteras, mayor que 0 (CK_MER_2).';
        }
        if (!isset(self::MOTIVOS[$form['motivo']])) {
            $errores['motivo'] = 'Elige el motivo.';
        }
        if (!$errores) {
            try {
                $valor = $this->mermas->registrar($this->usuario, (int) $form['producto'], $form['lote'] === '' ? null : (int) $form['lote'],
                    (int) $form['zona'], $form['motivo'], $cant, $form['observacion'] ?: null);
                $this->aviso('ok', "Merma registrada (" . sol($valor) . "). El stock ya bajó y queda pendiente de autorización.");
                $this->redirigir('mermas');
            } catch (DomainException $e) {
                $errores['cantidad'] = $e->getMessage();
            }
        }
        $this->mermas($errores, $form);
    }

    public function autorizarMerma(): void
    {
        $this->exigir($this->usuario->puedeAutorizarMerma());
        try {
            $m = $this->mermas->autorizar(self::entero('id') ?? 0, $this->usuario);
            $this->aviso('ok', "Merma de {$m['NOMBRE']} aprobada por {$this->usuario->getNombreCompleto()}.");
        } catch (DomainException $e) {
            $this->aviso('err', $e->getMessage());
        }
        $this->redirigir('mermas');
    }
}
