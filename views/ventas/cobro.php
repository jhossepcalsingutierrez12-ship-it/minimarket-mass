<?php
/** @var float $total @var int $metodo @var string $nombreMetodo @var string $ref @var string $error */
$titulo = 'Cobrar';
$seccion = 'venta';
require __DIR__ . '/../partials/cabecera.php';

// Color de cada método, como en las apps
$colores = ['Efectivo' => '#137a3e', 'Yape' => '#742284', 'Plin' => '#00a3ad'];
$color = $colores[$nombreMetodo] ?? '#1f3b73';
$esQr = in_array($nombreMetodo, ['Yape', 'Plin'], true);
$csrf = e(AuthController::token());
// Billetes sugeridos para el efectivo: el total redondeado, al 10, al 50, 100 y 200
$billetes = array_values(array_unique(array_filter([ceil($total), ceil($total / 10) * 10, ceil($total / 50) * 50, 100, 200], fn($b) => $b >= $total)));
?>
<a class="volver" href="?accion=venta">← Volver a la venta</a>
<h1>Cobrar</h1>
<p class="sub">La venta se registra recién cuando confirmas el pago.</p>

<div class="pago" style="margin:0 auto 0 0">
    <div class="pago-cab" style="background:<?= $color ?>"><b><?= $esQr ? e(strtolower($nombreMetodo)) : e(str_starts_with($nombreMetodo, 'Tarjeta') ? 'Tarjeta' : $nombreMetodo) ?></b><small><?= e($nombreMetodo) ?></small></div>
    <form method="post" action="?accion=cobro" novalidate>
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <div class="pago-cuerpo" style="<?= $esQr ? 'color:' . $color : '' ?>">
            <div class="pago-monto" data-total="<?= number_format($total, 2, '.', '') ?>"><?= sol($total) ?></div>
            <?php if ($error): ?><div class="msg err"><?= e($error) ?></div><?php endif; ?>

            <?php if ($esQr): ?>
                <div class="pago-qr" data-qr="<?= e('PAGO SIMULADO ' . strtoupper($nombreMetodo) . '|Minimarket Mass Arequipa|958111000|S/ ' . number_format($total, 2) . '|' . $ref) ?>" data-qr-tam="200" data-qr-color="<?= $nombreMetodo === 'Yape' ? '#3b0f4a' : '#00454a' ?>"></div>
                <div style="color:var(--k);font-size:.92rem">Escanea con <?= e($nombreMetodo) ?> y paga a<br><b>Minimarket Mass Arequipa</b> · celular <b>958 111 000</b></div>
                <div class="esperando"><i></i> Esperando el pago…</div>
                <div class="campo"><label for="operacion">N° de operación que sale en la app</label>
                    <input id="operacion" name="operacion" inputmode="numeric" maxlength="12" autocomplete="off" autofocus></div>
                <p class="sub" style="font-size:.75rem;margin:0">Pago simulado · proyecto académico. El QR no cobra dinero real.</p>
            <?php elseif ($nombreMetodo === 'Efectivo'): ?>
                <div class="campo"><label for="recibido">Efectivo recibido</label>
                    <input id="recibido" name="recibido" inputmode="decimal" autocomplete="off" autofocus></div>
                <div class="botones" style="justify-content:center">
                    <?php foreach (array_slice($billetes, 0, 4) as $b): ?>
                        <button type="button" class="btn sm ghost" data-billete="<?= (int) $b ?>">S/ <?= (int) $b ?></button>
                    <?php endforeach; ?>
                </div>
                <div class="vuelto" id="vuelto">Vuelto: —</div>
            <?php else: ?>
                <div style="font-size:.95rem">Pide al cliente que acerque, inserte o pase su tarjeta en el POS.</div>
                <div class="esperando"><i></i> Esperando la tarjeta…</div>
                <p class="sub" style="font-size:.75rem;margin:0">POS simulado · proyecto académico.</p>
            <?php endif; ?>
        </div>
        <div class="pago-pie">
            <button type="submit" form="fCancelar" class="btn ghost">Cancelar</button>
            <button type="submit" class="btn" style="<?= $esQr ? "background:$color;border-color:$color;color:#fff" : '' ?>">
                <?= $esQr ? 'Confirmar pago' : ($nombreMetodo === 'Efectivo' ? 'Cobrar' : 'Pago aprobado') ?>
            </button>
        </div>
    </form>
    <form id="fCancelar" method="post" action="?accion=cancelar_cobro"><input type="hidden" name="csrf" value="<?= $csrf ?>"></form>
</div>

<?php require __DIR__ . '/../partials/pie.php'; ?>
