<?php
/**
 * Parte de arriba de cada página con sesión: <head>, encabezado amarillo y menú lateral.
 * Antes de incluirla, la vista define $titulo (texto de la pestaña) y $seccion (opción activa).
 * El menú solo muestra lo que el rol ($u) puede usar.
 * @var string $titulo @var string $seccion @var Usuario $u @var array $tiendaActual
 */
require_once __DIR__ . '/ayudas.php';

// Íconos: un solo set para menú y botones, en views/partials/ayudas.php (ICONOS e icono())
// [grupo => [[acción, texto, ícono, ¿el rol lo ve?], ...]]
$menu = [
    // El cajero ve primero su caja; los demás, el catálogo
    'Tienda' => $u->puedeAbrirCaja() ? [
        ['caja', 'Caja', 'caja', true],
        ['venta', 'Nueva venta', 'carro', true],
        ['ventas', 'Ventas', 'lista', $u->puedeVerVentas()],
        ['listar', 'Catálogo', 'cat', true],
        ['consultas', 'Preguntas del Administrador', 'info', true],
    ] : [
        ['listar', 'Catálogo', 'cat', true],
        ['caja', 'Caja', 'caja', true],
        ['ventas', 'Ventas', 'lista', $u->puedeVerVentas()],
    ],
    'Inventario' => [
        ['alertas', 'Alertas de inventario', 'campana', $u->puedeVerAlertas()],
        ['lotes', 'Lotes y vencimientos', 'cal', $u->puedeVerLotes()],
        ['cuadre', 'Cuadre de stock', 'cuadre', $u->puedeVerCuadreStock()],
        ['mermas', 'Mermas', 'merma', true],
        ['agregar', 'Agregar producto', 'mas', $u->puedeAdministrarProductos()],
        ['pdf', 'Catálogo en PDF', 'pdf', $u->puedeAdministrarProductos()],
    ],
    'Seguridad' => [
        ['incidentes', 'Incidentes', 'alerta', true],
    ],
    'Administración' => [
        ['tiendas', 'Tiendas y personal', 'tienda', $u->puedeVerPersonal()],
        ['reportes', 'Reportes', 'chart', $u->puedeVerReportes()],
        ['faltantes', 'Faltantes de caja', 'billete', $u->puedeVerDineroCajas()],
        ['historial', 'Historial de actividades', 'reloj', $u->puedeVerHistorial()],
    ],
];
// Cajero que todavía no abre su caja: el menú solo muestra «Abre tu caja»
if (defined('CAJERO_SIN_CAJA') && CAJERO_SIN_CAJA) {
    $menu = ['Tienda' => [['caja_inicio', 'Abre tu caja', 'caja', true], ['consultas', 'Preguntas del Administrador', 'info', true]]];
    $seccion = $seccion === 'consultas' ? 'consultas' : 'caja_inicio';
}
// Administrador: faltantes de caja que todavía no vio (en la página de faltantes ya cuentan como vistos)
$faltantes = defined('FALTANTES_NUEVOS') && FALTANTES_NUEVOS !== null ? FALTANTES_NUEVOS : null;
$nFaltantes = $faltantes !== null && $seccion !== 'faltantes' ? $faltantes['n'] : 0;
// Cajero: preguntas del Administrador por un faltante que todavía no respondió
$nConsultas = defined('CONSULTAS_PENDIENTES') && $seccion !== 'consultas' ? CONSULTAS_PENDIENTES : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo) ?> · Minimarket Mass</title>
    <link href="https://fonts.googleapis.com/css2?family=Archivo+Black&family=Archivo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/estilos.css">
    <script src="js/qr.js" defer></script>
    <script src="js/app.js" defer></script>
</head>
<body<?= $faltantes !== null ? ' data-faltantes="' . $nFaltantes . '"' : '' ?>>
<div class="topbar">
    <span><?= e($tiendaActual['NOMBRE'] ?? 'Minimarket Mass') ?> · Abierto de <?= e($tiendaActual['HORA_APERTURA'] ?? '07:00') ?> a <?= e($tiendaActual['HORA_CIERRE'] ?? '22:00') ?></span>
    <span class="reloj" id="reloj"></span>
</div>
<a class="saltar" href="#contenido">Saltar al contenido</a>
<header>
    <button type="button" class="btn-menu" id="btnMenu" aria-label="Abrir menú" aria-expanded="false" aria-controls="menuLateral"><?= icono('menu', 22) ?></button>
    <a class="logo" href="?accion=listar"><span class="badge">MASS</span><span class="txt">Minimarket<br>Arequipa</span></a>
    <?php if ($faltantes !== null): ?>
        <a class="campana-noti<?= $nFaltantes ? ' hay' : '' ?>" id="campanaNoti" href="?accion=faltantes"
           aria-label="<?= $nFaltantes ? $nFaltantes . ' faltante(s) de caja sin revisar' : 'Faltantes de caja: nada nuevo' ?>"
           title="Faltantes de caja"><?= icono('campana', 22) ?><span class="noti-n" id="notiN"<?= $nFaltantes ? '' : ' hidden' ?>><?= $nFaltantes ?></span></a>
    <?php endif; ?>
    <?php require __DIR__ . '/barra_sesion.php'; ?>
</header>
<div class="panel">
    <nav class="lateral" id="menuLateral" aria-label="Menú">
        <?php foreach ($menu as $grupo => $opciones):
            $visibles = array_filter($opciones, fn($o) => $o[3]);
            if (!$visibles) continue; ?>
            <span class="grupo"><?= e($grupo) ?></span>
            <?php foreach ($visibles as [$accion, $texto, $icono]): ?>
                <a href="?accion=<?= e($accion) ?>" class="<?= $seccion === $accion ? 'activo' : '' ?>"<?= $seccion === $accion ? ' aria-current="page"' : '' ?>>
                    <?= icono($icono) ?>
                    <?= e($texto) ?>
                    <?php if ($accion === 'faltantes' && $nFaltantes): ?><span class="menu-n"><?= $nFaltantes ?></span><?php endif; ?>
                    <?php if ($accion === 'consultas' && $nConsultas): ?><span class="menu-n"><?= $nConsultas ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>
    <main class="contenido" id="contenido" tabindex="-1">
    <?php if ($nFaltantes): ?>
        <div class="banda-faltante" role="alert">
            <?= icono('billete', 22) ?>
            <span>
                <?php if ($faltantes['faltantes']): $nf = $faltantes['faltantes']; ?>
                    <b><?= $nf === 1 ? 'Faltó dinero al cerrar una caja' : "Faltó dinero al cerrar $nf cajas" ?>.</b>
                    <?= e(NotificacionRepository::texto($faltantes['ultimo'])) ?><?= $nf > 1 ? ' y ' . ($nf - 1) . ' más · total ' . e(sol($faltantes['total'])) : '' ?>.
                <?php endif; ?>
                <?php if ($faltantes['respuestas']): $nr = count($faltantes['respuestas']); ?>
                    <b><?= $nr === 1 ? 'Un cajero te respondió' : "$nr cajeros te respondieron" ?>.</b>
                    <?= e(NotificacionRepository::textoRespuesta($faltantes['respuestas'][0])) ?>.
                <?php endif; ?>
            </span>
            <a class="btn sm" href="?accion=faltantes">Revisar</a>
        </div>
    <?php endif; ?>
    <?php if ($nConsultas): ?>
        <div class="banda-faltante" role="alert">
            <?= icono('info', 22) ?>
            <span><b><?= $nConsultas === 1 ? 'El Administrador te pregunta' : "El Administrador te hizo $nConsultas preguntas" ?> por un faltante de dinero en tu caja.</b>
                Cuéntale qué pasó.</span>
            <a class="btn sm" href="?accion=consultas">Responder</a>
        </div>
    <?php endif; ?>
