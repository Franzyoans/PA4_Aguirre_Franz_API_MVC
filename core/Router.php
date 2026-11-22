<?php

namespace Core;

/**
 * Router sencillo: registra rutas (GET, POST, PUT, PATCH, DELETE) y ejecuta la acción.
 * Soporta el parámetro dinámico {id}.
 */
class Router
{
    private array $routes = [];

    public function get(string $route, callable $action): void    { $this->add('GET', $route, $action); }
    public function post(string $route, callable $action): void   { $this->add('POST', $route, $action); }
    public function put(string $route, callable $action): void    { $this->add('PUT', $route, $action); }
    public function patch(string $route, callable $action): void  { $this->add('PATCH', $route, $action); }
    public function delete(string $route, callable $action): void { $this->add('DELETE', $route, $action); }

    private function add(string $method, string $route, callable $action): void
    {
        $this->routes[] = [
            'method' => $method,
            'route'  => $route === '/' ? '/' : rtrim($route, '/'),
            'action' => $action,
        ];
    }

    public function dispatch(string $method, string $uri): void
    {
        $method = strtoupper($method);
        $uri = rawurldecode(parse_url($uri, PHP_URL_PATH) ?? '/');
        $uri = $uri === '/' ? '/' : rtrim('/' . ltrim($uri, '/'), '/');

        $rutaExiste = false;

        foreach ($this->routes as $r) {
            $patron = str_replace('\{id\}', '([^/]+)', preg_quote($r['route'], '#'));

            if (!preg_match('#^' . $patron . '$#', $uri, $m)) {
                continue;
            }

            $rutaExiste = true;

            if ($r['method'] !== $method) {
                continue;
            }

            array_shift($m);

            // El id debe ser un entero positivo
            foreach ($m as $valor) {
                if (!ctype_digit($valor) || (int) $valor < 1) {
                    Response::error('El id debe ser un número entero positivo.', 400);
                }
            }

            call_user_func_array($r['action'], array_map('intval', $m));
            return;
        }

        if ($rutaExiste) {
            Response::error('Método HTTP no permitido para esta ruta.', 405);
        }

        Response::error('Ruta no encontrada.', 404);
    }
}
