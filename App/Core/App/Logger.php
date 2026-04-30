<?php

namespace Wingman\Core\App;

use Wingman\Config\Globals;
use Wingman\Core\CLI\Colorizer;

/**
 * Static logger for writing leveled log messages to disk and stderr.
 *
 * Each log entry is written to a daily rotating log file under the configured
 * logs directory, and echoed to stderr via error_log() with ANSI color coding
 * for visibility in CLI and server output.
 *
 * Log format: [HH:MM:SS] [LEVEL]       <message>
 */
class Logger
{
    /**
     * Persists a log entry to the daily log file.
     *
     * Creates the log directory if it does not exist. Log files are named
     * by date (log-YYYY-MM-DD.log) and written to with an exclusive lock
     * to prevent corruption from concurrent requests.
     *
     * @param string $level   The log level label, e.g. '[INFO]' or '[ERROR]'.
     * @param string $message The message to log.
     */
    private static function save(string $level, string $message): void
    {
        if (!is_dir(Globals::getDir('LOGS')))
            mkdir(Globals::getDir('LOGS'), 0777, true);

        $date = date("Y-m-d");
        $time = date("H:i:s");

        $logFileName = "log-{$date}.log";
        $logFilePath = Globals::getDir('LOGS') . "/{$logFileName}";

        // Pad the timestamp + level prefix to a fixed width for aligned columns.
        $formatted = str_pad("[{$time}] {$level}", 24) . $message . PHP_EOL;

        file_put_contents($logFilePath, $formatted, FILE_APPEND | LOCK_EX);
    }

    /**
     * Logs an informational message.
     * Use for general application events and lifecycle notices.
     */
    public static function info(string $message): void
    {
        date_default_timezone_set(Globals::$timezone);
        $time  = date("H:i:s");
        $level = '[INFO]';
        self::save($level, $message);
        error_log(str_pad("[{$time}] {$level}", 24) . $message);
    }

    /**
     * Logs a success message.
     * Use for confirming that an operation completed as expected.
     * Output is colored green in the terminal.
     */
    public static function success(string $message): void
    {
        date_default_timezone_set(Globals::$timezone);
        $time  = date("H:i:s");
        $level = '[SUCCESS]';
        self::save($level, $message);
        error_log(Colorizer::green(str_pad("[{$time}] {$level}", 24) . $message) . Colorizer::reset());
    }

    /**
     * Logs a warning message.
     * Use for recoverable issues or unexpected-but-handled conditions.
     * Output is colored yellow in the terminal.
     */
    public static function warning(string $message): void
    {
        date_default_timezone_set(Globals::$timezone);
        $time  = date("H:i:s");
        $level = '[WARNING]';
        self::save($level, $message);
        error_log(Colorizer::yellow(str_pad("[{$time}] {$level}", 24) . $message) . Colorizer::reset());
    }

    /**
     * Logs an error message.
     * Use for failures that affect the current request or operation.
     * Output is colored red in the terminal.
     */
    public static function error(string $message): void
    {
        date_default_timezone_set(Globals::$timezone);
        $time  = date("H:i:s");
        $level = '[ERROR]';
        self::save($level, $message);
        error_log(Colorizer::red(str_pad("[{$time}] {$level}", 24) . $message) . Colorizer::reset());
    }

    /**
     * Logs a debug message.
     * Use for verbose diagnostic output during development.
     * Output is colored gray in the terminal.
     */
    public static function debug(string $message): void
    {
        date_default_timezone_set(Globals::$timezone);
        $time  = date("H:i:s");
        $level = '[DEBUG]';
        self::save($level, $message);
        error_log(Colorizer::gray(str_pad("[{$time}] {$level}", 24) . $message) . Colorizer::reset());
    }
}