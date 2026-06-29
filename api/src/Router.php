<?php
namespace App;

// Router minimalista: registra rutas con patrones tipo /trabajos/{id}
// y despacha al callback con los parametros capturados.
final class Router
{
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): void
    {
        $this->routes[] = [strtoupper($method), $pattern, $handler];
    }

    public function get(string $p, callable $h): void    { $this->add('GET', $p, $h); }
    public function post(string $p, callable $h): void   { $this->add('POST', $p, $h); }
    public function patch(string $p, callable $h): void  { $this->add('PATCH', $p, $h); }
    public function delete(string $p, callable $h): void { $this->add('DELETE', $p, $h); }

    public function dispatch(string $method, string $path): void
    {
        $path = '/' . trim($path, '/');
        foreach ($this->routes as [$m, $pattern, $handler]) {
            if ($m !== strtoupper($method)) {
                continue;
            }
            $regex = '#^' . preg_replace('#\{[a-zA-Z_]+\}#', '([^/]+)', $pattern) . '$#';
            if (preg_match($regex, $path, $matches)) {
                array_shift($matches);
                $args = array_map(static fn ($v) => ctype_digit($v) ? (int) $v : $v, $matches);
                $handler(...$args);
                return;
            }
        }
        Http::error('Ruta no encontrada', 404);
    }
}
