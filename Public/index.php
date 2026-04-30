<?php

use Wingman\Config\Globals;
use Wingman\Core\App\App;
use Wingman\Core\App\CorsHandler;
use Wingman\Core\App\Database;
use Wingman\Core\App\DatabaseConfig;
use Wingman\Core\App\Env;

require __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set(Globals::$timezone);

/**
 * =========================================================================
 * Environment
 * =========================================================================
 *
 * Loads key-value pairs from your .env file into the environment.
 * Must be called before anything else so all subsequent config reads
 * (especially DatabaseConfig::fromEnv()) have access to the variables.
 *
 * Make sure a .env file exists at the project root. Use .env.example
 * as a reference for the required keys.
 */
Env::load();

/**
 * =========================================================================
 * CORS Configuration
 * =========================================================================
 * 
 * Apply CORS headers before any routing or middleware logic runs.
 *
 * Restrict to specific origins and enable credentials for authenticated
 * cross-origin requests (e.g. from a separate frontend):
 *
 *   CorsHandler::build()
 *       ->withAllowedOrigins(['https://yourfrontend.com'])
 *       ->withAllowedMethods(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'])
 *       ->withAllowedHeaders(['Content-Type', 'Authorization', 'X-Requested-With'])
 *       ->withAllowedCredentials()
 *       ->withCachePreflight(86400)
 *       ->listen();
 *
 * Or use the shorthand for a wildcard, no-credentials setup (all defaults apply):
 */
CorsHandler::build()->listen();

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
$dbConfig = DatabaseConfig::fromDefault();

/**
 * =========================================================================
 * Database Connection
 * =========================================================================
 *
 * Establishes the mysqli connection from the config above.
 * The process terminates immediately if the connection fails,
 * printing a descriptive error to the console.
 */
$database = Database::fromConfig($dbConfig);

/**
 * =========================================================================
 * Application Bootstrap
 * =========================================================================
 *
 * Initializes the app with the database connection, registers your
 * routers, and starts listening for incoming HTTP requests.
 *
 * Add your routers to the withRouters() array to register their routes.
 * Each router must extend BaseRouter and implement a describe() method.
 *
 *   ->withRouters([
 *       AuthRouter::class,
 *       UserRouter::class,
 *       PostRouter::class,
 *   ])
 */
App::withDatabase($database)
    ->withRouters([
        // AuthRouter::class,
        // UserRouter::class,
    ])
    ->listen();