<?php

namespace Wingman\Core\Utils;

use Wingman\Core\CLI\Colorizer;

/**
 * Utility for pretty-printing arrays to stderr with ANSI syntax highlighting.
 *
 * Intended for development-time debugging of structured data such as
 * registered route tables. Output is formatted as indented JSON with
 * keys colored cyan and values colored by type.
 */
class ArrayLogger
{
    /**
     * Encodes an array as pretty-printed JSON and writes it to stderr
     * with ANSI color highlighting applied to keys and values.
     *
     * Coloring rules:
     *   - Keys              → cyan
     *   - String values     → yellow
     *   - Scalar values     → gray (numbers, booleans, null)
     *
     * Each line is indented by two spaces for visual separation from
     * surrounding log output. Fails gracefully with a red error message
     * if the array cannot be JSON-encoded.
     *
     * @param array<mixed> $value The array to log.
     */
    public static function log(array $value): void
    {
        $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false)
        {
            error_log(Colorizer::red("ArrayLogger: Failed to encode array - " . json_last_error_msg()) . Colorizer::reset());
            return;
        }

        // Apply ANSI colors to JSON keys and values via regex.
        // Matches: "key": "value"  |  "key": scalar  |  standalone "key"
        $output = preg_replace_callback(
            '/(".*?")(\s*:\s*)?(".*?"|[0-9.]+|true|false|null)?/',
            function (array $matches): string
            {
                $key   = $matches[1] ?? '';
                $colon = $matches[2] ?? '';
                $value = $matches[3] ?? '';

                if ($colon !== '' && $value !== '') {
                    // String value: color yellow.
                    if (str_starts_with($value, '"'))
                        return Colorizer::cyan($key) . $colon . Colorizer::yellow($value) . Colorizer::reset();

                    // Scalar value (number, boolean, null): color gray.
                    return Colorizer::cyan($key) . $colon . Colorizer::gray($value) . Colorizer::reset();
                }

                // Key with no value (e.g. inside arrays): color cyan only.
                return Colorizer::cyan($key) . Colorizer::reset();
            },
            $json
        );

        // Write each line individually so log viewers that process
        // one line at a time don't receive the entire block as one entry.
        foreach (explode("\n", $output) as $line)
        {
            error_log('  ' . $line);
        }
    }
}