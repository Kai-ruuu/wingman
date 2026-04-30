<?php

namespace Wingman\Core\App;

use mysqli;
use Wingman\Core\App\Enums\Builtins\HttpMethod;

/**
 * Represents a single registered route.
 *
 * Holds the HTTP method, path, controller, and action method for a route,
 * along with optional middleware chain and rate limiter. Responsible for
 * running the full request lifecycle when dispatched by the App.
 */
class Route
{
    /** @var HttpMethod The HTTP method this route responds to. */
    public HttpMethod $httpMethod;

    /** @var string The full path pattern for this route, including any prefix. */
    public string $path;

    /** @var string Fully-qualified class name of the controller. */
    public string $controller;

    /** @var string The controller method to invoke when this route is matched. */
    public string $method;

    /** @var string[] Ordered list of middleware class names to run before the controller. */
    private array $middleWares = [];

    /** @var mysqli|null Database connection passed to the controller on instantiation. */
    private ?mysqli $db = null;

    /** @var RateLimiter|null Optional rate limiter applied before the controller runs. */
    private ?RateLimiter $limiter = null;

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
     * Attaches an ordered middleware chain to this route.
     * Middlewares are executed in array order before the controller.
     *
     * @param string[] $middlewares Fully-qualified middleware class names.
     */
    public function withMiddlewares(array $middlewares): self
    {
        $this->middleWares = $middlewares;
        return $this;
    }

    /**
     * Attaches a rate limiter to this route.
     *
     * @param int $maxRequests Maximum requests allowed within the window.
     * @param int $perSeconds  Window duration in seconds.
     */
    public function withLimit(int $maxRequests, int $perSeconds): self
    {
        $this->limiter = new RateLimiter($maxRequests, $perSeconds);
        return $this;
    }

    /**
     * Executes the middleware chain and returns the resulting Context.
     *
     * If no middlewares are configured, returns an empty Context.
     * Otherwise, the first middleware is instantiated and seeded with
     * the full chain, an initial Context, and the database connection.
     * Each middleware is responsible for passing control to the next.
     */
    private function runMiddlewares(): Context
    {
        if (empty($this->middleWares))
            return new Context();

        $firstMiddleware = new $this->middleWares[0];
        $firstMiddleware->setup($this->middleWares, new Context, $this->db);
        return $firstMiddleware->run();
    }

    /**
     * Dispatches the route: runs middlewares, enforces rate limiting,
     * instantiates the controller, and calls the target method.
     *
     * @param array<string, string> $params Path parameters extracted from the URI.
     */
    public function run(array $params = []): void
    {
        if (!class_exists($this->controller))
        {
            Logger::error("Controller class '{$this->controller}' not found.");
            Response::internalServerError(['message' => 'A server error occurred.']);
        }

        $controller = new $this->controller($this->db);
        $method     = $this->method;

        if (!method_exists($controller, $method))
        {
            Logger::error("Method '{$method}' not found on '{$this->controller}'.");
            Response::internalServerError(['message' => 'A server error has occurred.']);
        }

        if ($this->limiter && !$this->limiter->isAllowed())
        {
            $window = $this->limiter->getReadableWindow();
            Response::manyRequests(['message' => "Too many login attempts. Try again in {$window}."]);
        }

        $context  = $this->runMiddlewares();
        $request  = new Request($context, $params);
        $response = new Response();

        $controller->$method($request, $response);
    }

    /**
     * Returns a plain array representation of this route.
     * Useful for logging and debugging.
     *
     * @return array{ path: string, method: string, has_database: bool, has_limiter: bool }
     */
    public function toArray(): array
    {
        return [
            'path'         => $this->path,
            'method'       => $this->httpMethod->value,
            'has_database' => $this->db !== null,
            'has_limiter'  => $this->limiter !== null
        ];
    }
}