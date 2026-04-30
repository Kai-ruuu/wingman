<?php

namespace Wingman\Core\App;

use mysqli;
use Wingman\Core\App\Response;
use Wingman\Core\App\Route;

/**
 * The application entry point.
 *
 * Responsible for registering routers, compiling and matching routes,
 * and dispatching incoming HTTP requests to the appropriate handler.
 */
class App
{
    /** @var mysqli|null The database connection passed down to routers and controllers. */
    private ?mysqli $db = null;

    /** @var array<string, Route[]> Routes grouped by HTTP method (e.g. 'GET', 'POST'). */
    private array $routes = [];

    /** @var string[] Tracks registered router prefixes to prevent duplicates. */
    private array $registeredPrefixes = [];

    /**
     * Creates an App instance without a database connection.
     */
    public static function default(): self
    {
        return new self();
    }

    /**
     * Creates an App instance with a database connection.
     * The connection is passed down to all routers, routes, and controllers.
     */
    public static function withDatabase(mysqli $db): self
    {
        $instance     = new self();
        $instance->db = $db;
        return $instance;
    }

    /**
     * Registers an array of router class names with the application.
     *
     * Each router is instantiated, described (routes registered), and its
     * routes are merged into the application's route table keyed by HTTP method.
     * Duplicate prefixes are rejected to prevent ambiguous routing.
     *
     * @param string[] $routers Fully-qualified class names of BaseRouter subclasses.
     */
    public function withRouters(array $routers): self
    {
        foreach ($routers as $router)
        {
            $routerInstance = new $router($this->db);

            // Populate the router's route table by calling its describe() method.
            $routerInstance->describe();

            if (in_array($routerInstance->prefix, $this->registeredPrefixes))
            {
                Logger::error("A router with the prefix '{$routerInstance->prefix}' was already registered.");
                die;
            }

            $this->registeredPrefixes[] = $routerInstance->prefix;

            // Merge routes per HTTP method to preserve string keys (GET, POST, etc.).
            foreach ($routerInstance->routes as $method => $routes)
            {
                $this->routes[$method] = array_merge($this->routes[$method] ?? [], $routes);
            }
        }

        return $this;
    }

    /**
     * Compiles a route path pattern into a named regex for matching.
     *
     * Supports dynamic segments in two forms:
     *   - {name}         — matches any non-slash sequence
     *   - {name:pattern} — matches the given regex pattern
     *
     * Static segments are escaped so metacharacters in path literals
     * (e.g. dots) don't affect matching.
     *
     * Example:
     *   /users/{id:\d+}/posts → #^/users/(\d+)/posts$#
     *
     * @return array{ regex: string, params: string[] }
     */
    private function compileRoute(string $path): array
    {
        $paramNames = [];

        // Split the path into alternating static and dynamic segments.
        $segments = preg_split('/(\{[^}]+\})/', $path, -1, PREG_SPLIT_DELIM_CAPTURE);

        $regexParts = array_map(function (string $segment) use (&$paramNames): string {

            // Dynamic segment: extract the param name and optional pattern.
            if (preg_match('/^\{(\w+)(?::([^}]+))?\}$/', $segment, $m))
            {
                $paramNames[] = $m[1];
                $pattern      = $m[2] ?? '[^/]+';
                return '(' . $pattern . ')';
            }

            // Static segment: escape regex metacharacters.
            return preg_quote($segment, '#');

        }, $segments);

        $regex = '#^' . implode('', $regexParts) . '$#';

        return ['regex' => $regex, 'params' => $paramNames];
    }

    /**
     * Attempts to match an incoming URI against a given route.
     *
     * Returns an associative array of extracted path parameters on match,
     * an empty array if the route has no parameters, or null if no match.
     *
     * @return array<string, string>|null
     */
    private function matchRoute(Route $route, string $uri): ?array
    {
        ['regex' => $regex, 'params' => $paramNames] = $this->compileRoute($route->path);

        if (!preg_match($regex, $uri, $matches))
            return null;

        // Drop the full match, keeping only capture groups.
        array_shift($matches);

        if (empty($paramNames))
            return [];

        if (count($paramNames) !== count($matches))
            return null;

        return array_combine($paramNames, $matches);
    }

    /**
     * Starts listening for the current HTTP request and dispatches it.
     *
     * Normalizes the request URI (strips trailing slashes, preserves root)
     * and matches it against registered routes by HTTP method. Runs the first
     * matching route, or responds with 404 if none is found.
     */
    public function listen(): void
    {
        $rawUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        // Normalize trailing slashes to match how routes are stored in addRoute().
        $uri    = $rawUri !== '/' ? rtrim($rawUri, '/') : '/';
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if (empty($this->routes[$method]))
        {
            Response::notFound(['message' => 'Route not found.']);
            return;
        }

        foreach ($this->routes[$method] as $route)
        {
            $params = $this->matchRoute($route, $uri);

            if ($params !== null)
            {
                $route->run($params);
                return;
            }
        }

        Response::notFound(['message' => 'Route not found.']);
    }
}