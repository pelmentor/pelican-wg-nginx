<?php

class Router {
    private array $routes = [];

    public function get(string $path, callable|array $handler): void {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, callable|array $handler): void {
        $this->routes['POST'][$path] = $handler;
    }

    public function dispatch(string $method, string $uri): void {
        // Strip query string
        $uri = strtok($uri, '?');
        // Remove trailing slash (except root)
        if ($uri !== '/') $uri = rtrim($uri, '/');

        $handler = $this->routes[$method][$uri] ?? null;

        if (!$handler) {
            http_response_code(404);
            echo json_encode(['error' => 'Not found']);
            return;
        }

        if (is_array($handler)) {
            [$class, $method] = $handler;
            $instance = new $class();
            $instance->$method();
        } else {
            $handler();
        }
    }
}
