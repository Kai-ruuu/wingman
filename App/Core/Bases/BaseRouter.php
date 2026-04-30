<?php

namespace Wingman\Core\Bases;

use mysqli;
use Wingman\Core\App\Enums\Builtins\HttpMethod;
use Wingman\Core\App\Route;

/**
 * Base class for all routers.
 *
 * Provides helper methods for registering routes against HTTP methods,
 * setting a shared path prefix, and applying rate limiting across all routes.
 * Subclasses implement describe() to define their specific routes.
 */
class BaseRouter
{
    /** @var mysqli|null Database connection passed to each route for use in controllers. */
    public ?mysqli $db;

    /** @var string Path prefix prepended to every route registered in this router. */
    public string $prefix = '';

    /** @var array<string, Route[]> Registered routes grouped by HTTP method. */
    public array $routes = [];

    /**
     * @param mysqli|null $db Optional database connection.
     */
    public function __construct(?mysqli $db = null)
    {
        $this->db = $db;
    }

    /**
     * Builds a Route, stores it under the appropriate HTTP method key,
     * and returns it for optional chaining (e.g. withMiddlewares, withLimit).
     *
     * The full path is formed by prepending the router prefix to the given path.
     * Trailing slashes are stripped; a bare root path is preserved as '/'.
     */
    private function addRoute(HttpMethod $httpMethod, string $path, string $controller, string $method): Route
    {
        $key      = $httpMethod->value;
        $fullPath = rtrim($this->prefix . $path, '/') ?: '/';
        $route    = new Route($httpMethod, $fullPath, $controller, $method, $this->db);
        $this->routes[$key][] = $route;
        return $route;
    }

    /**
     * Sets the path prefix for all routes registered in this router.
     * Should be called at the top of describe().
     *
     * Example: setPrefix('/auth') makes all routes start with /auth.
     */
    protected function setPrefix(string $prefix = ''): void
    {
        $this->prefix = $prefix;
    }

    /** Registers a GET route. */
    protected function get(string $path, string $controller, string $method): Route
    {
        return $this->addRoute(HttpMethod::GET, $path, $controller, $method);
    }

    /** Registers a POST route. */
    protected function post(string $path, string $controller, string $method): Route
    {
        return $this->addRoute(HttpMethod::POST, $path, $controller, $method);
    }

    /** Registers a PUT route. */
    protected function put(string $path, string $controller, string $method): Route
    {
        return $this->addRoute(HttpMethod::PUT, $path, $controller, $method);
    }

    /** Registers a DELETE route. */
    protected function delete(string $path, string $controller, string $method): Route
    {
        return $this->addRoute(HttpMethod::DELETE, $path, $controller, $method);
    }

    /** Registers a PATCH route. */
    protected function patch(string $path, string $controller, string $method): Route
    {
        return $this->addRoute(HttpMethod::PATCH, $path, $controller, $method);
    }

    /**
     * Applies a rate limit to every route currently registered in this router.
     * Must be called after all routes are defined in describe().
     *
     * @param int $maxRequests Maximum number of requests allowed in the window.
     * @param int $perSeconds  Duration of the window in seconds.
     */
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

    /**
     * Override point for subclasses that use build() instead of describe().
     * Left empty intentionally.
     */
    public function build(): void
    {

    }

    /**
     * Returns a plain array representation of all registered routes.
     * Useful for logging and debugging.
     *
     * @return array<int, array{ path: string, method: string, has_database: bool, has_limiter: bool }>
     */
    public function toArray(): array
    {
        $routesArray = [];

        foreach ($this->routes as $_method => $routes)
        {
            foreach ($routes as $route)
            {
                $routesArray[] = $route->toArray();
            }
        }

        return $routesArray;
    }
}