<?php

namespace Wingman\Core\App;

use mysqli;
use Wingman\Core\App\Enums\Builtins\HttpMethod;

/**
 * Route
 *
 * Represents a single registered HTTP route in the Wingman application.
 * Each route maps an HTTP method and a path pattern to a specific controller
 * method, and optionally runs a chain of middlewares before dispatching.
 *
 * Routes are typically created inside a Router's describe() method and
 * should not be instantiated directly outside of that context.
 *
 * Lifecycle of a matched route:
 *   1. App matches the incoming URI to a Route via compileRoute() + matchRoute()
 *   2. App calls Route::run() with the extracted URL params
 *   3. Route runs the middleware chain via runMiddlewares()
 *   4. Middleware chain produces a populated Context object
 *   5. Route instantiates the controller and calls the target method,
 *      passing in a Request (with params + context) and a Response
 */
class Route
{
    /** The HTTP method this route responds to (GET, POST, PUT, DELETE, etc.) */
    public HttpMethod $httpMethod;

    /** The URL path pattern for this route (e.g. '/users/{id:\d+}') */
    public string $path;

    /** Fully-qualified controller class name (e.g. 'Wingman\Controllers\UserController') */
    public string $controller;

    /** The controller method to call when this route is matched (e.g. 'show') */
    public string $method;

    /** Ordered list of fully-qualified middleware class names to run before the controller */
    private array $middleWares = [];

    /** The shared mysqli database connection passed down to middlewares and controllers */
    private ?mysqli $db = null;

    private ?RateLimiter $limiter = null;

    /**
     * @param HttpMethod $httpMethod The HTTP method (GET, POST, etc.)
     * @param string     $path       The route path pattern
     * @param string     $controller Fully-qualified controller class name
     * @param string     $method     Controller method to invoke on match
     * @param ?mysqli    $db         Optional database connection
     */
    public function __construct(
        HttpMethod $httpMethod,
        string $path,
        string $controller,
        string $method,
        ?mysqli $db = null,
    )
    {
        $this->httpMethod = $httpMethod;
        $this->path       = $path;
        $this->controller = $controller;
        $this->method     = $method;
        $this->db         = $db;
    }

    /**
     * Attaches an ordered list of middlewares to this route.
     *
     * Middlewares are executed in the order they are provided before the
     * controller method is called. Each middleware receives the shared
     * Context and can call next() to pass control to the next one.
     *
     * @param  array $middlewares Ordered array of fully-qualified middleware class names
     * @return self               Returns the route instance for method chaining
     */
    public function withMiddlewares(array $middlewares): self
    {
        $this->middleWares = $middlewares;
        return $this;
    }

    public function withLimit(int $maxRequests, int $perSeconds): self
    {
        $this->limiter = new RateLimiter($maxRequests, $perSeconds);
        return $this;
    }

    /**
     * Executes the middleware chain and returns the resulting Context.
     *
     * If no middlewares are registered, an empty Context is returned immediately.
     * Otherwise, the first middleware is instantiated and set up with the full
     * middleware list, a fresh Context, and the database connection. Calling
     * run() on the first middleware triggers the chain — each middleware is
     * responsible for calling next() to invoke the one after it.
     *
     * @return Context The context object populated by the middleware chain
     */
    private function runMiddlewares(): Context
    {
        // No middlewares registered — return an empty context immediately
        if (empty($this->middleWares))
            return new Context();

        // Bootstrap the chain from the first middleware
        // It will internally call next() to run the remaining ones in order
        $firstMiddleware = new $this->middleWares[0];
        $firstMiddleware->setup($this->middleWares, new Context, $this->db);
        return $firstMiddleware->run();
    }

    /**
     * Dispatches the route by running middlewares and invoking the controller method.
     *
     * Steps:
     * 1. Verifies the controller class exists
     * 2. Verifies the target method exists on the controller
     * 3. Runs the middleware chain to produce a populated Context
     * 4. Builds a Request from the URL params and the Context
     * 5. Instantiates the controller and calls the target method
     *
     * Responds with 500 and halts if the controller class or method is not found.
     *
     * @param array $params Associative array of URL parameters extracted from the matched route
     *                      e.g. ['id' => '42', 'slug' => 'hello-world']
     */
    public function run(array $params = []): void
    {
        // Guard: ensure the controller class is loadable
        if (!class_exists($this->controller))
            Response::internalServerError(['message' => "Controller class '{$this->controller}' not found."]);

        $controller = new $this->controller($this->db);
        $method     = $this->method;

        // Guard: ensure the target method exists on the controller
        if (!method_exists($this->controller, $method))
            Response::internalServerError(['message' => "Method '{$method}' not found on '{$this->controller}'."]);

        if ($this->limiter && !$this->limiter->isAllowed())
        {
            $window = $this->limiter->getReadableWindow();
            Response::manyRequests(['message' => "Too many login attempts. Try again in {$window}."]);
        }
        
        // Run middlewares to build the context, then dispatch to the controller
        $context  = $this->runMiddlewares();
        $request  = new Request($context, $params);
        $response = new Response();

        $controller->$method($request, $response);
    }
}