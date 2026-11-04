<?php
/** @var array $lista @var array $protocolos @var array $zonas @var array $errores @var array $form @var Usuario $u */
$titulo = 'Incidentes';
$seccion = 'incidentes';
require __DIR__ . '/../partials/cabecera.php';

$textoEstado = ['ABIERTO' => ['Abierto', 'bad'], 'EN INVESTIGACION' => ['En investigación', 'warn'], 'CERRADO' => ['Cerrado', 'ok']];
$boton = ['ABIERTO' => 'Pasar a investigación', 'EN INVESTIGACION' => 'Cerrar incidente'];
?>
<h1>Incidentes</h1>
<p class="sub">Robos, sismos, incendios y simulacros según los protocolos de la tienda. Cajeros y supervisores reportan; el Supervisor les da seguimiento. El Administrador ve todos los de las 3 tiendas.</p>
<?= aviso() ?>
<div class="tabla"><table>
    <thead><tr><th>Fecha</th><th>Protocolo</th><th>Lugar</th><th>Qué pasó</th><th>Reportó</th><th>Responsable</th><th class="num">Pérdida</th><th>Estado</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($lista as $i): [$txt, $cls] = $textoEstado[$i['ESTADO']]; ?>
        <tr>
            <td class="nw"><?= fecha($i['FECHA_HORA']) ?></td>
            <td><?= e($i['PROTOCOLO']) ?><br><?= tag('Riesgo ' . mb_strtolower($i['NIVEL_RIESGO']), $i['NIVEL_RIESGO'] === 'ALTO' ? 'bad' : 'warn') ?></td>
            <td><?= e($i['TIENDA']) ?><br><span class="sub" style="font-size:.78rem"><?= e($i['ZONA']) ?></span></td>
            <td style="min-width:220px"><?= e($i['DESCRIPCION']) ?>
                <?= $i['HUBO_HERIDOS'] === 'S' ? '<br>' . tag('Hubo heridos', 'bad') : '' ?>
                <?= $i['NRO_DENUNCIA_PNP'] ? '<br><span class="sku">Denuncia PNP ' . e($i['NRO_DENUNCIA_PNP']) . '</span>' : '' ?></td>
            <td><?= e(nombreCorto($i['REP_NOMBRES'], $i['REP_APELLIDOS'])) ?></td>
            <td><?= e(nombreCorto($i['RES_NOMBRES'], $i['RES_APELLIDOS'])) ?></td>
            <td class="num"><?= sol((float) $i['MONTO_PERDIDA']) ?></td>
            <td><?= tag($txt, $cls) ?></td>
            <td><?php if ($u->puedeGestionarIncidentes() && isset($boton[$i['ESTADO']])): ?>
                <form method="post" action="?accion=avanzar_incidente">
                    <input type="hidden" name="csrf" value="<?= e(AuthController::token()) ?>">
                    <input type="hidden" name="id" value="<?= (int) $i['ID_INCIDENTE'] ?>">
                    <button class="btn sm ghost"><?= $boton[$i['ESTADO']] ?></button>
                </form>
            <?php endif; ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$lista): ?><tr><td colspan="9">No hay incidentes.</td></tr><?php endif; ?>
    </tbody>
</table></div>

<?php if ($u->puedeReportarIncidente()): ?>
<h2 class="sec">Reportar incidente</h2>
<form class="card form" method="post" action="?accion=incidentes" novalidate>
    <input type="hidden" name="csrf" value="<?= e(AuthController::token()) ?>">
    <div class="cols">
        <div class="<?= claseCampo($errores, 'protocolo') ?>">
            <label for="protocolo">Protocolo</label>
            <select id="protocolo" name="protocolo">
                <?php foreach ($protocolos as $p): ?>
                    <option value="<?= (int) $p['ID_PROTOCOLO'] ?>" <?= (string) $p['ID_PROTOCOLO'] === (string) ($form['protocolo'] ?? '') ? 'selected' : '' ?>><?= e($p['NOMBRE']) ?></option>
                <?php endforeach; ?>
            </select>
            <?= error($errores, 'protocolo') ?>
        </div>
        <div class="<?= claseCampo($errores, 'zona') ?>">
            <label for="zona">Zona de la tienda</label>
            <select id="zona" name="zona">
                <?php foreach ($zonas as $z): ?>
                    <option value="<?= (int) $z['ID_ZONA'] ?>" <?= (string) $z['ID_ZONA'] === (string) ($form['zona'] ?? '') ? 'selected' : '' ?>><?= e($z['NOMBRE'] . ($z['ES_ZONA_SEGURA'] === 'S' ? ' (zona segura)' : '')) ?></option>
                <?php endforeach; ?>
            </select>
            <?= error($errores, 'zona') ?>
        </div>
        <div class="<?= claseCampo($errores, 'descripcion') ?> todo">
            <label for="descripcion">Qué pasó *</label>
            <textarea id="descripcion" name="descripcion" maxlength="400"><?= valor($form, 'descripcion') ?></textarea>
            <?= error($errores, 'descripcion') ?>
        </div>
        <div class="campo">
            <label for="heridos">¿Hubo heridos?</label>
            <select id="heridos" name="heridos"><option value="N">No</option><option value="S" <?= ($form['heridos'] ?? '') === 'S' ? 'selected' : '' ?>>Sí</option></select>
        </div>
        <div class="<?= claseCampo($errores, 'perdida') ?>">
            <label for="perdida">Pérdida estimada (S/)</label>
            <input id="perdida" name="perdida" inputmode="decimal" value="<?= valor($form, 'perdida') ?>">
            <?= error($errores, 'perdida') ?>
        </div>
        <div class="campo">
            <label for="pnp">N° de denuncia PNP (opcional)</label>
            <input id="pnp" name="pnp" maxlength="20" value="<?= valor($form, 'pnp') ?>">
        </div>
    </div>
    <div class="botones"><button class="btn" type="submit">Reportar</button>
        <span style="font-size:.8rem;color:var(--muted)">El responsable será tu jefe directo (o tú, si no tienes jefe).</span></div>
</form>
<?php endif; ?>

<?php require __DIR__ . '/../partials/pie.php'; ?>
