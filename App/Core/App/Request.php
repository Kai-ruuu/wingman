<?php

namespace Wingman\Core\App;

/**
 * Request
 *
 * Represents the incoming HTTP request. Parses and encapsulates all
 * incoming data sources — request body, URL parameters, query strings,
 * and the middleware context — into a single, clean interface.
 *
 * Automatically detects the Content-Type of the request body and parses
 * it accordingly: JSON payloads are decoded from php://input, while
 * form submissions are read from $_POST.
 *
 * An instance of Request is built by the Route after the middleware chain
 * completes, and is passed as the first argument to every controller method.
 *
 * Data sources:
 *   - fromBody()    → JSON body or form data   (POST /users with body payload)
 *   - fromParams()  → URL route parameters     (/users/{id})
 *   - fromQuery()   → Query string parameters  (/users?page=2&limit=10)
 *   - fromContext() → Middleware context data  (e.g. auth_user set by AuthMiddleware)
 */
class Request
{
    /** Parsed request body — from JSON input or $_POST */
    private array $body;

    /** URL route parameters extracted from the matched route pattern (e.g. ['id' => '42']) */
    private array $params;

    /** Query string parameters from the URL (e.g. ['page' => '2', 'limit' => '10']) */
    private array $queries;

    /** The context object populated by the middleware chain */
    private Context $context;

    /**
     * @param Context $context The context produced by the middleware chain
     * @param array   $params  URL route parameters extracted by the router
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
     * - application/json → reads raw input from php://input and JSON-decodes it
     * - anything else    → falls back to $_POST (standard form submission)
     *
     * Returns an empty array if the body is absent or cannot be decoded.
     *
     * @return array Parsed body as an associative array
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
     * Reads query string parameters from the URL.
     * Wraps $_GET for consistency and testability.
     *
     * @return array Associative array of query string parameters
     */
    private static function parseQueries(): array
    {
        return $_GET ?? [];
    }

    /**
     * Retrieves a value from the query string by key.
     * Returns $default if the key is not present.
     *
     * Example: GET /users?page=2 → fromQuery('page') returns '2'
     *
     * @param  string $key     The query parameter key
     * @param  mixed  $default Fallback value if the key is absent (default: null)
     * @return ?string         The query value, or the default
     */
    public function fromQuery(string $key, mixed $default = null): ?string
    {
        return $this->queries[$key] ?? $default;
    }

    /**
     * Retrieves a value from the parsed request body by key.
     * Returns null if the key is not present.
     *
     * Example: POST /users with JSON body {"username": "john"} → fromBody('username') returns 'john'
     *
     * @param  string  $key The body field key
     * @return ?string      The body value, or null if not found
     */
    public function fromBody(string $key): ?string
    {
        return $this->body[$key] ?? null;
    }

    /**
     * Retrieves a value from the URL route parameters by key.
     * Returns null if the key is not present.
     *
     * Example: GET /users/42 with route '/users/{id}' → fromParams('id') returns '42'
     *
     * @param  string  $key The route parameter key
     * @return ?string      The param value, or null if not found
     */
    public function fromParams(string $key): ?string
    {
        return $this->params[$key] ?? null;
    }

    /**
     * Retrieves a value from the middleware context by key.
     * Returns null if the key was not set by any middleware.
     *
     * Example: AuthMiddleware sets 'auth_user' → fromContext('auth_user') returns the user
     *
     * @param  string  $key The context key
     * @return ?string      The context value, or null if not found
     */
    public function fromContext(string $key): ?string
    {
        return $this->context->get($key);
    }

    /**
     * Returns all request data sources as a single associative array.
     * Useful for debugging or logging the full incoming request payload.
     *
     * @return array All params, queries, and body data keyed by source
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