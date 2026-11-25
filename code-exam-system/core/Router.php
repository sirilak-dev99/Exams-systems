<?php

declare(strict_types=1);

final class Router
{
    private array $routes = [];

    public function get(string $path, array $handler): void
    {
        $this->routes['GET'][] = [
            'pattern' => $this->compilePattern($this->norm($path)),
            'handler' => $handler,
        ];
    }

    public function post(string $path, array $handler): void
    {
        $this->routes['POST'][] = [
            'pattern' => $this->compilePattern($this->norm($path)),
            'handler' => $handler,
        ];
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $base = parse_url((string)env('APP_URL', ''), PHP_URL_PATH) ?: '';
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base)) ?: '/';
        }

        $normPath = $this->norm($path);
        $methodUpper = strtoupper($method);
        $candidates = $this->routes[$methodUpper] ?? [];

        foreach ($candidates as $route) {
            if (preg_match($route['pattern'], $normPath, $matches)) {
                $params = [];
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $params[] = $value;
                    }
                }

                [$class, $action] = $route['handler'];
                (new $class())->$action(...$params);
                return;
            }
        }

        http_response_code(404);
        require dirname(__DIR__) . '/app/Views/errors/404.php';
    }

    private function compilePattern(string $normalizedPath): string
    {
        $regex = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^/]+)', $normalizedPath);
        return '#^' . $regex . '$#';
    }

    private function norm(string $path): string
    {
        $p = '/' . trim($path, '/');
        return $p === '//' ? '/' : $p;
    }
}