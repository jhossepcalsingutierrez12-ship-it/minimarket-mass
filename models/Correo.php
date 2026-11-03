<?php
declare(strict_types=1);

/**
 * Envía correos por SMTP (por ejemplo Gmail) sin librerías: abre la conexión segura,
 * inicia sesión con la contraseña de aplicación y manda el mensaje.
 * Los datos de la cuenta van en config/correo.php (ese archivo NO se sube a GitHub).
 */
class Correo
{
    public function __construct(private array $cfg) {}

    // Devuelve null si no hay config/correo.php: entonces el código se muestra en pantalla
    public static function desdeConfig(): ?self
    {
        $archivo = __DIR__ . '/../config/correo.php';
        if (!is_file($archivo)) {
            return null;
        }
        $cfg = require $archivo;
        return is_array($cfg) && !empty($cfg['usuario']) && !empty($cfg['clave']) ? new self($cfg) : null;
    }

    public function enviar(string $para, string $asunto, string $texto, string $html): void
    {
        $host = $this->cfg['host'] ?? 'smtp.gmail.com';
        $puerto = (int) ($this->cfg['puerto'] ?? 465);
        $seguridad = $this->cfg['seguridad'] ?? 'ssl';          // ssl (465) · tls (587) · ninguna (solo pruebas locales)
        $contexto = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $destino = ($seguridad === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $puerto;

        $s = @stream_socket_client($destino, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $contexto);
        if ($s === false) {
            throw new RuntimeException("No se pudo conectar al servidor de correo ($errstr).");
        }
        stream_set_timeout($s, 15);
        try {
            $this->esperar($s, 220);
            $this->orden($s, 'EHLO minimarket-mass', 250);
            if ($seguridad === 'tls') {
                $this->orden($s, 'STARTTLS', 220);
                if (!stream_socket_enable_crypto($s, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('No se pudo activar TLS.');
                }
                $this->orden($s, 'EHLO minimarket-mass', 250);
            }
            $this->orden($s, 'AUTH LOGIN', 334);
            $this->orden($s, base64_encode($this->cfg['usuario']), 334);
            $this->orden($s, base64_encode(str_replace(' ', '', $this->cfg['clave'])), 235);
            $de = $this->cfg['usuario'];
            $this->orden($s, "MAIL FROM:<$de>", 250);
            $this->orden($s, "RCPT TO:<$para>", 250);
            $this->orden($s, 'DATA', 354);

            $limite = 'mass' . bin2hex(random_bytes(8));
            $nombre = '=?UTF-8?B?' . base64_encode($this->cfg['nombre'] ?? 'Minimarket Mass') . '?=';
            $mensaje = implode("\r\n", [
                "From: $nombre <$de>",
                "To: <$para>",
                'Subject: =?UTF-8?B?' . base64_encode($asunto) . '?=',
                'Date: ' . date('r'),
                'MIME-Version: 1.0',
                "Content-Type: multipart/alternative; boundary=\"$limite\"",
                '',
                "--$limite",
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: base64',
                '',
                chunk_split(base64_encode($texto)),
                "--$limite",
                'Content-Type: text/html; charset=UTF-8',
                'Content-Transfer-Encoding: base64',
                '',
                chunk_split(base64_encode($html)),
                "--$limite--",
                '.',
            ]);
            $this->orden($s, $mensaje, 250);
            fwrite($s, "QUIT\r\n");
        } finally {
            fclose($s);
        }
    }

    private function orden($s, string $linea, int $esperado): void
    {
        fwrite($s, $linea . "\r\n");
        $this->esperar($s, $esperado);
    }

    // Lee la respuesta del servidor (puede tener varias líneas "250-...") y revisa el código
    private function esperar($s, int $esperado): void
    {
        $resp = '';
        while (($l = fgets($s, 1024)) !== false) {
            $resp .= $l;
            if (strlen($l) < 4 || $l[3] === ' ') {
                break;
            }
        }
        if ((int) substr($resp, 0, 3) !== $esperado) {
            $detalle = trim($resp) ?: 'sin respuesta';
            throw new RuntimeException(str_starts_with($detalle, '535')
                ? 'Gmail rechazó el usuario o la contraseña de aplicación (revisa config/correo.php).'
                : "El servidor de correo respondió: $detalle");
        }
    }
}
