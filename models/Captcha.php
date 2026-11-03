<?php
declare(strict_types=1);

/**
 * "Verifica que eres humano": el logo de Mass aparece girado en un ángulo al azar
 * y la persona lo endereza con una barra. El ángulo solo lo sabe el servidor (sesión)
 * y la imagen girada la arma PHP, así un programa no puede leer la respuesta en el HTML.
 * Cada vez que se muestra el login sale un ángulo nuevo.
 */
class Captcha
{
    private const TOLERANCIA = 12;          // grados de error permitidos
    private const LADO = 240;               // tamaño de la imagen en píxeles
    private const LOGO = __DIR__ . '/../public/img/mass-logo.png';

    public static function nuevo(): void
    {
        // Nunca casi derecho: entre 40° y 320°. Sin gd la imagen no se puede girar: va derecha.
        $_SESSION['captcha'] = self::hayGd() ? random_int(40, 320) : 0;
    }

    // ¿El giro que hizo la persona deja el logo derecho? Sirve una sola vez.
    public static function verificar(string $giro): bool
    {
        $angulo = $_SESSION['captcha'] ?? null;
        unset($_SESSION['captcha']);
        if ($angulo === null || preg_match('/^\d{1,3}$/', $giro) !== 1) {
            return false;
        }
        $resto = ($angulo + (int) $giro) % 360;
        return min($resto, 360 - $resto) <= self::TOLERANCIA;
    }

    // Dibuja el logo girado (necesita la extensión gd de PHP, que Laragon trae activada)
    public static function imagen(): void
    {
        $angulo = $_SESSION['captcha'] ?? 0;
        header('Content-Type: image/png');
        header('Cache-Control: no-store');
        if (!function_exists('imagecreatefrompng')) {
            readfile(self::LOGO);   // sin gd no se puede girar: se muestra derecho
            return;
        }
        $lado = self::LADO;
        $lienzo = imagecreatetruecolor($lado, $lado);
        $amarillo = imagecolorallocate($lienzo, 254, 206, 0);
        imagefill($lienzo, 0, 0, $amarillo);

        $logo = imagecreatefrompng(self::LOGO);
        $ancho = (int) round($lado * 0.86);
        $alto = (int) round(imagesy($logo) * $ancho / imagesx($logo));
        imagecopyresampled($lienzo, $logo, (int) (($lado - $ancho) / 2), (int) (($lado - $alto) / 2), 0, 0, $ancho, $alto, imagesx($logo), imagesy($logo));

        // imagerotate gira en contra de las agujas del reloj: con -ángulo queda girado a la derecha
        $girada = imagerotate($lienzo, -$angulo, $amarillo);
        $final = imagecreatetruecolor($lado, $lado);
        imagecopy($final, $girada, 0, 0, (int) ((imagesx($girada) - $lado) / 2), (int) ((imagesy($girada) - $lado) / 2), $lado, $lado);
        imagepng($final);
    }

    public static function hayGd(): bool
    {
        return function_exists('imagecreatefrompng');
    }
}
