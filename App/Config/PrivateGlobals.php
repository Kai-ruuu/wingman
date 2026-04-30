<?php

namespace Wingman\Config;

use Wingman\Core\App\DatabaseConfig;
use Wingman\Core\App\Env;

/**
 * PrivateGlobals
 *
 * A configuration class for defining application-wide sensitive globals
 * such as JWT settings, admin credentials, and database configuration.
 *
 * By default, values are pulled from environment variables via Env::get(),
 * with fallback defaults provided for local development convenience.
 *
 * For environments that do not support .env files (e.g. InfinityFree),
 * replace the Env::get() calls with hardcoded values and ensure this file
 * is added to .gitignore to avoid exposing sensitive data in version control.
 *
 * Uncomment and configure the properties and methods below as needed.
 */
class PrivateGlobals
{
    /**
     * The algorithm used for signing and verifying JWT tokens.
     * HS256 is the default symmetric HMAC algorithm.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc7518#section-3
     */
    public static string $jwtAlg = 'HS256';

    /**
     * The secret key used to sign and verify JWT tokens.
     * Should be a long, randomly generated string.
     *
     * Generate one with: php -r "echo bin2hex(random_bytes(32));"
     *
     * Never commit this value to version control.
     */
    public static string $jwtKey = 'example_jwt_key';

    /**
     * Returns the default admin account credentials.
     *
     * Used when seeding the initial admin user into the database.
     * Values are read from environment variables with safe fallback defaults.
     *
     * @return array{username: string, email: string, password: string}
     */
    public static function getAdminCredentials(): array
    {
        return [
            'username' => Env::get('ADMIN_USERNAME', 'Administrator'),
            'email'    => Env::get('ADMIN_EMAIL', 'admin@example.com'),
            'password' => Env::get('ADMIN_PASSWORD', '@Admin_Example_Pass_12345')
        ];
    }

    /**
     * Returns the database configuration for the application.
     *
     * Values are read from environment variables with fallback defaults
     * suitable for a standard local development setup.
     *
     * @return DatabaseConfig
     */
    public static function getDatabaseConfig(): DatabaseConfig
    {
        /**
         * =========================================================================
         * Database Configuration
         * =========================================================================
         *
         * Wingman provides three ways to configure your database connection
         * depending on your environment. Choose one and remove the others.
         *
         * -------------------------------------------------------------------------
         * Option 1 — fromDefault()
         * -------------------------------------------------------------------------
         * Connects using sensible local defaults. No setup needed.
         * Good for getting started quickly in development.
         *
         *   hostname      → localhost
         *   username      → root
         *   password      → (empty)
         *   database_name → app-database
         *
         * -------------------------------------------------------------------------
         * Option 2 — fromCustom(hostname, username, password, database_name)
         * -------------------------------------------------------------------------
         * Connects using explicit credentials. Use this when your local database
         * setup differs from the defaults (e.g. a different port, username, or name).
         *
         *   $dbConfig = DatabaseConfig::fromCustom('localhost', 'john', 'secret', 'wingman');
         *
         * -------------------------------------------------------------------------
         * Option 3 — fromEnv()                                    ← recommended for prod
         * -------------------------------------------------------------------------
         * Reads credentials from environment variables set in your .env file
         * or directly on your server / Docker container. Keeps sensitive
         * credentials out of your codebase entirely.
         *
         * Required environment variables:
         *   DB_HOST   → database hostname
         *   DB_USER   → database username
         *   DB_PASS   → database password
         *   DB_NAME   → database name
         *
         *   $dbConfig = DatabaseConfig::fromEnv();
         */
        
        return DatabaseConfig::fromCustom(
            Env::get('DB_HOST', 'localhost'),
            Env::get('DB_USER', 'root'),
            Env::get('DB_PASS', ''),
            Env::get('DB_NAME', 'app-database'),
        );
    }
}