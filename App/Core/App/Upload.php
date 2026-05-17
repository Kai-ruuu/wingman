<?php

namespace Wingman\Core\App;

use finfo;
use Wingman\Config\Globals;
use Wingman\Core\Utils\File;

/**
 * Upload
 *
 * Handles a single-file upload through a two-phase staging and commit workflow.
 *
 * The file is first validated and moved to a temporary directory (stage), then
 * moved to its final destination (commit) only after all dependent operations
 * (e.g. database inserts) have succeeded. If anything fails at any point,
 * rollback() cleans up the temp file.
 *
 * Implements the Uploadable interface so it can be managed uniformly by
 * UploadHandler alongside MultiUpload instances.
 *
 * Typical usage:
 * @example
 * $upload = Upload::build()
 *     ->withLabel('Avatar')
 *     ->withFieldName('avatar')
 *     ->withAllowedTypes(['image/jpeg', 'image/png'])
 *     ->withMaxSizeMbOf(2.0)
 *     ->withDestination('avatars')
 *     ->withPrefix('avatar');
 *
 * $upload->stage();
 * if ($upload->hasError()) // respond with error
 *
 * // do your DB work, then:
 * $upload->commit();
 * if ($upload->hasError()) // respond with error
 */
class Upload implements Uploadable
{
    /** @var string Human-readable label used in error messages (e.g. 'Avatar') */
    private string $label;

    /** @var string|null The $_FILES key this upload reads from (e.g. 'avatar') */
    private ?string $fieldName = null;

    /** @var string[] Allowed MIME types (e.g. ['image/jpeg', 'image/png']) */
    private array $allowedTypes = [];

    /** @var float Maximum allowed file size in megabytes. Defaults to 5.0 MB. */
    private float $maxSizeMb = 5.0;

    /** @var bool Whether the file is required. Optional uploads are skipped if absent. */
    private bool $required = false;

    /** @var string Subdirectory under APP_UPLOADS where the file will be committed (e.g. 'avatars') */
    private string $destination;

    /** @var string Optional prefix prepended to the generated filename (e.g. 'avatar') */
    private string $prefix = '';

    /** @var string|null Error message captured during stage(), or null if no error */
    private ?string $error = null;

    /** @var string|null Error message captured during commit(), or null if no error */
    private ?string $commitError = null;

    /** @var string|null Absolute path to the temp directory used during staging */
    private ?string $tempFileDir = null;

    /** @var string|null Generated filename used for both the temp and final file paths */
    private ?string $tempFileName = null;

    /**
     * Private constructor — use Upload::build() to create an instance.
     */
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
     * Sets the prefix prepended to the generated filename.
     *
     * Useful for grouping files by type in the filesystem
     * (e.g. 'avatar' produces filenames like 'avatar_abc123.jpg').
     *
     * @param  string $prefix
     * @return self
     */
    public function withPrefix(string $prefix): self
    {
        $this->prefix = $prefix;
        return $this;
    }

    /**
     * Sets the human-readable label used in error messages.
     *
     * @param  string $label  (e.g. 'Avatar', 'Profile Photo')
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
     * Must match the `name` attribute of the file input in the form.
     *
     * @param  string $fieldName  (e.g. 'avatar')
     * @return self
     */
    public function withFieldName(string $fieldName): self
    {
        $this->fieldName = $fieldName;
        return $this;
    }

    /**
     * Sets the allowed MIME types for this upload.
     *
     * MIME type is determined from the actual file content using finfo,
     * not from the client-supplied filename or Content-Type header.
     *
     * @param  string[] $allowedTypes  (e.g. ['image/jpeg', 'image/png'])
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
     * Defaults to 5.0 MB if not set.
     *
     * @param  float $maxSizeMb
     * @return self
     */
    public function withMaxSizeMbOf(float $maxSizeMb = 5.0): self
    {
        $this->maxSizeMb = $maxSizeMb;
        return $this;
    }

    /**
     * Sets the destination subdirectory under APP_UPLOADS.
     *
     * The directory is created automatically during commit() if it does not exist.
     *
     * @param  string $destination  (e.g. 'avatars', 'recipe-images')
     * @return self
     */
    public function withDestination(string $destination): self
    {
        $this->destination = $destination;
        return $this;
    }

    /**
     * Marks this upload as required.
     *
     * If the file is absent, stage() captures an error and processing stops.
     * By default, uploads are optional — absent files are silently skipped.
     *
     * @return self
     */
    public function asRequired(): self
    {
        $this->required = true;
        return $this;
    }

    /**
     * Validates the incoming file and moves it to a temporary directory.
     *
     * Performs the following checks in order:
     * - Skips silently if not required and no file was uploaded
     * - Captures an error if required but no file was uploaded
     * - Captures an error if PHP reported an upload error
     * - Validates MIME type against the allowed types list
     * - Validates file size against the maximum allowed size
     *
     * On success, the file is moved to the temp directory and the generated
     * filename is stored for use in commit() and getFileName().
     *
     * Check hasError() after calling this.
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

        $extension = File::getExtension($mime);
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
     * Should only be called after stage() has succeeded and all dependent
     * operations (e.g. database inserts) have also succeeded.
     *
     * No-op if no file was staged (e.g. optional upload with no file provided).
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
     * Safe to call at any point — no-op if no file was staged.
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
     * Returns the first error captured during stage() or commit().
     *
     * @return string|null  The error message, or null if no error occurred
     */
    public function getError(): ?string
    {
        return $this->error ?? $this->commitError ?? null;
    }

    /**
     * Returns whether an error occurred during stage() or commit().
     *
     * @return bool
     */
    public function hasError(): bool
    {
        return $this->getError() !== null;
    }

    /**
     * Returns the $_FILES field name this upload is registered under.
     *
     * Used by UploadHandler to look up uploads by field name.
     *
     * @return string|null
     */
    public function getFieldName(): ?string
    {
        return $this->fieldName;
    }

    /**
     * Returns the generated filename after a successful stage().
     *
     * The filename is the same for both the temp and final file paths.
     * Returns null if the file has not been staged yet.
     *
     * @return string|null  The generated filename (e.g. 'avatar_abc123.jpg'), or null
     */
    public function getFileName(): ?string
    {
        return $this->tempFileName;
    }
}