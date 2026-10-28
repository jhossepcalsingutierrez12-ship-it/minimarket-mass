<?php
declare(strict_types=1);

require __DIR__ . '/../config/conexion.php';
require __DIR__ . '/../models/Producto.php';
require __DIR__ . '/../models/ProductoRepository.php';
require __DIR__ . '/../models/UsuarioRepository.php';
require __DIR__ . '/../controllers/ProductoController.php';
require __DIR__ . '/../controllers/AuthController.php';

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

$accion = $_GET['accion'] ?? 'listar';
$auth = new AuthController(new UsuarioRepository($pdo));

// Rutas que no necesitan haber iniciado sesión
if ($accion === 'login') {
    $_SERVER['REQUEST_METHOD'] === 'POST' ? $auth->login() : $auth->mostrarLogin();
    exit;
}
if ($accion === 'logout') {
    $auth->logout();
    exit;
}

// Todo lo demás exige haber iniciado sesión
if (AuthController::usuarioActual() === null) {
    header('Location: ?accion=login');
    exit;
}

$controlador = new ProductoController(new ProductoRepository($pdo));

switch ($accion) {
    case 'listar':
        $controlador->listar();
        break;
    case 'ver':
        // Solo se acepta un SKU con el formato de CODIGO_SKU (letras, números y guion)
        $sku = (string) ($_GET['sku'] ?? '');
        if (preg_match('/^[A-Z0-9-]{1,20}$/', $sku) !== 1) {
            http_response_code(404);
            echo 'Página no encontrada';
            break;
        }
        $controlador->ver($sku);
        break;
    default:
        http_response_code(404);
        echo 'Página no encontrada';
}