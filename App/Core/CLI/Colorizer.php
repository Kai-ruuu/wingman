<?php

namespace Wingman\Core\CLI;


define('C_RESET',  "\033[0m");
define('C_RED',    "\033[31m");
define('C_GREEN',  "\033[32m");
define('C_YELLOW', "\033[33m");
define('C_CYAN',   "\033[36m");
define('C_GRAY',   "\033[90m");


class Colorizer
{
    public static function reset(string $text = ''): string
    {
        return C_RESET . $text;
    }

    public static function red(string $text = ''): string
    {
        return C_RED . $text;
    }

    public static function green(string $text = ''): string
    {
        return C_GREEN . $text;
    }
    
    public static function yellow(string $text = ''): string
    {
        return C_YELLOW . $text;
    }
    
    public static function cyan(string $text = ''): string
    {
        return C_CYAN . $text;
    }
    
    public static function gray(string $text = ''): string
    {
        return C_GRAY . $text;
    }
}