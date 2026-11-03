<?php
/** @var Producto[] $productos @var Usuario $u */
$titulo = 'Catálogo en PDF';
$seccion = 'pdf';
require __DIR__ . '/../partials/cabecera.php';

$cuenta = fn(string $estado): int => count(array_filter($productos, fn(Producto $p) => $p->estadoStock() === $estado));
$tinta = ['Normal' => 'var(--ok)', 'Reponer' => 'var(--warn)', 'Agotado' => 'var(--bad)'];
?>
<div class="cab">
    <div>
        <h1>Catálogo en PDF</h1>
        <p class="sub">Vista previa de la hoja. El botón abre la ventana de impresión para guardarla como PDF.</p>
    </div>
    <div class="botones" style="margin-bottom:18px"><button class="btn" type="button" data-imprimir="imp-hoja">Imprimir o guardar PDF</button></div>
</div>
<div class="hoja-wrap">
    <div class="hoja">
        <div class="enc">
            <div style="display:flex;align-items:center;gap:12px">
                <span class="badge">MASS</span>
                <div>
                    <div style="font-family:'Archivo Black',sans-serif;font-size:1.2rem;text-transform:uppercase">Catálogo de productos</div>
                    <div style="font-size:.8rem">Minimarket Mass Arequipa S.A.C. · RUC 20601234567</div>
                </div>
            </div>
            <div style="text-align:right;font-size:.75rem;line-height:1.5">Generado: <?= date('d/m/Y H:i') ?><br>Por: <?= e($u->getNombreCompleto()) ?> (<?= e($u->rol()) ?>)</div>
        </div>
        <p style="margin:0;font-size:.82rem">Solo productos activos. Precios en soles con IGV 18 % incluido.</p>
        <table>
            <thead><tr><th>SKU</th><th>Producto</th><th class="num">Precio</th><th class="num">IGV</th><th class="num">Stock</th><th>Estado</th></tr></thead>
            <tbody>
            <?php foreach ($productos as $p): ?>
                <tr>
                    <td style="font-family:Consolas,monospace"><?= e($p->getCodigoSku()) ?></td>
                    <td><?= e($p->getNombre()) ?></td>
                    <td class="num"><?= sol($p->getPrecioVenta()) ?></td>
                    <td class="num"><?= sol($p->igv()) ?></td>
                    <td class="num"><?= $p->getStockActual() ?></td>
                    <td style="font-weight:700;color:<?= $tinta[$p->estadoStock()] ?>"><?= e($p->estadoStock()) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div style="font-size:.82rem"><b><?= count($productos) ?></b> productos activos · <b><?= $cuenta('Reponer') ?></b> por reponer · <b><?= $cuenta('Agotado') ?></b> agotados</div>
        <div class="pie">Proyecto académico del grupo ILTM · SENATI CFP Arequipa · NRC 16211. Datos de prueba.</div>
    </div>
</div>

<?php require __DIR__ . '/../partials/pie.php'; ?>
