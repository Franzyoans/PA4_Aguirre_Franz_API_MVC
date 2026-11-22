<?php

/**
 * Rutas de la API. Variables disponibles desde public/index.php: $router y $db.
 *
 * Cada recurso expone:
 *   GET    /api/{recurso}        -> index   (listar)
 *   GET    /api/{recurso}/{id}   -> show    (consultar por id)
 *   POST   /api/{recurso}        -> store   (crear)
 *   PUT    /api/{recurso}/{id}   -> update  (actualizar)
 *   PATCH  /api/{recurso}/{id}   -> patch   (actualización parcial)
 *   DELETE /api/{recurso}/{id}   -> destroy (eliminar)
 */

use App\Controllers\CategoriaController;
use App\Controllers\ClienteController;
use App\Controllers\DetallePedidoController;
use App\Controllers\PedidoController;
use App\Controllers\ProductoController;
use App\Controllers\ProveedorController;
use App\Controllers\ReporteController;
use App\Controllers\UsuarioController;
use Core\Response;

$recursos = [
    'categorias'     => CategoriaController::class,
    'proveedores'    => ProveedorController::class,
    'productos'      => ProductoController::class,
    'clientes'       => ClienteController::class,
    'usuarios'       => UsuarioController::class,
    'pedidos'        => PedidoController::class,
    'detalle-pedido' => DetallePedidoController::class,
];

foreach ($recursos as $ruta => $clase) {
    $c = new $clase($db);

    $router->get("/api/{$ruta}",          fn()    => $c->index());
    $router->get("/api/{$ruta}/{id}",     fn($id) => $c->show($id));
    $router->post("/api/{$ruta}",         fn()    => $c->store());
    $router->put("/api/{$ruta}/{id}",     fn($id) => $c->update($id));
    $router->patch("/api/{$ruta}/{id}",   fn($id) => $c->patch($id));
    $router->delete("/api/{$ruta}/{id}",  fn($id) => $c->destroy($id));
}

// Reportes (procedimiento almacenado y vista del script SQL)
$reportes = new ReporteController($db);
$router->get('/api/reportes/ventas-por-categoria', fn() => $reportes->ventasPorCategoria());
$router->get('/api/reportes/pedidos-resumen',      fn() => $reportes->pedidosResumen());

// Información general de la API
$info = fn() => Response::success([
    'api'       => 'YoaPoint API REST (PHP + MVC + MySQL)',
    'recursos'  => array_map(fn($r) => "/api/{$r}", array_keys($recursos)),
    'reportes'  => ['/api/reportes/ventas-por-categoria', '/api/reportes/pedidos-resumen'],
], 'API YoaPoint en funcionamiento');

$router->get('/', $info);
$router->get('/api', $info);
