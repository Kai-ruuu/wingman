<?php

namespace Wingman\Core\App;

use finfo;
use Wingman\Config\Globals;

/**
 * Upload
 *
 * Fluent builder for handling a single file upload through a two-phase
 * staging and commit workflow.
 *
 * Files are first moved to a temporary directory during stage(), then
 * relocated to their final destination during commit(). If anything goes
 * wrong at any point, rollback() cleans up any staged temp file.
 *
 * Typical usage:
 * @example
 * Upload::build()
 *     ->asRequired()
 *     ->withLabel('Profile Photo')
 *     ->withPrefix('user_123')
 *     ->withFieldName('profile_photo')
 *     ->withAllowedTypes(['image/png', 'image/jpeg'])
 *     ->withMaxSizeMbOf(2.0)
 *     ->withDestination('users/avatars');
 */
class Upload
{
    /** @var string Human-readable label used in error messages (e.g. 'Profile Photo') */
    private string $label;

    /** @var string|null The $_FILES field name this upload reads from */
    private ?string $fieldName = null;

    /** @var string[] List of permitted MIME types (e.g. ['image/png', 'image/jpeg']) */
    private array $allowedTypes = [];

    /** @var float Maximum allowed file size in megabytes */
    private float $maxSizeMb = 5.0;

    /** @var bool Whether the upload is mandatory; triggers a validation error if no file is provided */
    private bool $required = false;

    /** @var string Subdirectory under APP_UPLOADS where the file will be committed */
    private string $destination;

    /** @var string Optional filename prefix applied before the unique ID (e.g. 'sunflower_seed') */
    private string $prefix = '';

    /** @var string|null Validation or upload error set during stage() */
    private ?string $error = null;

    /** @var string|null Error set during commit() */
    private ?string $commitError = null;

    /** @var string|null Absolute path to the temp directory where the file is staged */
    private ?string $tempFileDir = null;

    /** @var string|null Generated filename used in both the temp and final locations */
    private ?string $tempFileName = null;

    private function __construct()
    {
    }

    /**
     * Creates a new Upload instance.
     *
     * @return self
     */
    public static function build(): self
    {
        return new self();
    }

    /**
     * Sets the filename prefix prepended to the generated unique ID.
     *
     * @param  string $prefix  e.g. 'sunflower_seed' produces 'sunflower_seed_<uniqid>.png'
     * @return self
     */
    public function withPrefix(string $prefix): self
    {
        $this->prefix = $prefix;
        return $this;
    }

    /**
     * Sets the human-readable label used in validation and error messages.
     *
     * @param  string $label  e.g. 'Profile Photo'
     * @return self
     */
    public function withLabel(string $label): self
    {
        $this->label = $label;
        return $this;
    }

    /**
     * Sets the $_FILES key this upload reads from.
     *
     * @param  string $fieldName  The HTML input name attribute (e.g. 'image_seed')
     * @return self
     */
    public function withFieldName(string $fieldName): self
    {
        $this->fieldName = $fieldName;
        return $this;
    }

    /**
     * Sets the list of permitted MIME types.
     * Files with a detected MIME type not in this list will be rejected.
     *
     * @param  string[] $allowedTypes  e.g. ['image/png', 'image/jpeg']
     * @return self
     */
    public function withAllowedTypes(array $allowedTypes = []): self
    {
        $this->allowedTypes = $allowedTypes;
        return $this;
    }

    /**
     * Sets the maximum allowed file size in megabytes.
     *
     * @param  float $maxSizeMb  Defaults to 5.0 MB
     * @return self
     */
    public function withMaxSizeMbOf(float $maxSizeMb = 5.0): self
    {
        $this->maxSizeMb = $maxSizeMb;
        return $this;
    }

    /**
     * Sets the destination subdirectory under APP_UPLOADS where the file
     * will be moved on commit().
     *
     * @param  string $destination  e.g. 'release-plants' resolves to Uploads/release-plants/
     * @return self
     */
    public function withDestination(string $destination): self
    {
        $this->destination = $destination;
        return $this;
    }

    /**
     * Marks this upload as required.
     * If no file is provided during stage(), an error will be set.
     *
     * @return self
     */
    public function asRequired(): self
    {
        $this->required = true;
        return $this;
    }

    /**
     * Resolves a file extension from a MIME type.
     * Falls back to 'bin' for any unrecognized MIME type.
     *
     * @param  string $mime  A MIME type string (e.g. 'image/png')
     * @return string        The corresponding file extension (e.g. 'png')
     */
    private static function getExtension(string $mime): string
    {
        return match ($mime)
        {
            // images
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/svg+xml' => 'svg',
            'image/bmp' => 'bmp',
            'image/x-icon' => 'ico',
            'image/tiff' => 'tiff',
            'image/avif' => 'avif',

            // documents
            'application/pdf' => 'pdf',
            'text/plain' => 'txt',
            'text/csv' => 'csv',
            'text/html' => 'html',
            'application/json' => 'json',
            'application/xml' => 'xml',

            // microsoft office
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',

            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',

            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',

            // archives
            'application/zip' => 'zip',
            'application/x-rar-compressed' => 'rar',
            'application/x-7z-compressed' => '7z',
            'application/gzip' => 'gz',
            'application/x-tar' => 'tar',

            // audio
            'audio/mpeg' => 'mp3',
            'audio/wav' => 'wav',
            'audio/ogg' => 'ogg',
            'audio/flac' => 'flac',
            'audio/aac' => 'aac',

            // video
            'video/mp4' => 'mp4',
            'video/x-msvideo' => 'avi',
            'video/x-matroska' => 'mkv',
            'video/webm' => 'webm',
            'video/quicktime' => 'mov',

            // code
            'text/x-php' => 'php',
            'application/x-httpd-php' => 'php',
            'text/javascript' => 'js',
            'application/javascript' => 'js',
            'text/css' => 'css',

            default => 'bin'
        };
    }

    /**
     * Validates and moves the uploaded file to the temporary staging directory.
     *
     * Runs the following checks in order:
     * - Skips silently if the upload is optional and no file was provided
     * - Sets an error if the upload is required and no file was provided
     * - Sets an error if PHP reported an upload error
     * - Sets an error if the detected MIME type is not in the allowed types list
     * - Sets an error if the file size exceeds the configured maximum
     *
     * On success, the file is moved from PHP's tmp dir into APP_UPLOADS_TEMP
     * under a generated unique filename. Check hasError() after calling this.
     *
     * @return void
     */
    public function stage(): void
    {
        $file = $_FILES[$this->fieldName] ?? null;

        if (!$this->required && (!$file || $file['error'] === UPLOAD_ERR_NO_FILE))
            return;

        if ($this->required && (!$file || $file['error'] === UPLOAD_ERR_NO_FILE))
        {
            $this->error = $this->label . ' is required.';
            return;
        }

        if ($file['error'])
        {
            $this->error = 'Unable to upload ' . $this->label;
            return;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!in_array($mime, $this->allowedTypes))
        {
            $this->error = 'Unsupported file format for ' . $this->label;
            return;
        }

        $sizeMb = round($file['size'] / 1024 / 1024, 2);
        if ($sizeMb > $this->maxSizeMb)
        {
            $this->error = 'File is too large for ' . $this->label;
            return;
        }

        $this->tempFileDir = Globals::getDir('APP_UPLOADS_TEMP');
        if (!is_dir($this->tempFileDir))
            mkdir($this->tempFileDir, 0777, true);

        $extension = self::getExtension($mime);
        $this->tempFileName = uniqid($this->prefix ? $this->prefix . '_' : '', true) . '.' . $extension;
        $tempFilePath = $this->tempFileDir . '/' . $this->tempFileName;

        $tmpPath = $file['tmp_name'];
        if (!move_uploaded_file($tmpPath, $tempFilePath))
        {
            $this->error = 'Unable to upload ' . $this->label;
            return;
        }
    }

    /**
     * Moves the staged file from the temp directory to its final destination.
     *
     * Should only be called after stage() has succeeded and any dependent
     * operations (e.g. database inserts) have also succeeded. The destination
     * directory is created automatically if it does not exist.
     *
     * Sets a commit error if the temp file is missing or cannot be moved.
     * Check hasError() after calling this.
     *
     * @return void
     */
    public function commit(): void
    {
        if (!$this->tempFileDir || !$this->tempFileName)
            return;
        
        $fullFileDir = Globals::getConcatDir('APP_UPLOADS', $this->destination);
        if (!is_dir($fullFileDir))
            mkdir($fullFileDir, 0777, true);

        $tempFilePath = $this->tempFileDir . '/' . $this->tempFileName;
        $fullFilePath = $fullFileDir . '/' . $this->tempFileName;

        if (!file_exists($tempFilePath))
        {
            $this->commitError = 'Unable to upload ' . $this->label;
            return;
        }

        if (!rename($tempFilePath, $fullFilePath))
        {
            $this->commitError = 'Unable to upload ' . $this->label;
            return;
        }
    }

    /**
     * Deletes the staged temp file if it exists.
     *
     * Safe to call even if stage() never completed — returns early if no
     * temp path was recorded, preventing unlink() from being called on an
     * invalid path.
     *
     * @return void
     */
    public function rollback(): void
    {
        if (!$this->tempFileDir || !$this->tempFileName)
            return;

        $tempFilePath = $this->tempFileDir . '/' . $this->tempFileName;
        if (file_exists($tempFilePath))
            unlink($tempFilePath);
    }

    /**
     * Returns the first error encountered, prioritizing stage errors over commit errors.
     *
     * @return string|null The error message, or null if no error occurred
     */
    public function getError(): ?string
    {
        return $this->error ?? $this->commitError ?? null;
    }

    /**
     * Returns whether this upload has encountered any error.
     *
     * @return bool
     */
    public function hasError(): bool
    {
        return $this->getError() !== null;
    }

    /**
     * Returns the $_FILES field name this upload is bound to.
     *
     * @return string|null
     */
    public function getFieldName(): ?string
    {
        return $this->fieldName;
    }

    /**
     * Returns the generated filename used in both the temp and final locations.
     * Returns null if stage() has not yet successfully run.
     *
     * @return string|null  e.g. 'sunflower_seed_6a0717c6d96a72.54023881.png'
     */
    public function getFileName(): ?string
    {
        return $this->tempFileName;
    }
}