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

// Aviso guardado en la sesión por el controlador; se muestra una sola vez
function aviso(): string
{
    $a = $_SESSION['aviso'] ?? null;
    unset($_SESSION['aviso']);
    return $a ? '<div class="msg ' . e($a['tipo']) . '" role="status">' . e($a['texto']) . '</div>' : '';
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
