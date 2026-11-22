<?php
/**
 * Front controller: único punto de entrada de la API.
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Middleware\CorsMiddleware;
use Config\Database;
use Core\Router;

CorsMiddleware::handle();

// Conexión a la base de datos (si falla, el manejador global responde 500)
$db = (new Database())->connect();

$router = new Router();
require_once __DIR__ . '/../routes/api.php';

// Obtener la URI quitando la carpeta del proyecto (funciona con /yoapoint_api/api/... y /yoapoint_api/public/api/...)
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$carpetaPublic = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$carpetaProyecto = rtrim(str_replace('\\', '/', dirname($carpetaPublic)), '/');

foreach ([$carpetaPublic, $carpetaProyecto] as $base) {
    if ($base !== '' && str_starts_with($uri, $base . '/')) {
        $uri = substr($uri, strlen($base));
        break;
    }
    if ($base !== '' && $uri === $base) {
        $uri = '/';
        break;
    }
}

$router->dispatch($_SERVER['REQUEST_METHOD'], $uri === '' ? '/' : $uri);
