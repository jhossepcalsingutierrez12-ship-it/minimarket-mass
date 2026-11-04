<?php
/** @var array $venta @var bool $recien */
$titulo = 'Comprobante ' . $venta['SERIE'] . '-' . $venta['NUMERO'];
$seccion = 'ventas';
require __DIR__ . '/../partials/cabecera.php';

$v = $venta;
$boleta = $v['TIPO_COMPROBANTE'] === 'BOLETA';
$pagoEfectivo = array_values(array_filter($v['pagos'], fn($p) => $p['VUELTO'] !== null))[0] ?? null;
?>
<a class="volver" href="?accion=ventas">← Volver a ventas</a>
<h1>Comprobante</h1>
<p class="sub"></p>
<?= aviso() ?>

<?php if ($recien): ?>
    <div class="pagado card" style="max-width:420px;margin-bottom:16px">
        <div class="check" aria-hidden="true">✓</div>
        <div class="pago-monto" style="font-size:1.6rem">¡Pagado!</div>
        <div><?= sol((float) $v['TOTAL']) ?> · <?= e($v['pagos'][0]['METODO'] ?? '') ?></div>
        <?php if ($pagoEfectivo): ?><div class="vuelto">Vuelto: <?= sol((float) $pagoEfectivo['VUELTO']) ?></div><?php endif; ?>
        <div class="sub" style="margin:0"><?= $boleta ? 'Boleta' : 'Factura' ?> <?= e($v['SERIE'] . '-' . $v['NUMERO']) ?> registrada. El inventario ya se actualizó.</div>
    </div>
<?php endif; ?>

<div class="card ticket" style="max-width:420px">
    <div class="c"><b>MINIMARKET MASS AREQUIPA S.A.C.</b><br>RUC 20601234567<br><?= e($v['TIENDA']) ?><br><?= e($v['DIRECCION']) ?>, <?= e($v['DISTRITO']) ?> - Arequipa<?= $v['TIENDA_TEL'] ? '<br>Tel. ' . e($v['TIENDA_TEL']) : '' ?></div><hr>
    <div class="c"><b><?= $boleta ? 'BOLETA DE VENTA ELECTRÓNICA' : 'FACTURA ELECTRÓNICA' ?></b><br><?= e($v['SERIE']) ?>-<?= str_pad((string) $v['NUMERO'], 8, '0', STR_PAD_LEFT) ?></div><hr>
    <div class="l"><span>Fecha</span><span><?= fecha($v['FECHA_HORA']) ?></span></div>
    <div class="l"><span>Caja</span><span>N° <?= (int) $v['NUMERO_CAJA'] ?> · <?= e($v['TURNO']) ?></span></div>
    <div class="l"><span>Cajero</span><span><?= e(nombreCorto($v['CAJERO_NOMBRES'], $v['CAJERO_APELLIDOS'])) ?></span></div>
    <div class="l"><span>Cliente</span><span><?= $v['ID_CLIENTE'] ? e($v['TIPO_DOCUMENTO'] . ' ' . $v['NRO_DOCUMENTO']) : 'Cliente varios' ?></span></div>
    <?php if ($v['ID_CLIENTE']): ?><div><?= e($v['NOMBRE_RAZON_SOCIAL']) ?></div><?php endif; ?>
    <hr>
    <div class="l"><b>Descripción</b><b>Importe</b></div>
    <?php foreach ($v['detalle'] as $d): ?>
        <div><?= e($d['NOMBRE']) ?></div>
        <div class="l"><span>&nbsp;&nbsp;<?= (float) $d['CANTIDAD'] + 0 ?> × <?= number_format((float) $d['PRECIO_UNITARIO'], 2) ?><?= (float) $d['DESCUENTO'] > 0 ? ' − ' . number_format((float) $d['DESCUENTO'], 2) : '' ?></span><span><?= number_format((float) $d['IMPORTE'], 2) ?></span></div>
    <?php endforeach; ?>
    <hr>
    <div class="l"><span>Op. gravada</span><span><?= sol((float) $v['SUBTOTAL']) ?></span></div>
    <div class="l"><span>IGV 18 %</span><span><?= sol((float) $v['IGV']) ?></span></div>
    <div class="l total"><span>IMPORTE TOTAL</span><span><?= sol((float) $v['TOTAL']) ?></span></div>
    <div style="font-size:.75rem;margin-top:4px"><?= e(enLetras((float) $v['TOTAL'])) ?></div>
    <hr>
    <?php foreach ($v['pagos'] as $p): ?>
        <div class="l"><span><?= e($p['METODO']) ?></span><span><?= sol((float) ($p['MONTO_RECIBIDO'] ?? $p['MONTO'])) ?></span></div>
        <?php if ($p['NRO_OPERACION']): ?><div class="l"><span>&nbsp;&nbsp;N° operación</span><span><?= e($p['NRO_OPERACION']) ?></span></div><?php endif; ?>
        <?php if ($p['VUELTO'] !== null): ?><div class="l"><span>&nbsp;&nbsp;Vuelto</span><span><?= sol((float) $p['VUELTO']) ?></span></div><?php endif; ?>
    <?php endforeach; ?>
    <div class="c" style="margin:10px 0 4px">
        <?= $v['ESTADO'] === 'ANULADA' ? '<span class="sello" style="border-color:var(--bad);color:var(--bad)">ANULADA</span>' : '<span class="sello">PAGADO</span>' ?>
    </div>
    <div class="qrw" data-qr="<?= e(textoQrSunat($v)) ?>" data-qr-tam="120"></div>
    <div class="c" style="font-size:.72rem">Representación impresa de la <?= $boleta ? 'boleta de venta' : 'factura' ?> electrónica.<br>¡Gracias por su compra!</div>
    <div class="prueba">DOCUMENTO DE PRUEBA · SIN VALOR TRIBUTARIO</div>
</div>
<div class="botones" style="margin-top:16px">
    <button class="btn amarillo" type="button" data-imprimir="imp-ticket">Imprimir comprobante</button>
    <?php if ($u->puedeAbrirCaja()): ?><a class="btn" href="?accion=venta">Nueva venta</a><?php endif; ?>
    <a class="btn ghost" href="?accion=ventas">Ver todas las ventas</a>
</div>
<p class="sub" style="font-size:.8rem">Sale en tamaño de ticketera (80 mm). En la ventana de impresión también puedes elegir «Guardar como PDF».</p>

<?php require __DIR__ . '/../partials/pie.php'; ?>
