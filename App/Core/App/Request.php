<?php

namespace Wingman\Core\App;

/**
 * Represents an incoming HTTP request.
 *
 * Parses and encapsulates the request body, URL parameters, query strings,
 * and the middleware-populated Context. Provides typed accessors for
 * retrieving values from each source.
 */
class Request
{
    /** @var array<string, mixed> Parsed request body, from JSON or form POST data. */
    private array $body;

    /** @var array<string, string> Path parameters extracted from the matched route. */
    private array $params;

    /** @var array<string, string> Query string parameters from the URL. */
    private array $queries;

    /** @var Context Data bag populated by the middleware chain before dispatch. */
    private Context $context;

    /**
     * @param Context             $context Middleware context passed from the route dispatcher.
     * @param array<string,string> $params  Path parameters extracted by the router.
     */
    public function __construct(Context $context, array $params = [])
    {
        $this->body    = self::parseBody();
        $this->params  = $params ?? [];
        $this->queries = self::parseQueries();
        $this->context = $context;
    }

    /**
     * Parses the request body based on the Content-Type header.
     *
     * - application/json: reads raw input and decodes it as JSON.
     * - anything else:    falls back to $_POST.
     *
     * @return array<string, mixed>
     */
    private static function parseBody(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            return json_decode($raw, true) ?? [];
        }

        return $_POST ?? [];
    }

    /**
     * Returns the raw query string parameters from the URL.
     *
     * @return array<string, string>
     */
    private static function parseQueries(): array
    {
        return $_GET ?? [];
    }

    /**
     * Retrieves a value from the query string by key.
     *
     * @param string $key     The query parameter name.
     * @param mixed  $default Returned if the key is not present.
     */
    public function fromQuery(string $key, mixed $default = null): ?string
    {
        return $this->queries[$key] ?? $default;
    }

    /**
     * Retrieves a value from the parsed request body by key.
     *
     * @param string $key The body field name.
     */
    public function fromBody(string $key): ?string
    {
        return $this->body[$key] ?? null;
    }

    /**
     * Retrieves a path parameter by key.
     *
     * Path parameters are dynamic segments extracted from the route pattern,
     * e.g. {id} in /users/{id}.
     *
     * @param string $key The parameter name as defined in the route pattern.
     */
    public function fromParams(string $key): ?string
    {
        return $this->params[$key] ?? null;
    }

    /**
     * Retrieves a value from the middleware Context by key.
     *
     * Context values are set by middlewares during the request lifecycle,
     * typically used to pass data such as the authenticated user to controllers.
     *
     * @param string $key The context key to retrieve.
     */
    public function fromContext(string $key): mixed
    {
        return $this->context->get($key);
    }

    /**
     * Returns all request data as a single associative array.
     * Useful for debugging or passing the full request state to a handler.
     *
     * @return array{ params: array, queries: array, body: array }
     */
    public function fromAll(): array
    {
        return [
            'params'  => $this->params,
            'queries' => $this->queries,
            'body'    => $this->body,
        ];
    }
}