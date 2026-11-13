<?php
declare(strict_types=1);
final class Router {
 private array $routes=[];
 public function get(string $path, array $handler): void { $this->routes['GET'][$this->norm($path)]=$handler; }
 public function post(string $path, array $handler): void { $this->routes['POST'][$this->norm($path)]=$handler; }
 public function dispatch(string $method,string $uri): void {
  $path=parse_url($uri,PHP_URL_PATH) ?: '/';
  $base=parse_url((string)env('APP_URL',''),PHP_URL_PATH) ?: '';
  if ($base!=='' && str_starts_with($path,$base)) $path=substr($path,strlen($base)) ?: '/';
  $handler=$this->routes[strtoupper($method)][$this->norm($path)] ?? null;
  if (!$handler) { http_response_code(404); require dirname(__DIR__).'/app/Views/errors/404.php'; return; }
  [$class,$action]=$handler; (new $class())->$action();
 }
 private function norm(string $path): string { $p='/'.trim($path,'/'); return $p==='//'?'/':$p; }
}
