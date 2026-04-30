<?php

namespace Wingman\Core\App;

use Wingman\Core\CLI\Colorizer;

define('LOGS_DIR', __DIR__ . '/../../../Logs');

class Logger
{
    private static function save(string $level, string $message): void
    {
        if (!is_dir(LOGS_DIR))
            mkdir(LOGS_DIR, 0777, true);
        
        $date = date("Y-m-d");
        $time = date("H:i:s");

        $logFileName = "log-{$date}.log";
        $logFilePath = LOGS_DIR . "/{$logFileName}";

        $formatted = str_pad("[{$time}] {$level}", 24) . $message . PHP_EOL;

        file_put_contents($logFilePath, $formatted, FILE_APPEND | LOCK_EX);
    }
    
    public static function info(string $message): void
    {
        $time = date("H:i:s");
        $level = '[INFO]';
        self::save($level, $message);
        echo str_pad("[{$time}] {$level}", 24) . $message . PHP_EOL;
    }
    
    public static function success(string $message): void
    {
        $time = date("H:i:s");
        $level = '[SUCCESS]';
        self::save($level, $message);
        echo Colorizer::green(str_pad("[{$time}] {$level}", 24) . $message . PHP_EOL) . Colorizer::reset();
    }

    public static function warning(string $message): void
    {
        $time = date("H:i:s");
        $level = '[WARNING]';
        self::save($level, $message);
        echo Colorizer::yellow(str_pad("[{$time}] {$level}", 24) . $message . PHP_EOL) . Colorizer::reset();
    }

    public static function error(string $message): void
    {
        $time = date("H:i:s");
        $level = '[ERROR]';
        self::save($level, $message);
        echo Colorizer::red(str_pad("[{$time}] {$level}", 24) . $message . PHP_EOL) . Colorizer::reset();
    }
        
    public static function debug(string $message): void
    {
        $time = date("H:i:s");
        $level = '[DEBUG]';
        self::save($level, $message);
        echo Colorizer::gray(str_pad("[{$time}] {$level}", 24) . $message . PHP_EOL) . Colorizer::reset();
    }
}