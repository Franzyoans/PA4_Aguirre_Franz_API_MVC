<?php

namespace Config;

use PDO;
use PDOException;

/**
 * Conexión a MySQL/MariaDB con PDO.
 * Los datos de conexión se leen del archivo .env 
 */
class Database
{
    public function connect(): PDO
    {
        $host = Env::get('DB_HOST', 'localhost');
        $port = Env::get('DB_PORT', '3306');
        $name = Env::get('DB_NAME', 'yoapoint');
        $user = Env::get('DB_USER', 'root');
        $pass = Env::get('DB_PASS', '');

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

        try {
            return new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // Se registra el detalle real, pero al cliente solo se informa un mensaje genérico
            registrarError($e);
            throw new PDOException('No se pudo conectar con la base de datos.');
        }
    }
}
