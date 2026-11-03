<?php
declare(strict_types=1);

/** Página del asistente con IA y la llamada que responde cada pregunta */
class AsistenteController extends Controlador
{
    private const MAX_HISTORIAL = 10;   // últimos mensajes que se recuerdan en la conversación

    public function __construct(Usuario $usuario, array $tienda, private ?AsistenteIA $ia)
    {
        parent::__construct($usuario, $tienda);
    }

    public function index(): void
    {
        $this->vista('asistente/index', ['configurado' => $this->ia !== null, 'historial' => $_SESSION['chat'] ?? []]);
    }

    // Recibe la pregunta (POST con csrf) y devuelve JSON {respuesta} o {error}
    public function preguntar(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $pregunta = self::texto('pregunta', 500);
        if ($this->ia === null) {
            echo json_encode(['error' => 'Falta configurar la clave de Gemini en config/ia.php.']);
            return;
        }
        if (mb_strlen($pregunta) < 2) {
            echo json_encode(['error' => 'Escribe tu pregunta.']);
            return;
        }
        try {
            $respuesta = $this->ia->preguntar($pregunta, $_SESSION['chat'] ?? []);
        } catch (RuntimeException $e) {
            echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            return;
        }
        $chat = $_SESSION['chat'] ?? [];
        $chat[] = ['rol' => 'user', 'texto' => $pregunta];
        $chat[] = ['rol' => 'model', 'texto' => $respuesta];
        $_SESSION['chat'] = array_slice($chat, -self::MAX_HISTORIAL);
        echo json_encode(['respuesta' => $respuesta], JSON_UNESCAPED_UNICODE);
    }

    public function borrar(): void
    {
        unset($_SESSION['chat']);
        $this->redirigir('asistente');
    }
}
