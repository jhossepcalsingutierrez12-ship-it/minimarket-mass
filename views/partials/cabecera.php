<?php
/**
 * Parte de arriba de cada página con sesión: <head>, encabezado amarillo y menú lateral.
 * Antes de incluirla, la vista define $titulo (texto de la pestaña) y $seccion (opción activa).
 * El menú solo muestra lo que el rol ($u) puede usar.
 * @var string $titulo @var string $seccion @var Usuario $u @var array $tiendaActual
 */
require_once __DIR__ . '/ayudas.php';

$iconos = [
    'cat'    => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>',
    'caja'   => '<rect x="3" y="7" width="18" height="13" rx="1"/><path d="M7 7V4h10v3"/><line x1="3" y1="12" x2="21" y2="12"/>',
    'carro'  => '<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.5 12h12L22 7H6"/>',
    'lista'  => '<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/>',
    'cal'    => '<rect x="3" y="5" width="18" height="16" rx="1"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="3" x2="8" y2="7"/><line x1="16" y1="3" x2="16" y2="7"/>',
    'cuadre' => '<path d="M9 11l3 3 8-8"/><path d="M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h9"/>',
    'merma'  => '<path d="M4 7h16l-1.5 13h-13z"/><path d="M9 7V4h6v3"/><line x1="10" y1="11" x2="10" y2="16"/><line x1="14" y1="11" x2="14" y2="16"/>',
    'mas'    => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
    'pdf'    => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="14 3 14 9 20 9"/>',
    'alerta' => '<path d="M12 3 2 21h20z"/><line x1="12" y1="10" x2="12" y2="14"/><line x1="12" y1="17.5" x2="12" y2="17.6"/>',
    'tienda' => '<path d="M3 9l2-5h14l2 5"/><path d="M4 9v11h16V9"/><path d="M9 20v-6h6v6"/>',
    'campana'=> '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
    'camion' => '<rect x="1" y="6" width="14" height="11"/><path d="M15 10h4l3 3v4h-7z"/><circle cx="6" cy="19" r="2"/><circle cx="18" cy="19" r="2"/>',
    'bolsa'  => '<path d="M5 8h14l-1 13H6z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>',
    'cierre' => '<rect x="3" y="7" width="18" height="13" rx="1"/><path d="M7 7V4h10v3"/><path d="M9 14l2 2 4-4"/>',
    'reloj'  => '<circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/>',
    'chart'  => '<line x1="4" y1="20" x2="20" y2="20"/><rect x="6" y="11" width="3" height="7"/><rect x="11" y="6" width="3" height="12"/><rect x="16" y="13" width="3" height="5"/>',
];
// [grupo => [[acción, texto, ícono, ¿el rol lo ve?], ...]]
$menu = [
    // El cajero ve primero su caja; los demás, el catálogo
    'Tienda' => $u->puedeAbrirCaja() ? [
        ['caja', 'Caja', 'caja', true],
        ['venta', 'Nueva venta', 'carro', true],
        ['ventas', 'Ventas', 'lista', $u->puedeVerVentas()],
        ['listar', 'Catálogo', 'cat', true],
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
        ['historial', 'Historial de actividades', 'reloj', $u->puedeVerHistorial()],
    ],
];
// Cajero que todavía no abre su caja: el menú solo muestra «Abre tu caja»
if (defined('CAJERO_SIN_CAJA') && CAJERO_SIN_CAJA) {
    $menu = ['Tienda' => [['caja_inicio', 'Abre tu caja', 'caja', true]]];
    $seccion = 'caja_inicio';
}
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
<body>
<div class="topbar">
    <span><?= e($tiendaActual['NOMBRE'] ?? 'Minimarket Mass') ?> · Abierto de <?= e($tiendaActual['HORA_APERTURA'] ?? '07:00') ?> a <?= e($tiendaActual['HORA_CIERRE'] ?? '22:00') ?></span>
    <span class="reloj" id="reloj"></span>
</div>
<header>
    <a class="logo" href="?accion=listar"><span class="badge">MASS</span><span class="txt">Minimarket<br>Arequipa</span></a>
    <?php require __DIR__ . '/barra_sesion.php'; ?>
</header>
<div class="panel">
    <nav class="lateral" aria-label="Menú">
        <?php foreach ($menu as $grupo => $opciones):
            $visibles = array_filter($opciones, fn($o) => $o[3]);
            if (!$visibles) continue; ?>
            <span class="grupo"><?= e($grupo) ?></span>
            <?php foreach ($visibles as [$accion, $texto, $icono]): ?>
                <a href="?accion=<?= e($accion) ?>" class="<?= $seccion === $accion ? 'activo' : '' ?>">
                    <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?= $iconos[$icono] ?></svg>
                    <?= e($texto) ?>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>
    <main class="contenido">
