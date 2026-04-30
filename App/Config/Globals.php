<?php

namespace Wingman\Config;

/**
 * Globals
 *
 * Central configuration class for application-wide constants and paths.
 * Provides a single source of truth for directory paths, file paths,
 * and global settings used across the Wingman framework.
 *
 * Paths are resolved relative to this file's location and should not
 * need to be changed unless the project structure is modified.
 */
class Globals
{
    /**
     * The default timezone used throughout the application.
     * Applied to all date/time functions via date_default_timezone_set().
     *
     * @see https://www.php.net/manual/en/timezones.php for a full list of supported timezones
     */
    public static string $timezone = 'Asia/Manila';

    /**
     * Registered application directories.
     *
     * These paths point to key directories used by the framework and CLI tools
     * for scaffolding, logging, rate limiting, and more.
     *
     * Keys:
     * - LOGS            : Where log files are written
     * - RATE_LIMITS     : Where rate limit tracking files are stored
     * - APP_CONTROLLERS : Where application controllers live
     * - APP_MIDDLEWARES : Where application middlewares live
     * - APP_ROUTERS     : Where application routers live
     * - APP_MODELS      : Where application models live
     * - APP_SEEDERS     : Where application seeders live
     */
    private static array $dirs = [
        'LOGS'            => __DIR__ . '/../../Logs',
        'RATE_LIMITS'     => __DIR__ . '/../../Tmp/RateLimits',
        'APP_CONTROLLERS' => __DIR__ . '/../Controllers',
        'APP_MIDDLEWARES' => __DIR__ . '/../Middlewares',
        'APP_ROUTERS'     => __DIR__ . '/../Routers',
        'APP_MODELS'      => __DIR__ . '/../Models',
        'APP_SEEDERS'     => __DIR__ . '/../Seeders',
    ];

    /**
     * Registered kit (scaffold template) file paths.
     *
     * These point to the stub files used by the CLI when generating
     * new controllers, middlewares, routers, models, and seeders.
     *
     * Keys:
     * - KIT_CONTROLLER : Stub file for generating a new controller
     * - KIT_MIDDLEWARE : Stub file for generating a new middleware
     * - KIT_ROUTER     : Stub file for generating a new router
     * - KIT_MODEL      : Stub file for generating a new model
     * - KIT_SEEDER     : Stub file for generating a new seeder
     */
    private static array $paths = [
        'KIT_CONTROLLER' => __DIR__ . '/../Core/App/Kit/SampleController.php',
        'KIT_MIDDLEWARE' => __DIR__ . '/../Core/App/Kit/SampleMiddleware.php',
        'KIT_ROUTER'     => __DIR__ . '/../Core/App/Kit/SampleRouter.php',
        'KIT_MODEL'      => __DIR__ . '/../Core/App/Kit/SampleModel.php',
        'KIT_SEEDER'     => __DIR__ . '/../Core/App/Kit/SampleSeeder.php',
    ];

    /**
     * Retrieves a registered directory path by key.
     *
     * @param  string      $key The directory key (e.g. 'LOGS', 'APP_CONTROLLERS')
     * @return string|null      The resolved directory path, or null if the key doesn't exist
     */
    public static function getDir(string $key): ?string
    {
        return self::$dirs[$key] ?? null;
    }

    /**
     * Retrieves a registered file path by key.
     *
     * @param  string      $key The path key (e.g. 'KIT_CONTROLLER', 'KIT_MODEL')
     * @return string|null      The resolved file path, or null if the key doesn't exist
     */
    public static function getPath(string $key): ?string
    {
        return self::$paths[$key] ?? null;
    }
}