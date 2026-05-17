<?php

namespace Wingman\Core\Utils;

class Time
{
    public static function hoursToSeconds(int $hours = 1): int
    {
        return $hours * 3600;
    }

    public static function minutesToSeconds(int $minutes = 1): int
    {
        return $minutes * 60;
    }

    public static function daysToSeconds(int $days = 1): int
    {
        return $days * 86400;
    }

    public static function weeksToSeconds(int $weeks = 1): int
    {
        return $weeks * 604800;
    }

    public static function fromNowToSeconds(int $days = 0, int $hours = 0, int $minutes = 0): int
    {
        return time()
            + self::daysToSeconds($days)
            + self::hoursToSeconds($hours)
            + self::minutesToSeconds($minutes);
    }
}