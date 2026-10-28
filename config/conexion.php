<?php
declare(strict_types=1);

/**
 * Conexión única a MySQL de Laragon con PDO.
 * Base: minimarket_mass (creada con database/schema.sql y database/datos.sql).
 * En el aula MySQL de Laragon usa el puerto 3307 (ver README.md).
 */
class Conexion
{
    private const HOST    = '127.0.0.1';
    private const PUERTO  = 3307;
    private const BASE    = 'minimarket_mass';
    private const USUARIO = 'root';
    private const CLAVE   = '';          // Laragon trae root sin contraseña

    private static ?PDO $pdo = null;

    // Devuelve siempre la misma conexión (se crea solo la primera vez)
    public static function obtener(): PDO
    {
        if (self::$pdo === null) {
            // Si la PC usa otro puerto, se indica con la variable DB_PORT (ej. 3306 en casa)
            $puerto = getenv('DB_PORT') ?: self::PUERTO;
            $dsn = 'mysql:host=' . self::HOST . ';port=' . $puerto
                 . ';dbname=' . self::BASE . ';charset=utf8mb4';

            self::$pdo = new PDO($dsn, self::USUARIO, self::CLAVE, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // los errores lanzan excepción
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // filas como ['COLUMNA' => valor]
                PDO::ATTR_EMULATE_PREPARES   => false,                  // consultas preparadas reales
            ]);
        }
        return self::$pdo;
    }
}