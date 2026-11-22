<?php
/**
 * Arranque de la aplicación: autoload, variables de entorno y manejo global de errores.
 */

declare(strict_types=1);

define('BASE_PATH', __DIR__);

// Autoload PSR-4 sencillo (sin necesidad de Composer)
spl_autoload_register(function (string $class): void {
    $map = [
        'App\\'    => BASE_PATH . '/app/',
        'Config\\' => BASE_PATH . '/config/',
        'Core\\'   => BASE_PATH . '/core/',
    ];
    foreach ($map as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require_once $file;
            }
            return;
        }
    }
});

use Config\Env;
use Core\Response;

Env::load(BASE_PATH . '/.env');

// Nunca mostrar errores de PHP al cliente; se registran en storage/logs/error.log
ini_set('display_errors', '0');
error_reporting(E_ALL);
date_default_timezone_set('America/Lima');

function registrarError(Throwable $e): void
{
    $linea = sprintf(
        "[%s] %s: %s en %s:%d\n",
        date('Y-m-d H:i:s'),
        get_class($e),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    );
    @error_log($linea, 3, BASE_PATH . '/storage/logs/error.log');
}

// Convierte warnings/notices en excepciones para controlarlos
set_error_handler(function (int $no, string $msg, string $file, int $line): bool {
    if (!(error_reporting() & $no)) {
        return false;
    }
    throw new ErrorException($msg, 0, $no, $file, $line);
});

// Cualquier excepción no controlada -> 500 genérico (sin datos sensibles)
set_exception_handler(function (Throwable $e): void {
    registrarError($e);
    Response::error('Error interno del servidor. Intente nuevamente más tarde.', 500);
});
