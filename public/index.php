<?php
declare(strict_types=1);

// Front controller: todas las páginas pasan por aquí (?accion=...)
date_default_timezone_set('America/Lima');

require __DIR__ . '/../config/conexion.php';
foreach (['Producto', 'ProductoRepository', 'UsuarioRepository', 'Repositorio', 'CajaRepository', 'VentaRepository',
          'LoteRepository', 'MermaRepository', 'IncidenteRepository', 'TiendaRepository', 'ReporteRepository',
          'HistorialRepository', 'NotificacionRepository', 'Correo', 'Captcha'] as $modelo) {
    require_once __DIR__ . '/../models/' . $modelo . '.php';
}
foreach (['Controlador', 'AuthController', 'ProductoController', 'CajaController', 'VentaController',
          'InventarioController', 'TiendaController', 'AdminController'] as $controlador) {
    require_once __DIR__ . '/../controllers/' . $controlador . '.php';
}
require_once __DIR__ . '/../views/partials/ayudas.php';

// Sesión con cookie protegida: JavaScript no la puede leer (httponly)
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

// Si MySQL no responde, se muestra un mensaje simple (sin datos internos del error)
try {
    $pdo = Conexion::obtener();
} catch (PDOException $e) {
    http_response_code(500);
    error_log('Conexión a MySQL: ' . $e->getMessage());
    echo 'No se pudo conectar a la base de datos. Revisa que Laragon esté encendido (Start All) y el puerto de MySQL.';
    // Solo al probar con "php -S" en tu PC se muestra el detalle, para saber qué falló
    if (PHP_SAPI === 'cli-server') {
        echo '<br><small>Detalle: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</small>';
    }
    exit;
}

$accion = (string) ($_GET['accion'] ?? 'listar');
$esPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$usuarios = new UsuarioRepository($pdo);
$auth = new AuthController($usuarios);

// Todo formulario (POST) debe traer el token CSRF de la sesión
if ($esPost && $accion !== 'login' && !AuthController::tokenValido()) {
    http_response_code(400);
    echo 'La página expiró. <a href="?accion=listar">Vuelve a intentarlo</a>.';
    exit;
}

// Rutas del ingreso (todavía sin sesión iniciada)
$publicas = [
    'login'                   => fn() => $esPost ? $auth->login() : $auth->mostrarLogin(),
    'verificar'               => fn() => $esPost ? $auth->verificarCodigo() : $auth->mostrarCodigo(),
    'reenviar'                => fn() => $auth->reenviarCodigo(),
    'cancelar_ingreso'        => fn() => $auth->cancelarIngreso(),
    'captcha'                 => fn() => Captcha::imagen(),
    'logout'                  => fn() => $auth->logout(),
];
if (isset($publicas[$accion])) {
    $publicas[$accion]();
    exit;
}

// Todo lo demás exige haber iniciado sesión. El rol se vuelve a leer de la base en cada página.
$sesion = AuthController::usuarioActual();
$u = $sesion ? $usuarios->buscarPorId((int) $sesion['id']) : null;
if ($u === null) {
    unset($_SESSION['usuario']);
    header('Location: ?accion=login');
    exit;
}

// Historial de actividades: quién agregó, cambió precios, anuló, registró mermas...
$historial = new HistorialRepository($pdo);
Historial::usar($historial);

$productos = new ProductoRepository($pdo);
$lotes = new LoteRepository($pdo);
$cajas = new CajaRepository($pdo);
$tiendas = new TiendaRepository($pdo);
// La franja de arriba muestra la tienda donde el cajero tiene su caja abierta (o la suya)
$cajaAbierta = $u->puedeAbrirCaja() ? $cajas->sesionAbiertaDe($u->getIdEmpleado()) : null;
$tiendaActual = $tiendas->tienda($cajaAbierta ? (int) $cajaAbierta['ID_TIENDA'] : $u->getIdTienda()) ?? [];

// El cajero primero abre su caja: hasta abrirla solo ve «Abre tu caja» (lo demás aparece después)
define('CAJERO_SIN_CAJA', $u->puedeAbrirCaja() && $cajaAbierta === null);
if (CAJERO_SIN_CAJA && !in_array($accion, ['caja_inicio', 'caja_abrir'], true)) {
    header('Location: ?accion=caja_inicio');
    exit;
}

$catalogo   = new ProductoController($u, $tiendaActual, $productos, $lotes);
// Faltantes de caja: el Administrador los ve en la campana de arriba (y le llegan por correo al cerrar la caja)
$notificaciones = new NotificacionRepository($pdo);
define('FALTANTES_NUEVOS', $u->puedeVerDineroCajas() ? $notificaciones->nuevos($u->getIdEmpleado()) : null);

$caja       = new CajaController($u, $tiendaActual, $cajas, $tiendas, $notificaciones);
$ventas     = new VentaController($u, $tiendaActual, new VentaRepository($pdo), $cajas, $productos);
$inventario = new InventarioController($u, $tiendaActual, $lotes, new MermaRepository($pdo), $productos, $tiendas);
$reportes   = new ReporteRepository($pdo);
$tienda     = new TiendaController($u, $tiendaActual, $tiendas, new IncidenteRepository($pdo), $reportes, $productos, $cajas);
$admin      = new AdminController($u, $tiendaActual, $reportes, $historial, $notificaciones);

// [acción => [qué hacer con GET, qué hacer con POST]]
$rutas = [
    'listar'       => [fn() => $catalogo->listar(), null],
    'ver'          => [function () use ($catalogo) {
        // Solo se acepta un SKU con el formato de CODIGO_SKU (letras, números y guion)
        $sku = (string) ($_GET['sku'] ?? '');
        preg_match('/^[A-Z0-9-]{1,20}$/', $sku) === 1 ? $catalogo->ver($sku) : $catalogo->listar();
    }, null],
    'agregar'      => [fn() => $catalogo->formularioAgregar(), fn() => $catalogo->agregar()],
    'editar'       => [fn() => $catalogo->formularioEditar(), fn() => $catalogo->editar()],
    'baja'         => [fn() => $catalogo->confirmarBaja(), fn() => $catalogo->cambiarEstado('I')],
    'reactivar'    => [null, fn() => $catalogo->cambiarEstado('A')],
    'pdf'          => [fn() => $catalogo->pdf(), null],

    'caja'         => [fn() => $caja->index(), null],
    'caja_abrir'   => [null, fn() => $caja->abrir()],
    'caja_inicio'  => [fn() => $caja->inicio(), null],
    'caja_cerrar'  => [null, fn() => $caja->cerrar()],

    'venta'        => [fn() => $ventas->nueva(), fn() => $ventas->actualizar()],
    'cliente'      => [fn() => $ventas->buscarCliente(), null],
    'cobro'        => [fn() => $ventas->cobro(), fn() => $ventas->confirmarPago()],
    'cancelar_cobro' => [null, fn() => $ventas->cancelarCobro()],
    'comprobante'  => [fn() => $ventas->comprobante(), null],
    'ventas'       => [fn() => $ventas->lista(), null],
    'anular'       => [null, fn() => $ventas->anular()],

    'lotes'        => [fn() => $inventario->lotes(), fn() => $inventario->recibirLote()],
    'mermas'       => [fn() => $inventario->mermas(), fn() => $inventario->registrarMerma()],
    'autorizar_merma' => [null, fn() => $inventario->autorizarMerma()],

    'incidentes'   => [fn() => $tienda->incidentes(), fn() => $tienda->reportarIncidente()],
    'avanzar_incidente' => [null, fn() => $tienda->avanzarIncidente()],
    'tiendas'      => [fn() => $tienda->tiendas(), null],
    'cuadre'       => [fn() => $tienda->cuadreStock(), null],
    'reportes'     => [fn() => $tienda->reportes(), null],

    // Funciones del Administrador
    'alertas'      => [fn() => $admin->alertas(), null],
    'historial'    => [fn() => $admin->historial(), null],
    'faltantes'    => [fn() => $admin->faltantes(), null],
    'faltantes_n'  => [fn() => $admin->faltantesNuevos(), null],   // la campana pregunta cada 30 s si hay algo nuevo
];

$ruta = $rutas[$accion][$esPost ? 1 : 0] ?? null;
if ($ruta === null) {
    http_response_code(404);
    echo 'Página no encontrada. <a href="?accion=listar">Ir al catálogo</a>';
    exit;
}
$ruta();
