<?php

namespace Wingman\Core\Bases;

use mysqli;
use Wingman\Core\App\Enums\Builtins\HttpMethod;
use Wingman\Core\App\Route;

class BaseRouter
{
    public ?mysqli $db;
    public string $prefix = '';
    public array $routes = [];

    public function __construct(?mysqli $db = null)
    {
        $this->db = $db;
    }

    private function addRoute(HttpMethod $httpMethod, string $path, string $controller, string $method): Route
    {
        $key = $httpMethod->value;
        $fullPath = rtrim($this->prefix . $path, '/') ?: '/';
        $route = new Route($httpMethod, $fullPath, $controller, $method, $this->db);
        $this->routes[$key][] = $route;
        return $route;
    }

    protected function setPrefix(string $prefix = ''): void
    {
        $this->prefix = $prefix;
    }

    protected function get(string $path, string $controller, string $method): Route
    {
        return $this->addRoute(HttpMethod::GET, $path, $controller, $method);
    }

    protected function post(string $path, string $controller, string $method): Route
    {
        return $this->addRoute(HttpMethod::POST, $path, $controller, $method);
    }

    protected function put(string $path, string $controller, string $method): Route
    {
        return $this->addRoute(HttpMethod::PUT, $path, $controller, $method);
    }

    protected function delete(string $path, string $controller, string $method): Route
    {
        return $this->addRoute(HttpMethod::DELETE, $path, $controller, $method);
    }

    protected function patch(string $path, string $controller, string $method): Route
    {
        return $this->addRoute(HttpMethod::PATCH, $path, $controller, $method);
    }

    protected function withLimitToAll(int $maxRequests, int $perSeconds): void
    {
        foreach ($this->routes as $method => $routes)
        {
            foreach ($routes as $index => $_route)
            {
                $this->routes[$method][$index]->withLimit($maxRequests, $perSeconds);
            }
        }
    }

    public function build(): void
    {
        
    }
}