<?php
/** Tabla de sesiones de caja (la usan Caja y Reportes) @var array $historial */
?>
<div class="tabla"><table>
    <thead><tr><th>Sesión</th><th>Tienda · caja</th><th>Cajero</th><th>Turno</th><th>Apertura</th><th>Cierre</th><th class="num">Sistema</th><th class="num">Contado</th><th class="num">Diferencia</th><th>Estado</th></tr></thead>
    <tbody>
    <?php foreach ($historial as $s):
        $dif = $s['DIFERENCIA'] === null ? null : (float) $s['DIFERENCIA']; ?>
        <tr>
            <td><?= (int) $s['ID_SESION'] ?></td>
            <td><?= e($s['TIENDA']) ?> · N° <?= (int) $s['NUMERO_CAJA'] ?></td>
            <td><?= e(nombreCorto($s['CAJERO_NOMBRES'], $s['CAJERO_APELLIDOS'])) ?></td>
            <td><?= e($s['TURNO']) ?></td>
            <td class="nw"><?= fecha($s['FECHA_APERTURA']) ?></td>
            <td class="nw"><?= fecha($s['FECHA_CIERRE']) ?></td>
            <td class="num"><?= $s['EFECTIVO_SISTEMA'] === null ? '—' : sol((float) $s['EFECTIVO_SISTEMA']) ?></td>
            <td class="num"><?= $s['EFECTIVO_CONTADO'] === null ? '—' : sol((float) $s['EFECTIVO_CONTADO']) ?></td>
            <td class="num <?= $dif === null ? '' : ($dif < 0 ? 'neg' : ($dif > 0 ? 'posi' : '')) ?>"><?= $dif === null ? '—' : sol($dif) ?></td>
            <td><?= tag($s['ESTADO'], $s['ESTADO'] === 'ABIERTA' ? 'warn' : 'ok') ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$historial): ?><tr><td colspan="10">Todavía no hay sesiones.</td></tr><?php endif; ?>
    </tbody>
</table></div>
