<?php

namespace Wingman\Core\App;

/**
 * CorsHandler
 *
 * A fluent, immutable builder for configuring and applying CORS headers.
 * Each with*() method returns a new instance, leaving the original unchanged.
 *
 * Usage:
 *
 *   // Explicit configuration
 *   CorsHandler::build()
 *       ->withAllowedOrigins(['https://yourfrontend.com'])
 *       ->withAllowedMethods(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'])
 *       ->withAllowedHeaders(['Content-Type', 'Authorization', 'X-Requested-With'])
 *       ->withAllowedCredentials()
 *       ->withCachePreflight(86400)
 *       ->listen();
 *
 *   // Shorthand — uses wildcard origin, standard methods/headers, no credentials, 24h cache
 *   CorsHandler::build()->listen();
 */
class CorsHandler
{
    private array $allowedOrigins;
    private array $allowedMethods;
    private array $allowedHeaders;
    private bool $allowCredentials = false;
    private int $cachePreflight = 86400;
    
    private function __construct(array $allowedOrigins, array $allowedMethods, array $allowedHeaders)
    {
        $this->allowedOrigins = $allowedOrigins;
        $this->allowedMethods = $allowedMethods;
        $this->allowedHeaders = $allowedHeaders;
    }

    /**
     * Creates a CorsHandler instance with sensible defaults:
     *   - Allowed origins:  ['*']
     *   - Allowed methods:  GET, POST, PUT, PATCH, DELETE, OPTIONS
     *   - Allowed headers:  Content-Type, Authorization, X-Requested-With
     *   - Credentials:      false
     *   - Preflight cache:  86400 seconds (24 hours)
     */
    public static function build(): self
    {
        $allowedOrigins = ['*'];
        $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];
        $allowedHeaders = ['Content-Type', 'Authorization', 'X-Requested-With'];

        return new self($allowedOrigins, $allowedMethods, $allowedHeaders);
    }

    /**
     * Returns a new instance with the specified allowed origins.
     *
     * Pass ['*'] to allow all origins, or an explicit list to restrict access:
     *   ->withAllowedOrigins(['https://yourfrontend.com', 'https://staging.yourfrontend.com'])
     *
     * Note: Wildcard origin ('*') cannot be used together with withAllowedCredentials().
     */
    public function withAllowedOrigins(array $allowedOrigins): self
    {
        $this->allowedOrigins = $allowedOrigins;
        return $this;
    }

    /**
     * Returns a new instance with the specified allowed HTTP methods.
     *
     * Defaults cover standard REST methods plus OPTIONS for preflight.
     * Override if your API exposes only a subset:
     *   ->withAllowedMethods(['GET', 'POST'])
     */
    public function withAllowedMethods(array $allowedMethods): self
    {
        $this->allowedMethods = $allowedMethods;
        return $this;
    }

    /**
     * Returns a new instance with the specified allowed request headers.
     *
     * Add any custom headers your API accepts:
     *   ->withAllowedHeaders(['Content-Type', 'Authorization', 'X-Api-Key'])
     */
    public function withAllowedHeaders(array $allowedHeaders): self
    {
        $this->allowedHeaders = $allowedHeaders;
        return $this;
    }

    /**
     * Returns a new instance with credentials support enabled.
     *
     * When set, the Access-Control-Allow-Credentials: true header is sent,
     * allowing browsers to include cookies and Authorization headers in
     * cross-origin requests.
     *
     * Requires a specific origin — cannot be combined with a wildcard origin ('*').
     * listen() will terminate with an error if this constraint is violated.
     */
    public function withAllowedCredentials(): self
    {
        $this->allowCredentials = true;
        return $this;
    }

    /**
     * Returns a new instance with the specified preflight cache duration.
     *
     * Controls the Access-Control-Max-Age header — how long (in seconds) the
     * browser may cache the preflight response before sending another OPTIONS request.
     *
     * Defaults to 86400 (24 hours). Note: Chrome caps this at 7200 (2 hours)
     * regardless of the value set here.
     */
    public function withCachePreflight(int $cachePreflight): self
    {
        $this->cachePreflight = $cachePreflight;
        return $this;
    }

    /**
     * Applies the configured CORS headers to the current response and handles preflights.
     *
     * - Reflects a specific origin with Vary: Origin, or sends a wildcard
     * - Sends Allow-Methods, Allow-Headers, and Max-Age unconditionally
     * - Sends Allow-Credentials only when explicitly enabled
     * - Terminates with 204 No Content on OPTIONS preflight requests
     *
     * Should be called once at the application entry point, before any routing
     * or middleware logic runs.
     */
    public function listen(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if (in_array('*', $this->allowedOrigins))
        {
            header('Access-Control-Allow-Origin: *');
        }
        else if (in_array($origin, $this->allowedOrigins))
        {
            header("Access-Control-Allow-Origin: {$origin}");
            header('Vary: Origin');
        }

        header('Access-Control-Allow-Methods: ' . join(', ', $this->allowedMethods));
        header('Access-Control-Allow-Headers: ' . join(', ', $this->allowedHeaders));

        if ($this->allowCredentials && in_array('*', $this->allowedOrigins)) {
            Logger::error('Access-Control-Allow-Credentials cannot be used with a wildcard origin.');
            die;
        }

        if ($this->allowCredentials)
            header('Access-Control-Allow-Credentials: true');
        
        header('Access-Control-Max-Age: ' . $this->cachePreflight);

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}