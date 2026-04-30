<?php

namespace Wingman\Core\App;

class Env
{
    /**
     * Loads a .env file and populates $_ENV and getenv().
     * Call this once at the very top of your entry point (index.php).
     */
    public static function load(string $path = __DIR__ . '/../../../.env'): void
    {
        if (!file_exists($path))
        {
            Logger::error(".env file not found at {$path}");
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line)
        {
            // Skip comments
            if (str_starts_with(trim($line), '#')) continue;

            [$key, $value] = explode('=', $line, 2);

            $key   = trim($key);
            $value = trim($value);

            // Strip surrounding quotes if present
            $value = trim($value, '"\'');

            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }

    /**
     * Retrieves a value from the environment.
     * Returns $default if the key is not set.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? getenv($key) ?: $default;
    }
}