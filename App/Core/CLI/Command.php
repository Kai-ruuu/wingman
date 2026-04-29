<?php

namespace Wingman\Core\CLI;

use Wingman\Config\Models;
use Wingman\Config\Seeders;
use Wingman\Core\App\Database;
use Wingman\Core\App\DatabaseConfig;

/**
 * Path constants for app directories — used by make-* commands
 * to determine where to write newly scaffolded files.
 */
define('APP_CONTROLLERS_PATH', __DIR__ . '/../../Controllers');
define('APP_MIDDLEWARES_PATH', __DIR__ . '/../../Middlewares');
define('APP_ROUTERS_PATH',     __DIR__ . '/../../Routers');
define('APP_MODELS_PATH',      __DIR__ . '/../../Models');
define('APP_SEEDERS_PATH',     __DIR__ . '/../../Seeders');

/**
 * Path constants for kit (sample/template) files — used by make-* commands
 * as the source templates when scaffolding new files.
 */
define('KIT_CONTROLLER_PATH', __DIR__ . '/../App/Kit/SampleController.php');
define('KIT_MIDDLEWARE_PATH', __DIR__ . '/../App/Kit/SampleMiddleware.php');
define('KIT_ROUTER_PATH',     __DIR__ . '/../App/Kit/SampleRouter.php');
define('KIT_MODEL_PATH',      __DIR__ . '/../App/Kit/SampleModel.php');
define('KIT_SEEDER_PATH',     __DIR__ . '/../App/Kit/SampleSeeder.php');

/**
 * Command
 *
 * Defines and implements all available wing CLI commands.
 * Each command is a static method that receives a parsed flags array
 * and performs its action, printing feedback to the terminal.
 *
 * Commands are registered in the $commands manifest, which drives
 * both the help output and flag validation in CommandHandler.
 *
 * Available commands:
 *   help              → Lists all commands and their flags
 *   serve             → Starts the PHP development server
 *   seed              → Runs one or all seeders
 *   build             → Creates one or all model tables
 *   demolish          → Drops one or all model tables
 *   make-model        → Scaffolds a new model file
 *   make-seeder       → Scaffolds a new seeder file
 *   make-controller   → Scaffolds a new controller file
 *   make-router       → Scaffolds a new router file
 *   make-middleware   → Scaffolds a new middleware file
 */
class Command
{
    /**
     * The command manifest — defines every available CLI command and its flags.
     *
     * Each entry contains:
     *   'desc'  → Human-readable description shown in the help output
     *   'flags' → Associative array of supported flags, each with:
     *               'desc'   → Description shown in help
     *               'type'   → Expected value type ('string' or 'none')
     *               'no-val' → true if the flag is a boolean switch (no value required)
     */
    public static array $commands = [
        'help' => [
            'desc'  => "Displays wing commands' information",
            'flags' => []
        ],
        'build' => [
            'desc'  => "Creates models' tables",
            'flags' => [
                '--all' => [
                    'desc'   => "Create all existing models' tables",
                    'type'   => 'none',
                    'no-val' => true
                ],
                '--schema' => [
                    'desc'   => 'Print the model schema upon creation',
                    'type'   => 'none',
                    'no-val' => true
                ],
                '--model' => [
                    'desc'   => 'Specify model name',
                    'type'   => 'string',
                    'no-val' => false
                ]
            ]
        ],
        'demolish' => [
            'desc'  => "Drops models' tables",
            'flags' => [
                '--all' => [
                    'desc'   => "Drop all existing models' tables",
                    'type'   => 'none',
                    'no-val' => true
                ],
                '--model' => [
                    'desc'   => 'Specify model name',
                    'type'   => 'string',
                    'no-val' => false
                ]
            ]
        ],
        'seed' => [
            'desc'  => "Runs seeders",
            'flags' => [
                '--all' => [
                    'desc'   => 'Run all existing seeders',
                    'type'   => 'none',
                    'no-val' => true
                ],
                '--seeder' => [
                    'desc'   => 'Specify seeder name',
                    'type'   => 'string',
                    'no-val' => false
                ]
            ]
        ],
        'serve' => [
            'desc'  => 'Serve the application',
            'flags' => [
                '--host' => [
                    'desc'   => 'Specify host name',
                    'type'   => 'string',
                    'no-val' => false
                ],
                '--port' => [
                    'desc'   => 'Specify port number',
                    'type'   => 'string',
                    'no-val' => false
                ],
            ]
        ],
        'make-model' => [
            'desc'  => 'Creates a new model',
            'flags' => [
                '--name' => [
                    'desc'   => 'Specify model name',
                    'type'   => 'string',
                    'no-val' => false
                ]
            ]
        ],
        'make-seeder' => [
            'desc'  => 'Creates a new seeder',
            'flags' => [
                '--name' => [
                    'desc'   => 'Specify seeder name',
                    'type'   => 'string',
                    'no-val' => false
                ]
            ]
        ],
        'make-controller' => [
            'desc'  => 'Creates a new controller',
            'flags' => [
                '--name' => [
                    'desc'   => 'Specify controller name',
                    'type'   => 'string',
                    'no-val' => false
                ]
            ]
        ],
        'make-router' => [
            'desc'  => 'Creates a new router',
            'flags' => [
                '--name' => [
                    'desc'   => 'Specify router name',
                    'type'   => 'string',
                    'no-val' => false
                ]
            ]
        ],
        'make-middleware' => [
            'desc'  => 'Creates a new middleware',
            'flags' => [
                '--name' => [
                    'desc'   => 'Specify middleware name',
                    'type'   => 'string',
                    'no-val' => false
                ]
            ]
        ]
    ];

    /**
     * Prints all available commands and their flags to the terminal.
     * Automatically driven by the $commands manifest — no manual updates needed
     * when new commands are added.
     *
     * Output format:
     *   command-name    Description
     *     --flag        Flag description
     *
     * @param array $flags Unused — accepted for consistent method signature
     */
    public static function help(array $flags): void
    {
        echo Colorizer::yellow("Wingman Framework CLI") . Colorizer::reset(PHP_EOL . PHP_EOL);

        foreach (self::$commands as $command => $info)
        {
            echo Colorizer::cyan(str_pad($command, 16)) . Colorizer::reset($info['desc'] . PHP_EOL);

            foreach ($info['flags'] as $flag => $flagInfo)
            {
                echo Colorizer::gray("  " . str_pad($flag, 14)) . Colorizer::reset($flagInfo['desc'] . PHP_EOL);
            }

            echo PHP_EOL;
        }
    }

    /**
     * Starts the PHP built-in development server.
     *
     * Defaults to localhost:8000 if no host or port flags are provided.
     * Serves files from the Public/ directory. Blocks until interrupted
     * with Ctrl+C.
     *
     * @param array $flags Supported flags: --host, --port
     */
    public static function serve(array $flags): void
    {
        $host = $flags['--host'] ?? 'localhost';
        $port = $flags['--port'] ?? '8000';

        echo Colorizer::green("Starting development server..." . PHP_EOL);
        echo Colorizer::gray("Listening on http://{$host}:{$port}" . PHP_EOL);
        echo Colorizer::gray("Press Ctrl+C to stop.") . Colorizer::reset(PHP_EOL);

        $cmd = sprintf('php -S %s:%s -t Public', $host, $port);

        passthru($cmd);
    }

    /**
     * Scaffolds a new model file from the SampleModel kit template.
     *
     * - Requires the --name flag
     * - Automatically appends 'Model' to the name if not already present
     * - Terminates with an error if --name is missing or the file already exists
     * - Writes the new file to App/Models/
     *
     * @param array $flags Supported flags: --name (required)
     */
    public static function makeModel(array $flags): void
    {
        $name = $flags['--name'] ?? null;

        if (!$name)
            die(Colorizer::red("Error: --name is required") . Colorizer::reset(PHP_EOL));

        $name     = !str_ends_with($name, 'Model') ? $name . 'Model' : $name;
        $content  = file_get_contents(KIT_MODEL_PATH);
        $content  = str_replace('SampleModel', $name, $content);
        $fileName = $name . '.php';
        $path     = APP_MODELS_PATH . '/' . $fileName;

        if (file_exists($path))
            die(Colorizer::red("Error: {$fileName} already exists") . Colorizer::reset(PHP_EOL));

        file_put_contents($path, $content);
        echo Colorizer::green('The new model is located at App/Models/' . $fileName) . Colorizer::reset(PHP_EOL);
    }

    /**
     * Scaffolds a new seeder file from the SampleSeeder kit template.
     *
     * - Requires the --name flag
     * - Automatically appends 'Seeder' to the name if not already present
     * - Terminates with an error if --name is missing or the file already exists
     * - Writes the new file to App/Seeders/
     *
     * @param array $flags Supported flags: --name (required)
     */
    public static function makeSeeder(array $flags): void
    {
        $name = $flags['--name'] ?? null;

        if (!$name)
            die(Colorizer::red("Error: --name is required") . Colorizer::reset(PHP_EOL));

        $name     = !str_ends_with($name, 'Seeder') ? $name . 'Seeder' : $name;
        $content  = file_get_contents(KIT_SEEDER_PATH);
        $content  = str_replace('SampleSeeder', $name, $content);
        $fileName = $name . '.php';
        $path     = APP_SEEDERS_PATH . '/' . $fileName;

        if (file_exists($path))
            die(Colorizer::red("Error: {$fileName} already exists") . Colorizer::reset(PHP_EOL));

        file_put_contents($path, $content);
        echo Colorizer::green('The new seeder is located at App/Seeders/' . $fileName) . Colorizer::reset(PHP_EOL);
    }

    /**
     * Scaffolds a new controller file from the SampleController kit template.
     *
     * - Requires the --name flag
     * - Automatically appends 'Controller' to the name if not already present
     * - Terminates with an error if --name is missing or the file already exists
     * - Writes the new file to App/Controllers/
     *
     * @param array $flags Supported flags: --name (required)
     */
    public static function makeController(array $flags): void
    {
        $name = $flags['--name'] ?? null;

        if (!$name)
            die(Colorizer::red("Error: --name is required") . Colorizer::reset(PHP_EOL));

        $name     = !str_ends_with($name, 'Controller') ? $name . 'Controller' : $name;
        $content  = file_get_contents(KIT_CONTROLLER_PATH);
        $content  = str_replace('SampleController', $name, $content);
        $fileName = $name . '.php';
        $path     = APP_CONTROLLERS_PATH . '/' . $fileName;

        if (file_exists($path))
            die(Colorizer::red("Error: {$fileName} already exists") . Colorizer::reset(PHP_EOL));

        file_put_contents($path, $content);
        echo Colorizer::green('The new controller is located at App/Controllers/' . $fileName) . Colorizer::reset(PHP_EOL);
    }

    /**
     * Scaffolds a new middleware file from the SampleMiddleware kit template.
     *
     * - Requires the --name flag
     * - Automatically appends 'Middleware' to the name if not already present
     * - Terminates with an error if --name is missing or the file already exists
     * - Writes the new file to App/Middlewares/
     *
     * @param array $flags Supported flags: --name (required)
     */
    public static function makeMiddleware(array $flags): void
    {
        $name = $flags['--name'] ?? null;

        if (!$name)
            die(Colorizer::red("Error: --name is required") . Colorizer::reset(PHP_EOL));

        $name     = !str_ends_with($name, 'Middleware') ? $name . 'Middleware' : $name;
        $content  = file_get_contents(KIT_MIDDLEWARE_PATH);
        $content  = str_replace('SampleMiddleware', $name, $content);
        $fileName = $name . '.php';
        $path     = APP_MIDDLEWARES_PATH . '/' . $fileName;

        if (file_exists($path))
            die(Colorizer::red("Error: {$fileName} already exists") . Colorizer::reset(PHP_EOL));

        file_put_contents($path, $content);
        echo Colorizer::green('The new middleware is located at App/Middleware/' . $fileName) . Colorizer::reset(PHP_EOL);
    }

    /**
     * Scaffolds a new router file from the SampleRouter kit template.
     *
     * - Requires the --name flag
     * - Automatically appends 'Router' to the name if not already present
     * - Terminates with an error if --name is missing or the file already exists
     * - Writes the new file to App/Routers/
     *
     * @param array $flags Supported flags: --name (required)
     */
    public static function makeRouter(array $flags): void
    {
        $name = $flags['--name'] ?? null;

        if (!$name)
            die(Colorizer::red("Error: --name is required") . Colorizer::reset(PHP_EOL));

        $name     = !str_ends_with($name, 'Router') ? $name . 'Router' : $name;
        $content  = file_get_contents(KIT_ROUTER_PATH);
        $content  = str_replace('SampleRouter', $name, $content);
        $fileName = $name . '.php';
        $path     = APP_ROUTERS_PATH . '/' . $fileName;

        if (file_exists($path))
            die(Colorizer::red("Error: {$fileName} already exists") . Colorizer::reset(PHP_EOL));

        file_put_contents($path, $content);
        echo Colorizer::green('The new router is located at App/Routers/' . $fileName) . Colorizer::reset(PHP_EOL);
    }

    /**
     * Runs one or all seeders against the database.
     *
     * - Pass --all to run every seeder registered in Wingman\Config\Seeders
     * - Pass --seeder=User to run a specific seeder (e.g. UserSeeder)
     * - Automatically appends 'Seeder' to the name if not already present
     * - Terminates with an error if --seeder is missing (when --all is not passed)
     * - Terminates with an error if the resolved seeder class does not exist
     *
     * Note: Update the DatabaseConfig method here to match your environment
     * if you're not using the default local setup.
     *
     * @param array $flags Supported flags: --all (no value), --seeder=Name (required if --all absent)
     */
    public static function seed(array $flags): void
    {
        $seedAll = array_key_exists('--all', $flags);

        if ($seedAll)
        {
            $dbConfig = DatabaseConfig::fromDefault();
            $database = Database::fromConfig($dbConfig);
            Seeders::withDatabase($database)->seedAll();
            return;
        }

        $name = $flags['--seeder'] ?? null;

        if (!$name)
            die(Colorizer::red("Error: --seeder is required") . Colorizer::reset(PHP_EOL));

        $name        = !str_ends_with($name, 'Seeder') ? $name . 'Seeder' : $name;
        $dbConfig    = DatabaseConfig::fromDefault();
        $database    = Database::fromConfig($dbConfig);
        $seederClass = "Wingman\\Seeders\\" . $name;

        if (!class_exists($seederClass))
            die(Colorizer::red("Seeder class '{$seederClass}' not found.") . Colorizer::reset(PHP_EOL));

        $seeder = new $seederClass($database);
        $seeder->describe();
        $seeder->seed();
    }

    /**
     * Creates one or all model tables in the database.
     *
     * - Pass --all to build every model registered in Wingman\Config\Models
     * - Pass --model=User to build a specific model table (e.g. UserModel)
     * - Pass --schema alongside either flag to print the SQL schema upon creation
     * - Automatically appends 'Model' to the name if not already present
     * - Terminates with an error if --model is missing (when --all is not passed)
     * - Terminates with an error if the resolved model class does not exist
     *
     * Note: Update the DatabaseConfig method here to match your environment
     * if you're not using the default local setup.
     *
     * @param array $flags Supported flags: --all (no value), --model=Name (required if --all absent), --schema (no value, optional)
     */
    public static function build(array $flags): void
    {
        $buildAll   = array_key_exists('--all', $flags);
        $showSchema = array_key_exists('--schema', $flags);

        if ($buildAll)
        {
            $dbConfig = DatabaseConfig::fromDefault();
            $database = Database::fromConfig($dbConfig);
            Models::withDatabase($database)->buildAll($showSchema);
            return;
        }

        $modelName = $flags['--model'] ?? null;

        if (!$modelName)
            die(Colorizer::red("Error: --model is required") . Colorizer::reset(PHP_EOL));

        $modelName  = !str_ends_with($modelName, 'Model') ? $modelName . 'Model' : $modelName;
        $dbConfig   = DatabaseConfig::fromDefault();
        $database   = Database::fromConfig($dbConfig);
        $modelClass = "Wingman\\Models\\" . $modelName;

        if (!class_exists($modelClass))
            die(Colorizer::red("Model class '{$modelClass}' not found.") . Colorizer::reset(PHP_EOL));

        $model = new $modelClass($database);
        $model->describe();
        $model->build($showSchema);
    }

    /**
     * Drops one or all model tables from the database.
     *
     * - Pass --all to drop every model table registered in Wingman\Config\Models
     * - Pass --model=User to drop a specific model table (e.g. UserModel)
     * - Automatically appends 'Model' to the name if not already present
     * - Terminates with an error if --model is missing (when --all is not passed)
     * - Terminates with an error if the resolved model class does not exist
     * - Tables are dropped using DROP TABLE IF EXISTS — no error for missing tables
     *
     * Note: Update the DatabaseConfig method here to match your environment
     * if you're not using the default local setup.
     *
     * @param array $flags Supported flags: --all (no value), --model=Name (required if --all absent)
     */
    public static function demolish(array $flags): void
    {
        $demolishAll = array_key_exists('--all', $flags);

        if ($demolishAll)
        {
            $dbConfig = DatabaseConfig::fromDefault();
            $database = Database::fromConfig($dbConfig);
            Models::withDatabase($database)->demolishAll();
            return;
        }

        $modelName = $flags['--model'] ?? null;

        if (!$modelName)
            die(Colorizer::red("Error: --model is required") . Colorizer::reset(PHP_EOL));

        $modelName  = !str_ends_with($modelName, 'Model') ? $modelName . 'Model' : $modelName;
        $dbConfig   = DatabaseConfig::fromDefault();
        $database   = Database::fromConfig($dbConfig);
        $modelClass = "Wingman\\Models\\" . $modelName;

        if (!class_exists($modelClass))
            die(Colorizer::red("Model class '{$modelClass}' not found.") . Colorizer::reset(PHP_EOL));

        $model = new $modelClass($database);
        $model->demolish();
    }
}