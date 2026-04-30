<?php

namespace Wingman\Core\App;

use mysqli;
use Wingman\Core\App\Response;
use Wingman\Core\App\Route;

/**
 * App
 *
 * The core application class and entry point of every Wingman application.
 * Responsible for bootstrapping the app, registering routers, compiling
 * route patterns, matching incoming requests, and dispatching them to the
 * appropriate route handler.
 *
 * Usage:
 *
 *   // Without a database connection
 *   App::default()
 *       ->withRouters([HomeRouter::class])
 *       ->listen();
 *
 *   // With a database connection
 *   App::withDatabase($db)
 *       ->withRouters([UserRouter::class, PostRouter::class])
 *       ->listen();
 */
class App
{
    /**
     * The mysqli database connection instance.
     * Null if the app was initialized without a database (e.g. App::default()).
     */
    private ?mysqli $db = null;

    /**
     * All registered routes grouped by HTTP method.
     * Structure: ['GET' => [Route, ...], 'POST' => [Route, ...], ...]
     */
    private array $routes = [];

    /**
     * Tracks registered router prefixes to prevent duplicate prefix registration.
     * e.g. ['/users', '/posts']
     */
    private array $registeredPrefixes = [];

    /**
     * Creates a new App instance without a database connection.
     * Use this for apps or routers that don't require database access.
     */
    public static function default(): self
    {
        return new self();
    }

    /**
     * Creates a new App instance with a mysqli database connection.
     * The connection is passed down to all routers, middlewares, and controllers.
     */
    public static function withDatabase(mysqli $db): self
    {
        $instance = new self();
        $instance->db = $db;
        return $instance;
    }

    /**
     * Registers an array of router class names with the application.
     *
     * For each router:
     * - Instantiates the router with the current database connection
     * - Calls describe() to register the router's routes
     * - Guards against duplicate router prefixes
     * - Merges the router's routes into the app's global route table
     *
     * @param  array $routers Array of fully-qualified router class name strings
     * @return self           Returns the app instance for method chaining
     */
    public function withRouters(array $routers): self
    {
        foreach ($routers as $router)
        {
            $routerInstance = new $router($this->db);

            // Populate the router's internal route list by calling its describe() method
            $routerInstance->describe();

            // Prevent two routers from sharing the same prefix (e.g. two routers on '/users')
            if (in_array($routerInstance->prefix, $this->registeredPrefixes))
            {
                Logger::error("A router with the prefix '{$router->prefix}' was already registered.");
                die;
            }

            $this->registeredPrefixes[] = $routerInstance->prefix;

            // Merge this router's routes into the global route table
            $this->routes = array_merge($this->routes, $routerInstance->routes);
        }

        return $this;
    }

    /**
     * Compiles a route path string into a regex pattern and extracts parameter names.
     *
     * Supports dynamic segments in two forms:
     * - {id}         → captures any non-slash value using the default pattern [^/]+
     * - {id:\d+}     → captures a value matching the custom pattern \d+
     *
     * Example:
     *   Input:  '/users/{id:\d+}/posts/{slug}'
     *   Output: [
     *     'regex'  => '#^/users/(\d+)/posts/([^/]+)$#',
     *     'params' => ['id', 'slug']
     *   ]
     *
     * @param  string $path The route path (e.g. '/users/{id:\d+}')
     * @return array        Associative array with 'regex' and 'params' keys
     */
    private function compileRoute(string $path): array
    {
        $paramNames = [];

        $regex = preg_replace_callback(
            '/\{(\w+)(?::([^}]+))?\}/',
            function (array $matches) use (&$paramNames): string {
                // Capture the parameter name (e.g. 'id', 'slug')
                $paramNames[] = $matches[1];

                // Use the custom pattern if provided, otherwise match any non-slash value
                $pattern = $matches[2] ?? '[^/]+';

                return '(' . $pattern . ')';
            },
            $path
        );

        // Wrap the pattern into a full anchored regex
        $regex = '#^' . $regex . '$#';

        return ['regex' => $regex, 'params' => $paramNames];
    }

    /**
     * Attempts to match an incoming URI against a registered route.
     *
     * If the URI matches the route's compiled regex, returns an associative
     * array of extracted route parameters (e.g. ['id' => '42', 'slug' => 'hello']).
     * Returns null if the URI does not match the route.
     *
     * @param  Route  $route The route to match against
     * @param  string $uri   The incoming request URI path
     * @return array|null    Matched params as key-value pairs, or null on no match
     */
    private function matchRoute(Route $route, string $uri): ?array
    {
        ['regex' => $regex, 'params' => $paramNames] = $this->compileRoute($route->path);

        if (!preg_match($regex, $uri, $matches)) {
            return null;
        }

        // Remove the full match (index 0), keep only the capture groups
        array_shift($matches);

        // Map capture group values to their corresponding parameter names
        return array_combine($paramNames, $matches);
    }

    /**
     * Starts listening for the incoming HTTP request and dispatches it.
     *
     * - Extracts the request URI path and HTTP method from the server globals
     * - Returns 404 if no routes are registered for the given method
     * - Iterates over registered routes for that method and attempts to match
     * - Dispatches the first matching route by calling its run() method
     * - Returns 404 if no route matches the incoming URI
     */
    public function listen(): void
    {
        $uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'];

        // No routes registered for this HTTP method
        if (empty($this->routes[$method])) {
            Response::notFound(['message' => 'Route not found.']);
            return;
        }

        // Try to match the URI against each registered route for this method
        foreach ($this->routes[$method] as $route) {
            $params = $this->matchRoute($route, $uri);

            if ($params !== null) {
                // Match found — dispatch the route with the extracted params
                $route->run($params);
                return;
            }
        }

        // No matching route found
        Response::notFound(['message' => 'Route not found.']);
    }
}