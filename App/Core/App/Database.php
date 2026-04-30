<?php

namespace Wingman\Core\App;

use mysqli;
use Wingman\Core\App\DatabaseConfig;

/**
 * Database
 *
 * A simple factory class for creating a mysqli database connection.
 * Takes a DatabaseConfig instance and returns a ready-to-use mysqli
 * connection, terminating the process with a descriptive error message
 * if the connection fails.
 *
 * Usage:
 *
 *   $config = DatabaseConfig::fromDefault();
 *   $db     = Database::fromConfig($config);
 *
 *   // Then pass $db to your app or models
 *   App::withDatabase($db)->withRouters([...])->listen();
 */
class Database
{
    /**
     * Creates and returns a mysqli connection from a DatabaseConfig instance.
     *
     * Attempts to connect using the hostname, username, password, and
     * database name from the provided config. If the connection fails,
     * the process is terminated immediately with a red error message
     * printed to the CLI.
     *
     * @param  DatabaseConfig $config The database connection configuration
     * @return mysqli                 A live, ready-to-use mysqli connection
     */
    public static function fromConfig(DatabaseConfig $config): mysqli
    {
        $connection = new mysqli(
            $config->hostname,
            $config->username,
            $config->password,
            $config->database_name
        );

        // Bail early if the connection could not be established
        if ($connection->connect_error)
        {
            Logger::error("Failed to connect to the database - {$connection->connect_error}");
            die;
        }

        return $connection;
    }
}