<?php
declare(strict_types=1);

/**
 * Lo que comparten los repositorios: la conexión PDO y cómo sacar el siguiente ID.
 * En schema.sql los ID no son AUTO_INCREMENT, por eso se calcula MAX + 1
 * dentro de la misma transacción del INSERT.
 */
abstract class Repositorio
{
    public function __construct(protected PDO $pdo) {}

    // $tabla y $columna salen siempre del código (nunca del usuario), por eso se pueden unir al SQL
    protected function siguienteId(string $tabla, string $columna): int
    {
        return (int) $this->pdo->query("SELECT COALESCE(MAX($columna), 0) + 1 FROM $tabla")->fetchColumn();
    }

    protected static function ahora(): string
    {
        return date('Y-m-d H:i:s');
    }

    // Montos de dinero con 2 decimales exactos, como DECIMAL(12,2) de MySQL
    protected static function dinero(float $monto): string
    {
        return number_format(round($monto, 2), 2, '.', '');
    }

    // Ejecuta $trabajo en una transacción: o se guarda todo o no se guarda nada
    protected function enTransaccion(callable $trabajo): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $r = $trabajo();
            $this->pdo->commit();
            return $r;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
