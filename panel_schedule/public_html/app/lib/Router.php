<?php
declare(strict_types=1);

namespace App\Lib;

class Router
{
    private array $routes = [];

    public function get(string $path, $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    private function add(string $method, string $path, $handler): void
    {
        $pattern = preg_replace('#\{[^/]+\}#', '([^/]+)', $path);
        $pattern = '#^' . $pattern . '$#';
        $this->routes[] = [$method, $path, $pattern, $handler];
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        if ($base && strpos($path, $base) === 0) {
            $path = substr($path, strlen($base));
            if ($path === '') {
                $path = '/';
            }
        }

        foreach ($this->routes as [$routeMethod, $routePath, $pattern, $handler]) {
            if ($routeMethod !== $method) {
                continue;
            }
            if (preg_match($pattern, $path, $matches)) {
                array_shift($matches);
                if (is_callable($handler)) {
                    call_user_func_array($handler, $matches);
                    return;
                }
                if (is_array($handler)) {
                    [$class, $methodName] = $handler;
                    $controller = new $class();
                    call_user_func_array([$controller, $methodName], $matches);
                    return;
                }
            }
        }

        http_response_code(404);
        echo '404 Not Found';
    }
}
