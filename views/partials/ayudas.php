<?php
declare(strict_types=1);

// Funciones pequeñas que usan las vistas. Solo dan formato: no consultan la base.

// Escapa el texto antes de imprimirlo (evita XSS)
function e(?string $texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

// 24.9 -> "S/ 24.90"
function sol(float $monto): string
{
    return 'S/ ' . number_format($monto, 2);
}

// La categoría sale de las 3 primeras letras del SKU: ABA-0001 -> Abarrotes
const CATEGORIAS = [
    'ABA' => ['nombre' => 'Abarrotes', 'icono' => '🍚'],
    'LAC' => ['nombre' => 'Lácteos',   'icono' => '🥛'],
    'BEB' => ['nombre' => 'Bebidas',   'icono' => '🥤'],
    'LIM' => ['nombre' => 'Limpieza',  'icono' => '🧴'],
];

function categoriaDe(string $sku): string
{
    return substr($sku, 0, 3);
}

// Imagen de la categoría (public/img/categorias/ABA.svg...) para guiarse: Abarrotes, Lácteos, Bebidas, Limpieza
function imagenCategoria(string $codigo, int $tam = 22): string
{
    $cat = CATEGORIAS[$codigo] ?? null;
    return $cat ? '<img class="img-cat" src="img/categorias/' . e($codigo) . '.svg" width="' . $tam . '" height="' . $tam . '" alt="">' : '';
}

// Etiqueta de categoría con su imagen: [imagen] Lácteos
function etiquetaCategoria(string $sku): string
{
    $codigo = categoriaDe($sku);
    return '<span class="cat-ref cat-' . e($codigo) . '">' . imagenCategoria($codigo, 18) . e(CATEGORIAS[$codigo]['nombre'] ?? 'Sin categoría') . '</span>';
}

// Foto en public/img/productos/<SKU>.jpg; si no existe, muestra la imagen de la categoría
function fotoProducto(Producto $p): string
{
    return fotoSku($p->getCodigoSku(), $p->getNombre());
}

function fotoSku(string $sku, string $nombre, string $clase = ''): string
{
    $archivo = __DIR__ . '/../../public/img/productos/' . $sku . '.jpg';
    if (is_file($archivo)) {
        // ?v= cambia cuando se sube otra foto, así el navegador no muestra la vieja
        return '<img' . ($clase ? ' class="' . $clase . '"' : '') . ' src="img/productos/' . rawurlencode($sku) . '.jpg?v=' . filemtime($archivo) . '" alt="' . e($nombre) . '">';
    }
    return $clase ? '' : (imagenCategoria(categoriaDe($sku), 96) ?: '<span aria-hidden="true">🛒</span>');
}

// "2026-10-20 08:12:00" -> "20/10/2026 08:12" · "2026-10-20" -> "20/10/2026"
function fecha(?string $f): string
{
    if ($f === null || $f === '') {
        return '—';
    }
    $t = strtotime($f);
    return strlen($f) > 10 ? date('d/m/Y H:i', $t) : date('d/m/Y', $t);
}

// Días desde hoy hasta la fecha (negativo = ya pasó)
function diasHasta(string $f): int
{
    return (int) round((strtotime($f) - strtotime(date('Y-m-d'))) / 86400);
}

// Etiqueta de color: ok (verde), warn (amarillo), bad (rojo), gris
function tag(string $texto, string $clase): string
{
    return '<span class="tag ' . e($clase) . '">' . e($texto) . '</span>';
}

// "Rosa" + "Quispe Huanca" -> "Rosa Quispe"
function nombreCorto(?string $nombres, ?string $apellidos): string
{
    return $nombres === null ? '—' : trim($nombres . ' ' . explode(' ', (string) $apellidos)[0]);
}

// Aviso guardado en la sesión por el controlador; se muestra una sola vez,
// como un aviso flotante (toast) que se va solo y no empuja el contenido (public/js/app.js)
function aviso(): string
{
    $a = $_SESSION['aviso'] ?? null;
    unset($_SESSION['aviso']);
    if (!$a) {
        return '';
    }
    $tipo = in_array($a['tipo'], ['ok', 'err', 'info'], true) ? $a['tipo'] : 'info';
    $icono = ['ok' => 'ok', 'err' => 'error', 'info' => 'info'][$tipo];
    return '<div class="toast-zona"><div class="toast ' . $tipo . '" role="' . ($tipo === 'err' ? 'alert' : 'status') . '">'
         . icono($icono, 20) . '<span>' . e($a['texto']) . '</span>'
         . '<button type="button" class="toast-x" aria-label="Cerrar aviso">' . icono('cerrar', 16) . '</button></div></div>';
}

/** Íconos SVG (un solo set para el menú, los botones y los avisos) */
const ICONOS = [
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
    'ojo'    => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
    'lapiz'  => '<path d="M4 20h4L19 9l-4-4L4 16z"/><line x1="13" y1="7" x2="17" y2="11"/>',
    'basura' => '<polyline points="4 7 20 7"/><path d="M6 7l1 13h10l1-13"/><path d="M9 7V4h6v3"/>',
    'cerrar' => '<line x1="6" y1="6" x2="18" y2="18"/><line x1="18" y1="6" x2="6" y2="18"/>',
    'menu'   => '<line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="20" y2="17"/>',
    'atras'  => '<polyline points="15 5 8 12 15 19"/>',
    'buscar' => '<circle cx="11" cy="11" r="7"/><line x1="16.5" y1="16.5" x2="21" y2="21"/>',
    'vacio'  => '<path d="M3 8l9-5 9 5v9l-9 5-9-5z"/><polyline points="3 8 12 13 21 8"/><line x1="12" y1="13" x2="12" y2="22"/>',
    'ok'     => '<circle cx="12" cy="12" r="9"/><polyline points="8 12 11 15 16 9"/>',
    'error'  => '<circle cx="12" cy="12" r="9"/><line x1="12" y1="7" x2="12" y2="13"/><line x1="12" y1="16.5" x2="12" y2="16.6"/>',
    'info'   => '<circle cx="12" cy="12" r="9"/><line x1="12" y1="11" x2="12" y2="17"/><line x1="12" y1="7.5" x2="12" y2="7.6"/>',
    'filtro' => '<path d="M3 5h18l-7 8v6l-4 2v-8z"/>',
    'billete'=> '<rect x="2" y="6" width="20" height="12" rx="1"/><circle cx="12" cy="12" r="2.5"/><line x1="6" y1="12" x2="6.01" y2="12"/><line x1="18" y1="12" x2="18.01" y2="12"/>',
];

// <svg> de un ícono del set; decorativo (el texto del botón o el aria-label dice qué hace)
function icono(string $nombre, int $tam = 18): string
{
    return '<svg class="ico" aria-hidden="true" focusable="false" width="' . $tam . '" height="' . $tam . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
         . (ICONOS[$nombre] ?? '') . '</svg>';
}

// Migas de pan: [['Catálogo', '?accion=listar'], ['Arroz Costeño', null]] → « Catálogo › Arroz Costeño »
function migas(array $pasos): string
{
    $h = '<nav class="migas" aria-label="Estás en"><ol>';
    $n = count($pasos);
    foreach ($pasos as $i => [$texto, $url]) {
        $ultimo = $i === $n - 1;
        $h .= '<li>' . ($url && !$ultimo
            ? '<a href="' . e($url) . '">' . ($i === 0 ? icono('atras', 16) : '') . e($texto) . '</a>'
            : '<span' . ($ultimo ? ' aria-current="page"' : '') . '>' . e($texto) . '</span>') . '</li>';
    }
    return $h . '</ol></nav>';
}

// Estado vacío: ícono + título + texto guía (+ un botón opcional)
function vacio(string $titulo, string $guia, string $icono = 'vacio', string $boton = ''): string
{
    return '<div class="vacio">' . icono($icono, 40) . '<p class="vacio-tit">' . e($titulo) . '</p><p class="vacio-guia">' . e($guia) . '</p>' . $boton . '</div>';
}

// Valor escrito antes en el formulario (cuando hay errores no se pierde lo que escribió)
function valor(array $form, string $campo): string
{
    return e((string) ($form[$campo] ?? ''));
}

// Mensaje de error debajo del campo
function error(array $errores, string $campo): string
{
    return isset($errores[$campo]) ? '<span class="err">' . e($errores[$campo]) . '</span>' : '';
}

function claseCampo(array $errores, string $campo): string
{
    return isset($errores[$campo]) ? 'campo mal' : 'campo';
}

// Lote: vencido (rojo), vence en 30 días (amarillo), vigente (verde) o sin vencimiento
function estadoLote(?string $vence): array
{
    if ($vence === null) {
        return ['Sin vencimiento', 'gris'];
    }
    $d = diasHasta($vence);
    return $d < 0 ? ['Vencido', 'bad'] : ($d <= 30 ? ['Vence en ' . $d . ' día' . ($d === 1 ? '' : 's'), 'warn'] : ['Vigente', 'ok']);
}

// Monto en letras, como en los comprobantes: "SON: TREINTA Y TRES CON 90/100 SOLES"
function enLetras(float $n): string
{
    $u = ['', 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE', 'DIEZ', 'ONCE', 'DOCE', 'TRECE',
          'CATORCE', 'QUINCE', 'DIECISÉIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE', 'VEINTE', 'VEINTIUNO', 'VEINTIDÓS',
          'VEINTITRÉS', 'VEINTICUATRO', 'VEINTICINCO', 'VEINTISÉIS', 'VEINTISIETE', 'VEINTIOCHO', 'VEINTINUEVE'];
    $d = ['', '', '', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];
    $c = ['', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS', 'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];
    $hasta999 = function (int $x) use ($u, $d, $c): string {
        if ($x === 100) {
            return 'CIEN';
        }
        $t = $c[intdiv($x, 100)];
        $r = $x % 100;
        if ($r) {
            $t .= ($t ? ' ' : '') . ($r < 30 ? $u[$r] : $d[intdiv($r, 10)] . ($r % 10 ? ' Y ' . $u[$r % 10] : ''));
        }
        return $t;
    };
    $centimos = (int) round($n * 100);
    $ent = intdiv($centimos, 100);
    $mil = intdiv($ent, 1000);
    $resto = $ent % 1000;
    $t = $ent === 0 ? 'CERO' : trim(($mil ? ($mil === 1 ? 'MIL' : $hasta999($mil) . ' MIL') : '') . ' ' . ($resto ? $hasta999($resto) : ''));
    $t = preg_replace(['/VEINTIUNO$/u', '/UNO$/u'], ['VEINTIÚN', 'UN'], $t);
    return 'SON: ' . $t . ' CON ' . str_pad((string) ($centimos % 100), 2, '0', STR_PAD_LEFT) . '/100 SOLES';
}

// Texto del QR de la boleta/factura con el formato de SUNAT: RUC|tipo|serie|número|IGV|total|fecha|tipo doc|doc
function textoQrSunat(array $v): string
{
    $tipoDoc = match ($v['TIPO_DOCUMENTO'] ?? null) { 'RUC' => '6', 'DNI' => '1', 'CE' => '4', default => '-' };
    return implode('|', ['20601234567', $v['TIPO_COMPROBANTE'] === 'FACTURA' ? '01' : '03', $v['SERIE'], $v['NUMERO'],
        number_format((float) $v['IGV'], 2, '.', ''), number_format((float) $v['TOTAL'], 2, '.', ''),
        substr((string) $v['FECHA_HORA'], 0, 10), $tipoDoc, $v['NRO_DOCUMENTO'] ?? '-', '']);
}
