<?php

namespace Wingman\Core\App;

/**
 * Cookie
 *
 * A fluent builder class for creating, reading, and removing HTTP cookies.
 * Instantiation is handled through the static build() method, after which
 * the cookie can be configured by chaining the available with*() methods
 * before calling set() to write it to the response.
 *
 * Usage:
 *
 *   Cookie::build()
 *       ->withKey('token')
 *       ->withValue($jwt)
 *       ->withLifespan(3600)
 *       ->withHttpOnlyAs(true)
 *       ->withSecuredAs(true)
 *       ->set();
 *
 *   // Read a cookie
 *   $token = Cookie::get('token');
 *
 *   // Remove a cookie
 *   Cookie::build()->withKey('token')->unset();
 */
class Cookie
{
    /** The name of the cookie */
    private string $key;

    /** The value stored in the cookie */
    private string $value;

    /** The Unix timestamp at which the cookie expires */
    private int $expiresAt;

    /** The URL path the cookie is valid for. Defaults to '/' (entire domain) */
    private string $path = '/';

    /** Whether the cookie should only be transmitted over HTTPS */
    private bool $secure = false;

    /** Whether the cookie is inaccessible to JavaScript via document.cookie */
    private bool $httpOnly = false;

    /** Controls cross-site request behavior. Accepts 'strict', 'lax', or 'none' */
    private string $samesite = 'lax';

    /**
     * Private constructor — use Cookie::build() to create an instance.
     */
    private function __construct()
    {
    }

    /**
     * Creates and returns a new Cookie builder instance.
     *
     * @return self
     */
    public static function build(): self
    {
        return new self();
    }

    /**
     * Sets the name of the cookie.
     *
     * @param  string $key The cookie name
     * @return self
     */
    public function withKey(string $key): self
    {
        $this->key = $key;
        return $this;
    }

    /**
     * Sets the value of the cookie.
     *
     * @param  string $value The cookie value
     * @return self
     */
    public function withValue(string $value): self
    {
        $this->value = $value;
        return $this;
    }

    /**
     * Sets the cookie's expiry time as a duration from the current time.
     *
     * @param  int  $seconds Number of seconds from now until the cookie expires
     * @return self
     */
    public function withLifespan(int $seconds): self
    {
        $this->expiresAt = time() + $seconds;
        return $this;
    }

    /**
     * Sets the URL path scope for the cookie.
     * Defaults to '/', making the cookie available across the entire domain.
     *
     * @param  string $path The path prefix the cookie applies to
     * @return self
     */
    public function withPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    /**
     * Sets whether the cookie should only be sent over secure HTTPS connections.
     * Should be set to true in production.
     *
     * @param  bool $secured True to restrict to HTTPS only
     * @return self
     */
    public function withSecuredAs(bool $secured = true): self
    {
        $this->secure = $secured;
        return $this;
    }

    /**
     * Sets whether the cookie is inaccessible to client-side JavaScript.
     * Recommended to be set to true to mitigate XSS attacks.
     *
     * @param  bool $httpOnly True to hide the cookie from JavaScript
     * @return self
     */
    public function withHttpOnlyAs(bool $httpOnly = true): self
    {
        $this->httpOnly = $httpOnly;
        return $this;
    }

    /**
     * Sets the SameSite attribute to control cross-site request behavior.
     *
     * Accepted values:
     * - 'strict' : Cookie is only sent for same-site requests
     * - 'lax'    : Cookie is sent for same-site requests and top-level navigations (default)
     * - 'none'   : Cookie is sent for all requests, requires secure to be true
     *
     * @param  string $samesite The SameSite policy ('strict', 'lax', or 'none')
     * @return self
     */
    public function withSamesiteAs(string $samesite = 'lax'): self
    {
        $this->samesite = $samesite;
        return $this;
    }

    /**
     * Writes the cookie to the HTTP response using the configured settings.
     * Must be called before any output is sent to the browser.
     */
    public function set(): void
    {
        setcookie($this->key, $this->value, [
            "expires"  => $this->expiresAt,
            "path"     => $this->path,
            "secure"   => $this->secure,
            "httponly" => $this->httpOnly,
            "samesite" => $this->samesite
        ]);
    }

    /**
     * Removes the cookie from the browser by overwriting it with an
     * already-expired timestamp, prompting the browser to delete it.
     * Must be called before any output is sent to the browser.
     */
    public function unset(): void
    {
        setcookie($this->key, "", [
            "expires"  => time() - 3600,
            "path"     => $this->path,
            "secure"   => $this->secure,
            "httponly" => $this->httpOnly,
            "samesite" => $this->samesite
        ]);
    }

    /**
     * Reads a cookie value from the current request by name.
     *
     * @param  string      $key The cookie name to look up
     * @return string|null      The cookie value, or null if not present
     */
    public static function get(string $key): ?string
    {
        return $_COOKIE[$key] ?? null;
    }
}