<?php

namespace Wingman\Core\Utils;

/**
 * File
 *
 * Utility class for file-related helpers.
 */
class File
{
    /**
     * Returns the file extension for a given MIME type.
     *
     * Used during upload staging to generate filenames with the correct
     * extension based on the actual file content rather than the client-supplied
     * filename, which cannot be trusted.
     *
     * Returns 'bin' for any unrecognized MIME type.
     *
     * @param  string $mime  The MIME type (e.g. 'image/jpeg', 'application/pdf')
     * @return string        The corresponding file extension (e.g. 'jpg', 'pdf')
     */
    public static function getExtension(string $mime): string
    {
        return match ($mime)
        {
            'image/jpeg' => 'jpg',
            // ... rest of your match
            default => 'bin'
        };
    }
}