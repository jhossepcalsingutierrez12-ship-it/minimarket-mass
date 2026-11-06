<?php
/** Historial de actividades (solo Administrador) @var array $lista @var bool $sinTablas @var array $f @var array $empleados */
$titulo = 'Historial de actividades';
$seccion = 'historial';
require __DIR__ . '/../partials/cabecera.php';

$clase = ['ANULAR_VENTA' => 'bad', 'REGISTRAR_MERMA' => 'warn', 'CAMBIAR_PRECIO' => 'warn', 'BAJA_PRODUCTO' => 'bad',
          'AGREGAR_PRODUCTO' => 'ok', 'REGISTRAR_COMPRA' => 'ok', 'RECIBIR_LOTE' => 'ok'];
?>
<h1>Historial de actividades</h1>
<p class="sub">Quién hizo qué y cuándo: agregar productos, cambiar precios, anular ventas, registrar mermas, recibir lotes, abrir y cerrar cajas.
    Se guarda solo y nadie lo puede borrar desde el sistema.</p>
<?php $ayuda = [
    'Guarda <b>quién hizo qué y cuándo</b>: agregar productos, cambiar precios, anular ventas, registrar o autorizar mermas, recibir lotes, abrir y cerrar cajas.',
    'Se anota <b>solo</b> cada vez que alguien hace una de esas acciones, y no se puede borrar desde el sistema.',
    'Filtra por actividad, empleado o fechas para revisar, por ejemplo, <b>quién anuló una venta</b> o <b>quién cambió un precio</b>.',
]; require __DIR__ . '/../partials/ayuda.php'; ?>
<?= aviso() ?>

<?php if ($sinTablas): ?>
    <?php require __DIR__ . '/../partials/falta_admin_sql.php'; ?>
<?php else: ?>
    <form class="filtros" method="get">
        <input type="hidden" name="accion" value="historial">
        <select name="tipo" aria-label="Qué hizo">
            <option value="">Todas las actividades</option>
            <?php foreach (HistorialRepository::ACCIONES as $k => $txt): ?>
                <option value="<?= e($k) ?>" <?= $f['accion'] === $k ? 'selected' : '' ?>><?= e($txt) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="empleado" aria-label="Quién">
            <option value="">Todos los empleados</option>
            <?php foreach ($empleados as $em): ?>
                <option value="<?= (int) $em['ID_EMPLEADO'] ?>" <?= $f['empleado'] === (int) $em['ID_EMPLEADO'] ? 'selected' : '' ?>><?= e($em['NOMBRES'] . ' ' . $em['APELLIDOS']) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="fecha-filtro">Desde <input type="date" name="desde" value="<?= e($f['desde']) ?>"></label>
        <label class="fecha-filtro">Hasta <input type="date" name="hasta" value="<?= e($f['hasta']) ?>"></label>
        <button class="btn sm" type="submit">Filtrar</button>
        <a class="btn sm ghost" href="?accion=historial">Limpiar</a>
    </form>

    <div class="tabla"><table>
        <thead><tr><th>Fecha y hora</th><th>Quién</th><th>Qué hizo</th><th>Detalle</th></tr></thead>
        <tbody>
        <?php foreach ($lista as $h): ?>
            <tr>
                <td class="nw"><?= fecha($h['FECHA_HORA']) ?></td>
                <td><b><?= e(nombreCorto($h['NOMBRES'], $h['APELLIDOS'])) ?></b><br><span class="sub" style="font-size:.78rem"><?= e($h['CARGO']) ?> · <?= e($h['TIENDA']) ?></span></td>
                <td><?= tag(HistorialRepository::ACCIONES[$h['ACCION']] ?? $h['ACCION'], $clase[$h['ACCION']] ?? 'gris') ?></td>
                <td><?= e($h['DETALLE']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$lista): ?><tr><td colspan="4">No hay actividades con ese filtro.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
    <p class="sub" style="font-size:.8rem">Se muestran las últimas 300 actividades.</p>
<?php endif; ?>

<?php require __DIR__ . '/../partials/pie.php'; ?>
