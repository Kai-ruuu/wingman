<?php

use Wingman\Config\Globals;
use Wingman\Config\PrivateGlobals;
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
 * Database Connection
 * =========================================================================
 *
 * Establishes the mysqli connection from the config above.
 * The process terminates immediately if the connection fails,
 * printing a descriptive error to the console.
 */
$config = PrivateGlobals::getDatabaseConfig();
$database = Database::fromConfig($config);

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