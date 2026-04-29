<?php

namespace Wingman\Core\App;

/**
 * Context
 *
 * A shared data bag that carries state across the middleware chain.
 * Each middleware can read from and write to the context, allowing
 * data to be passed downstream to subsequent middlewares and controllers.
 *
 * Common use cases:
 * - Attaching an authenticated user after token verification
 * - Passing parsed request metadata between middlewares
 * - Sharing computed values without re-fetching them
 *
 * Usage:
 *
 *   // In a middleware
 *   $this->context->add('auth_user', $user);
 *
 *   // In the next middleware or controller
 *   $user = $this->context->get('auth_user');
 */
class Context
{
    /**
     * Internal key-value store holding all context data.
     */
    private array $data = [];

    /**
     * Adds or overwrites a value in the context by key.
     *
     * @param string $key   The key to store the value under
     * @param mixed  $value The value to store (any type)
     */
    public function add(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    /**
     * Retrieves a value from the context by key.
     * Returns null if the key does not exist.
     *
     * @param  string $key The key to look up
     * @return mixed       The stored value, or null if not found
     */
    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    /**
     * Returns all context data as an associative array.
     * Useful for debugging or passing the full context payload downstream.
     *
     * @return array All stored key-value pairs
     */
    public function getAll(): array
    {
        return $this->data;
    }
}