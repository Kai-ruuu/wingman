<?php

namespace Wingman\Core\Utils;

use Wingman\Core\CLI\Colorizer;

class ArrayLogger
{
    public static function log(array $value): void
    {
        $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false)
        {
            echo Colorizer::red("ArrayLogger: Failed to encode array - " . json_last_error_msg()) . Colorizer::reset(PHP_EOL);
            return;
        }

        $output = preg_replace_callback(
            '/(".*?")(\s*:\s*)?(".*?"|[0-9.]+|true|false|null)?/',
            function (array $matches): string
            {
                $key      = $matches[1] ?? '';
                $colon    = $matches[2] ?? '';
                $value    = $matches[3] ?? '';

                if ($colon !== '' && $value !== '') {
                    if (str_starts_with($value, '"'))
                        return Colorizer::cyan($key) . $colon . Colorizer::yellow($value);

                    return Colorizer::cyan($key) . $colon . Colorizer::gray($value);
                }

                // standalone key
                return Colorizer::cyan($key);
            },
            $json
        );

        foreach (explode("\n", $output) as $line)
        {
            echo '  ' . $line . PHP_EOL;
        }
    }
}