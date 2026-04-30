<?php

namespace Wingman\Core\CLI;

use Wingman\Core\App\Logger;

/**
 * CommandHandler
 *
 * Extends Command to handle the parsing and dispatching of CLI input.
 * Acts as the entry point for the wing CLI tool — receives raw argv,
 * validates the command and flags, and routes execution to the appropriate
 * Command method.
 *
 * Responsibilities:
 * - Stripping the script name from argv
 * - Extracting and validating the command
 * - Parsing and validating flags via parseFlags()
 * - Dispatching to the correct Command method via handle()
 *
 * Usage (in wing):
 *
 *   CommandHandler::handle($argv);
 */
class CommandHandler extends Command
{
    /**
     * Parses raw flag strings from argv into a validated key-value array.
     *
     * Supports two flag formats:
     * - Value flags:   --flag=value  → stored as ['--flag' => 'value']
     * - Boolean flags: --flag        → stored as ['--flag' => null]
     *
     * Validation rules:
     * - A flag without '=' is only valid if it's registered as 'no-val' => true
     * - A flag with '=' must have a non-empty value (--flag= is rejected)
     * - Flags not listed in the command manifest are rejected
     *
     * Terminates with a red error message if any validation rule is violated.
     *
     * @param  string $command       The command name used to look up valid flags
     * @param  array  $unparsedFlags Raw flag strings from argv (e.g. ['--host=localhost', '--schema'])
     * @return array                 Validated associative array of flags and their values
     */
    public static function parseFlags(string $command, array $unparsedFlags): array
    {
        $flags = [];

        foreach ($unparsedFlags as $flagPair)
        {
            $noValue = false;

            // Check flag format — if no '=' is present, it must be a registered no-value flag
            if (!str_contains($flagPair, '='))
            {
                if (
                    !array_key_exists($flagPair, self::$commands[$command]['flags']) ||
                    !self::$commands[$command]['flags'][$flagPair]['no-val']
                )
                {
                    Logger::error("Invalid flag format in \"{$flagPair}\". Use --flag=value");
                    die;
                }

                $noValue = true;
            }

            if (!$noValue)
            {
                $pair = explode('=', $flagPair, 2);

                // Reject flags with missing or empty values (e.g. --host=)
                if (count($pair) <= 1 || empty($pair[1]))
                {
                    Logger::error("Incomplete flag \"{$flagPair}\"");
                    die;
                }

                [$flag, $value] = $pair;

                // Reject flags not registered in the command manifest
                if (!array_key_exists($flag, self::$commands[$command]['flags']))
                {
                    Logger::error("Unrecognized flag \"{$flag}\" for command \"{$command}\"");
                    die;
                }

                $flags[$flag] = $value;
            }
            else
            {
                // Boolean flag — store with null value to indicate presence
                $flags[$flagPair] = null;
            }
        }

        return $flags;
    }

    /**
     * Entry point for the wing CLI tool.
     *
     * Processes the raw argv array, extracts the command and flags,
     * validates them, and dispatches to the appropriate Command method.
     *
     * Steps:
     * 1. Strips the script name (argv[0]) from the args
     * 2. Extracts the command name (argv[1])
     * 3. Falls back to help if no command is provided
     * 4. Rejects unrecognized commands with a red error message
     * 5. Parses and validates remaining args as flags
     * 6. Dispatches to the matching Command method
     *
     * @param array $args The raw $argv array from the CLI entry point
     */
    public static function handle(array $args): void
    {
        // Strip the script name (e.g. 'wing') from the args
        array_shift($args);

        // Extract the command
        $command = array_shift($args);

        // Default to help if no command was provided
        if (!$command) {
            self::help([]);
            return;
        }

        // Reject unrecognized commands
        if (!array_key_exists($command, self::$commands))
        {
            Logger::error("Unrecognized command \"{$command}\"");
            die;
        }

        // Parse and validate flags from remaining args
        $flags = self::parseFlags($command, $args);

        // Dispatch to the appropriate command method
        match ($command)
        {
            'help'            => self::help($flags),
            'serve'           => self::serve($flags),
            'seed'            => self::seed($flags),
            'build'           => self::build($flags),
            'demolish'        => self::demolish($flags),
            'make-model'      => self::makeModel($flags),
            'make-seeder'     => self::makeSeeder($flags),
            'make-controller' => self::makeController($flags),
            'make-middleware' => self::makeMiddleware($flags),
            'make-router'     => self::makeRouter($flags),
        };
    }
}