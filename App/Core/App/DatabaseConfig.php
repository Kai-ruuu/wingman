<?php

namespace Wingman\Core\App;

/**
 * DatabaseConfig
 *
 * A value object that holds database connection credentials.
 * Instantiated exclusively through one of its three named constructors
 * depending on the environment — the private constructor prevents
 * direct instantiation and enforces the use of the factory methods.
 *
 * Choose the factory method that fits your environment:
 *
 *   DatabaseConfig::fromDefault()               → zero-config local development
 *   DatabaseConfig::fromCustom(...)             → explicit credentials in development
 *   DatabaseConfig::fromEnv()                   → environment variables for production
 *
 * The resulting instance is passed to Database::fromConfig() to establish
 * the mysqli connection.
 */
class DatabaseConfig
{
    /** Database server hostname (e.g. 'localhost' or '127.0.0.1') */
    public string $hostname;

    /** Database username */
    public string $username;

    /** Database password */
    public string $password;

    /** Name of the database to connect to */
    public string $database_name;

    /**
     * Private constructor — use one of the static factory methods instead.
     */
    private function __construct(string $hostname, string $username, string $password, string $database_name)
    {
        $this->hostname      = $hostname;
        $this->username      = $username;
        $this->password      = $password;
        $this->database_name = $database_name;
    }

    /**
     * Creates a config using sensible local development defaults.
     * No arguments needed — ideal for getting started quickly.
     *
     * Defaults:
     *   hostname      → localhost
     *   username      → root
     *   password      → (empty)
     *   database_name → app-database
     *
     * @return self
     */
    public static function fromDefault(): self
    {
        return new DatabaseConfig('localhost', 'root', '', 'app-database');
    }

    /**
     * Creates a config with explicit credentials.
     * Use this when your local setup differs from the defaults
     * (e.g. different username, password, or database name).
     *
     * All parameters are optional and fall back to the same defaults
     * as fromDefault() if not provided.
     *
     * @param  string $hostname      Database server hostname
     * @param  string $username      Database username
     * @param  string $password      Database password
     * @param  string $database_name Name of the database
     * @return self
     */
    public static function fromCustom(
        string $hostname      = 'localhost',
        string $username      = 'root',
        string $password      = '',
        string $database_name = 'app-database'
    ): self
    {
        return new DatabaseConfig($hostname, $username, $password, $database_name);
    }

    /**
     * Creates a config by reading credentials from environment variables.
     * Recommended for production — keeps sensitive credentials out of
     * your codebase and in the server environment or .env file instead.
     *
     * Required environment variables:
     *   DB_HOST → database hostname
     *   DB_USER → database username
     *   DB_PASS → database password
     *   DB_NAME → database name
     *
     * Falls back to the same defaults as fromDefault() for any missing keys,
     * though in production all four variables should always be explicitly set.
     *
     * @return self
     */
    public static function fromEnv(): self
    {
        return new DatabaseConfig(
            Env::get('DB_HOST', 'localhost'),
            Env::get('DB_USER', 'root'),
            Env::get('DB_PASS', ''),
            Env::get('DB_NAME', 'app-database'),
        );
    }
}