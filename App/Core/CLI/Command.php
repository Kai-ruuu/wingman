<?php

namespace Wingman\Core\CLI;

use Wingman\Config\Globals;
use Wingman\Config\Models;
use Wingman\Config\PrivateGlobals;
use Wingman\Config\Seeders;
use Wingman\Core\App\Database;
use Wingman\Core\App\DatabaseConfig;
use Wingman\Core\App\Logger;

/**
 * Command
 *
 * The central command handler for Wingman's PHP-based CLI tool.
 * Each public static method maps directly to a CLI command invoked via:
 *
 *   php wing <command> [flags]
 *
 * Commands are registered in the $commands array, which defines their
 * description and accepted flags. This registry is used by the help
 * command to generate the CLI usage output dynamically.
 *
 * Available commands:
 *   help             Display all available commands and their flags
 *   serve            Start the PHP development server
 *   build            Create a model's database table
 *   demolish         Drop a model's database table
 *   seed             Run one or all seeders
 *   make-model       Scaffold a new model file
 *   make-controller  Scaffold a new controller file
 *   make-router      Scaffold a new router file
 *   make-middleware  Scaffold a new middleware file
 *   make-seeder      Scaffold a new seeder file
 */
class Command
{
    /**
     * Registry of all available CLI commands and their metadata.
     *
     * Each entry defines the command's description and its accepted flags.
     * Flags include a description, a value type ('string' or 'none'), and
     * whether they accept a value ('no-val' => true means the flag is a
     * boolean switch with no accompanying value).
     *
     * This registry is used by help() to render the CLI usage output.
     *
     * @var array<string, array{desc: string, flags: array}>
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
     * Displays all registered commands and their flags in the terminal.
     *
     * Iterates over the $commands registry and prints each command name,
     * description, and available flags with their descriptions. Output is
     * color-coded using the Colorizer utility for readability.
     *
     * Usage:
     *   php wing help
     *
     * @param array $flags Unused. Accepted for signature consistency with other commands.
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
     * The server serves files from the Public/ directory.
     *
     * Usage:
     *   php wing serve
     *   php wing serve --host=0.0.0.0 --port=9000
     *
     * @param array $flags Accepted flags: --host (string), --port (string)
     */
    public static function serve(array $flags): void
    {
        $host = $flags['--host'] ?? 'localhost';
        $port = $flags['--port'] ?? '8000';

        Logger::success("Starting development server...");
        Logger::debug("Listening on http://{$host}:{$port}");
        Logger::debug("Press Ctrl+C to stop.");

        $cmd = sprintf('php -S %s:%s -t Public', $host, $port);

        passthru($cmd);
    }

    /**
     * Scaffolds a new model file from the kit template.
     *
     * Replaces the template placeholder class name with the provided name,
     * and infers the table name by lowercasing and pluralizing if needed.
     * Appends 'Model' to the name if not already present.
     * Halts with an error if --name is missing or the file already exists.
     *
     * Usage:
     *   php wing make-model --name=Post
     *
     * @param array $flags Required flags: --name (string)
     */
    public static function makeModel(array $flags): void
    {
        $name = $flags['--name'] ?? null;

        if (!$name)
        {
            Logger::error("--name is required.");
            die;
        }

        if (!is_dir(Globals::getDir('APP_MODELS')))
            mkdir(Globals::getDir('APP_MODELS'), 0777, true);

        // Infer the table name from the model name
        $table = str_ends_with($name, 's') ? $name : $name . 's';
        $table = strtolower($table);

        // Ensure the class name ends with 'Model'
        $name     = !str_ends_with($name, 'Model') ? $name . 'Model' : $name;
        $content  = file_get_contents(Globals::getPath('KIT_MODEL'));
        $content  = str_replace('SampleModel', $name, $content);
        $content  = str_replace('samples', $table, $content);
        $fileName = $name . '.php';
        $path     = Globals::getDir('APP_MODELS') . '/' . $fileName;

        if (file_exists($path))
        {
            Logger::error("{$fileName} already exists.");
            die;
        }

        file_put_contents($path, $content);
        Logger::success('The new model is located at App/Models/' . $fileName);
    }

    /**
     * Scaffolds a new seeder file from the kit template.
     *
     * Replaces the template placeholder class name with the provided name.
     * Appends 'Seeder' to the name if not already present.
     * Halts with an error if --name is missing or the file already exists.
     *
     * Usage:
     *   php wing make-seeder --name=Post
     *
     * @param array $flags Required flags: --name (string)
     */
    public static function makeSeeder(array $flags): void
    {
        $name = $flags['--name'] ?? null;

        if (!$name)
        {
            Logger::error("--name is required.");
            die;
        }

        if (!is_dir(Globals::getDir('APP_SEEDERS')))
            mkdir(Globals::getDir('APP_SEEDERS'), 0777, true);

        // Ensure the class name ends with 'Seeder'
        $name     = !str_ends_with($name, 'Seeder') ? $name . 'Seeder' : $name;
        $content  = file_get_contents(Globals::getPath('KIT_SEEDER'));
        $content  = str_replace('SampleSeeder', $name, $content);
        $fileName = $name . '.php';
        $path     = Globals::getDir('APP_SEEDERS') . '/' . $fileName;

        if (file_exists($path))
        {
            Logger::error("{$fileName} already exists.");
            die;
        }

        file_put_contents($path, $content);
        Logger::success('The new seeder is located at App/Seeders/' . $fileName);
    }

    /**
     * Scaffolds a new controller file from the kit template.
     *
     * Replaces the template placeholder class name with the provided name.
     * Appends 'Controller' to the name if not already present.
     * Halts with an error if --name is missing or the file already exists.
     *
     * Usage:
     *   php wing make-controller --name=Post
     *
     * @param array $flags Required flags: --name (string)
     */
    public static function makeController(array $flags): void
    {
        $name = $flags['--name'] ?? null;

        if (!$name)
        {
            Logger::error("--name is required.");
            die;
        }

        if (!is_dir(Globals::getDir('APP_CONTROLLERS')))
            mkdir(Globals::getDir('APP_CONTROLLERS'), 0777, true);

        // Ensure the class name ends with 'Controller'
        $name     = !str_ends_with($name, 'Controller') ? $name . 'Controller' : $name;
        $content  = file_get_contents(Globals::getPath('KIT_CONTROLLER'));
        $content  = str_replace('SampleController', $name, $content);
        $fileName = $name . '.php';
        $path     = Globals::getDir('APP_CONTROLLERS') . '/' . $fileName;

        if (file_exists($path))
        {
            Logger::error("{$fileName} already exists.");
            die;
        }

        file_put_contents($path, $content);
        Logger::success('The new controller is located at App/Controllers/' . $fileName);
    }

    /**
     * Scaffolds a new middleware file from the kit template.
     *
     * Replaces the template placeholder class name with the provided name.
     * Appends 'Middleware' to the name if not already present.
     * Halts with an error if --name is missing or the file already exists.
     *
     * Usage:
     *   php wing make-middleware --name=Auth
     *
     * @param array $flags Required flags: --name (string)
     */
    public static function makeMiddleware(array $flags): void
    {
        $name = $flags['--name'] ?? null;

        if (!$name)
        {
            Logger::error("--name is required.");
            die;
        }

        if (!is_dir(Globals::getDir('APP_MIDDLEWARES')))
            mkdir(Globals::getDir('APP_MIDDLEWARES'), 0777, true);

        // Ensure the class name ends with 'Middleware'
        $name     = !str_ends_with($name, 'Middleware') ? $name . 'Middleware' : $name;
        $content  = file_get_contents(Globals::getPath('KIT_MIDDLEWARE'));
        $content  = str_replace('SampleMiddleware', $name, $content);
        $fileName = $name . '.php';
        $path     = Globals::getDir('APP_MIDDLEWARES') . '/' . $fileName;

        if (file_exists($path))
        {
            Logger::error("{$fileName} already exists.");
            die;
        }

        file_put_contents($path, $content);
        Logger::success('The new middleware is located at App/Middleware/' . $fileName);
    }

    /**
     * Scaffolds a new router file from the kit template.
     *
     * Replaces the template placeholder class name with the provided name.
     * Appends 'Router' to the name if not already present.
     * Halts with an error if --name is missing or the file already exists.
     *
     * Usage:
     *   php wing make-router --name=Post
     *
     * @param array $flags Required flags: --name (string)
     */
    public static function makeRouter(array $flags): void
    {
        $name = $flags['--name'] ?? null;

        if (!$name)
        {
            Logger::error("--name is required.");
            die;
        }

        if (!is_dir(Globals::getDir('APP_ROUTERS')))
            mkdir(Globals::getDir('APP_ROUTERS'), 0777, true);

        // Ensure the class name ends with 'Router'
        $name     = !str_ends_with($name, 'Router') ? $name . 'Router' : $name;
        $content  = file_get_contents(Globals::getPath('KIT_ROUTER'));
        $content  = str_replace('SampleRouter', $name, $content);
        $fileName = $name . '.php';
        $path     = Globals::getDir('APP_ROUTERS') . '/' . $fileName;

        if (file_exists($path))
        {
            Logger::error("{$fileName} already exists.");
            die;
        }

        file_put_contents($path, $content);
        Logger::success('The new router is located at App/Routers/' . $fileName);
    }

    /**
     * Runs one or all registered seeders against the database.
     *
     * When --all is passed, all seeders registered in the Seeders config
     * are run in order. When --seeder is passed, only the specified seeder
     * is instantiated and run. Appends 'Seeder' to the name if not present.
     * Halts with an error if the seeder class cannot be found.
     *
     * Usage:
     *   php wing seed --all
     *   php wing seed --seeder=Post
     *
     * @param array $flags Accepted flags: --all (switch), --seeder (string)
     */
    public static function seed(array $flags): void
    {
        $seedAll = array_key_exists('--all', $flags);

        if ($seedAll)
        {
            $dbConfig = PrivateGlobals::getDatabaseConfig();
            $database = Database::fromConfig($dbConfig);
            Seeders::withDatabase($database)->seedAll();
            return;
        }

        $name = $flags['--seeder'] ?? null;

        if (!$name)
        {
            Logger::error("--seeder is required.");
            die;
        }

        // Ensure the class name ends with 'Seeder'
        $name        = !str_ends_with($name, 'Seeder') ? $name . 'Seeder' : $name;
        $dbConfig    = PrivateGlobals::getDatabaseConfig();
        $database    = Database::fromConfig($dbConfig);
        $seederClass = "Wingman\\Seeders\\" . $name;

        if (!class_exists($seederClass))
        {
            Logger::error("Seeder class '{$seederClass}' not found.");
            die;
        }

        $seeder = new $seederClass($database);
        $seeder->describe();
        $seeder->seed();
    }

    /**
     * Creates one or all model database tables.
     *
     * When --all is passed, all models registered in the Models config
     * have their tables created. When --model is passed, only the specified
     * model's table is created. Passing --schema additionally prints the
     * generated SQL schema to the terminal after creation.
     * Appends 'Model' to the name if not present.
     * Halts with an error if the model class cannot be found.
     *
     * Usage:
     *   php wing build --all
     *   php wing build --model=Post
     *   php wing build --model=Post --schema
     *
     * @param array $flags Accepted flags: --all (switch), --model (string), --schema (switch)
     */
    public static function build(array $flags): void
    {
        $buildAll   = array_key_exists('--all', $flags);
        $showSchema = array_key_exists('--schema', $flags);

        if ($buildAll)
        {
            $dbConfig = PrivateGlobals::getDatabaseConfig();
            $database = Database::fromConfig($dbConfig);
            Models::withDatabase($database)->buildAll($showSchema);
            return;
        }

        $modelName = $flags['--model'] ?? null;

        if (!$modelName)
        {
            Logger::error("--model is required");
            die;
        }

        // Ensure the class name ends with 'Model'
        $modelName  = !str_ends_with($modelName, 'Model') ? $modelName . 'Model' : $modelName;
        $dbConfig   = PrivateGlobals::getDatabaseConfig();
        $database   = Database::fromConfig($dbConfig);
        $modelClass = "Wingman\\Models\\" . $modelName;

        if (!class_exists($modelClass))
        {
            Logger::error("Model class '{$modelClass}' not found.");
            die;
        }

        $model = new $modelClass($database);
        $model->describe();
        $model->build($showSchema);
    }

    /**
     * Drops one or all model database tables.
     *
     * When --all is passed, all models registered in the Models config
     * have their tables dropped. When --model is passed, only the specified
     * model's table is dropped.
     * Appends 'Model' to the name if not present.
     * Halts with an error if the model class cannot be found.
     *
     * Usage:
     *   php wing demolish --all
     *   php wing demolish --model=Post
     *
     * @param array $flags Accepted flags: --all (switch), --model (string)
     */
    public static function demolish(array $flags): void
    {
        $demolishAll = array_key_exists('--all', $flags);

        if ($demolishAll)
        {
            $dbConfig = PrivateGlobals::getDatabaseConfig();
            $database = Database::fromConfig($dbConfig);
            Models::withDatabase($database)->demolishAll();
            return;
        }

        $modelName = $flags['--model'] ?? null;

        if (!$modelName)
        {
            Logger::error("--model is required");
            die;
        }

        // Ensure the class name ends with 'Model'
        $modelName  = !str_ends_with($modelName, 'Model') ? $modelName . 'Model' : $modelName;
        $dbConfig   = PrivateGlobals::getDatabaseConfig();
        $database   = Database::fromConfig($dbConfig);
        $modelClass = "Wingman\\Models\\" . $modelName;

        if (!class_exists($modelClass))
        {
            Logger::error("Model class '{$modelClass}' not found.");
            die;
        }

        $model = new $modelClass($database);
        $model->demolish();
    }
}