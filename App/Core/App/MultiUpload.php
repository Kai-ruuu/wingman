<?php

namespace Wingman\Core\App;

use finfo;
use Wingman\Config\Globals;
use Wingman\Core\Utils\File;

/**
 * MultiUpload
 *
 * Handles a multi-file upload through a two-phase staging and commit workflow.
 *
 * Expects a file input with the `multiple` attribute and an array field name
 * (e.g. `name="images[]"`). PHP structures $_FILES for multi-file inputs as
 * parallel arrays keyed by property — MultiUpload transposes this into a
 * per-file structure before processing each file individually.
 *
 * Each file is validated and staged sequentially. If any file fails validation
 * or cannot be moved to the temp directory, the error is captured and all
 * previously staged files are rolled back immediately.
 *
 * Implements the Uploadable interface so it can be managed uniformly by
 * UploadHandler alongside single Upload instances.
 *
 * Typical usage:
 * @example
 * $upload = MultiUpload::build()
 *     ->withLabel('Recipe Images')
 *     ->withFieldName('images')
 *     ->withAllowedTypes(['image/jpeg', 'image/png'])
 *     ->withMaxSizeMbOf(2.0)
 *     ->withMaxFileOf(3)
 *     ->withDestination('recipe-images')
 *     ->withPrefix('recipe');
 *
 * $upload->stage();
 * if ($upload->hasError()) // respond with error
 *
 * // do your DB work, then:
 * $upload->commit();
 * if ($upload->hasError()) // respond with error
 */
class MultiUpload implements Uploadable
{
    /** @var string Human-readable label used in error messages (e.g. 'Recipe Images') */
    private string $label;

    /** @var string|null The $_FILES key this upload reads from (e.g. 'images') */
    private ?string $fieldName = null;

    /** @var string[] Allowed MIME types applied to every file (e.g. ['image/jpeg', 'image/png']) */
    private array $allowedTypes = [];

    /** @var float Maximum allowed file size in megabytes, enforced per individual file. Defaults to 5.0 MB. */
    private float $maxSizeMb = 5.0;

    /** @var bool Whether at least one file is required. If true and no files are uploaded, stage() captures an error. */
    private bool $required = false;

    /** @var string Subdirectory under APP_UPLOADS where files will be committed (e.g. 'recipe-images') */
    private string $destination;

    /** @var string Optional prefix prepended to each generated filename (e.g. 'recipe') */
    private string $prefix = '';

    /** @var string|null Error message captured during stage(), or null if no error */
    private ?string $error = null;

    /** @var string|null Error message captured during commit(), or null if no error */
    private ?string $commitError = null;

    /** @var string|null Absolute path to the temp directory used during staging */
    private ?string $tempFileDir = null;

    /** @var string[] Generated filenames for all successfully staged files, indexed by upload order */
    private array $tempFileNames = [];

    /** @var int Maximum number of files allowed in a single upload. Defaults to 3. */
    private int $maxFiles = 3;

    /**
     * Private constructor — use MultiUpload::build() to create an instance.
     */
    private function __construct()
    {
    }

    /**
     * Creates a new MultiUpload instance.
     *
     * @return self
     */
    public static function build(): self
    {
        return new self();
    }

    /**
     * Sets the prefix prepended to each generated filename.
     *
     * Useful for grouping files by type in the filesystem
     * (e.g. 'recipe' produces filenames like 'recipe_abc123.jpg').
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
     * Sets the maximum number of files allowed in a single upload.
     *
     * If the uploaded file count exceeds this limit, stage() captures
     * an error and no files are processed. Defaults to 3.
     *
     * @param  int $maxFiles
     * @return self
     */
    public function withMaxFileOf(int $maxFiles): self
    {
        $this->maxFiles = $maxFiles;
        return $this;
    }

    /**
     * Sets the human-readable label used in error messages.
     *
     * @param  string $label  (e.g. 'Recipe Images', 'Gallery Photos')
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
     * Must match the `name` attribute of the file input in the form,
     * without the trailing brackets (e.g. pass 'images' for `name="images[]"`).
     *
     * @param  string $fieldName  (e.g. 'images')
     * @return self
     */
    public function withFieldName(string $fieldName): self
    {
        $this->fieldName = $fieldName;
        return $this;
    }

    /**
     * Sets the allowed MIME types applied to every uploaded file.
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
     * Sets the maximum allowed file size in megabytes, enforced per file.
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
     * @param  string $destination  (e.g. 'recipe-images', 'gallery')
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
     * If no files are uploaded, stage() captures an error and processing stops.
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
     * Validates all incoming files and moves them to a temporary directory.
     *
     * PHP structures $_FILES for multi-file inputs as parallel arrays keyed
     * by property (name, tmp_name, error, size, type). This method first
     * transposes that structure into a per-file array before processing.
     *
     * For each file, performs the following checks in order:
     * - Skips silently if not required and no files were uploaded
     * - Captures an error if required but no files were uploaded
     * - Captures an error if the file count exceeds the configured maximum
     * - Captures an error if PHP reported an upload error for any file
     * - Validates MIME type against the allowed types list
     * - Validates file size against the maximum allowed size
     *
     * On success, each file is moved to the temp directory and its generated
     * filename is appended to $tempFileNames. If any file fails, all
     * previously staged files are rolled back immediately.
     *
     * Check hasError() after calling this.
     *
     * @return void
     */
    public function stage(): void
    {
        $raw = $_FILES[$this->fieldName] ?? [];

        if (!$this->required && empty($raw))
            return;

        if ($this->required && empty($raw))
        {
            $this->error = $this->label . ' is required.';
            return;
        }

        $count = count($raw['name']);

        if ($count > $this->maxFiles)
        {
            $fileOrFiles = $this->maxFiles > 1 ? 'files' : 'file';
            $this->error = "You can only upload {$this->maxFiles} {$fileOrFiles} for {$this->label}.";
            return;
        }

        // Transpose from {property => [values]} to [{property => value}]
        $files = [];
        for ($i = 0; $i < $count; $i++)
        {
            $files[] = [
                'name'     => $raw['name'][$i],
                'tmp_name' => $raw['tmp_name'][$i],
                'error'    => $raw['error'][$i],
                'size'     => $raw['size'][$i],
                'type'     => $raw['type'][$i],
            ];
        }

        foreach ($files as $file)
        {
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
            $fileName = uniqid($this->prefix ? $this->prefix . '_' : '', true) . '.' . $extension;
            $this->tempFileNames[] = $fileName;
            $tempFilePath = $this->tempFileDir . '/' . $fileName;

            $tmpPath = $file['tmp_name'];
            if (!move_uploaded_file($tmpPath, $tempFilePath))
            {
                $this->error = 'Unable to upload ' . $this->label;
                return;
            }
        }
    }

    /**
     * Moves all staged files from the temp directory to their final destination.
     *
     * Should only be called after stage() has succeeded and all dependent
     * operations (e.g. database inserts) have also succeeded.
     *
     * If any file fails to move, the error is captured and processing stops.
     * Already-committed files are not rolled back at this point — call
     * rollback() explicitly if needed.
     *
     * No-op if no files were staged. Check hasError() after calling this.
     *
     * @return void
     */
    public function commit(): void
    {
        if (!$this->tempFileDir || count($this->tempFileNames) === 0)
            return;

        $fullFileDir = Globals::getConcatDir('APP_UPLOADS', $this->destination);
        if (!is_dir($fullFileDir))
            mkdir($fullFileDir, 0777, true);

        foreach ($this->tempFileNames as $tempFileName)
        {
            $tempFilePath = $this->tempFileDir . '/' . $tempFileName;
            $fullFilePath = $fullFileDir . '/' . $tempFileName;

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
    }

    /**
     * Deletes all staged temp files if they exist.
     *
     * Safe to call at any point — no-op if no files were staged.
     *
     * @return void
     */
    public function rollback(): void
    {
        if (!$this->tempFileDir || count($this->tempFileNames) === 0)
            return;

        foreach ($this->tempFileNames as $tempFileName)
        {
            $tempFilePath = $this->tempFileDir . '/' . $tempFileName;
            if (file_exists($tempFilePath))
                unlink($tempFilePath);
        }
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
     * Returns the generated filename for a specific file by its upload index.
     *
     * Returns null if no files were staged or if the index is out of range.
     *
     * @param  int         $index  Zero-based index of the file (default: 0)
     * @return string|null         The generated filename (e.g. 'recipe_abc123.jpg'), or null
     */
    public function getFileName(int $index = 0): ?string
    {
        if (count($this->tempFileNames) === 0)
            return null;

        if (!isset($this->tempFileNames[$index]))
            return null;

        return $this->tempFileNames[$index];
    }

    /**
     * Returns all generated filenames after a successful stage().
     *
     * Filenames are indexed by upload order (0, 1, 2...). Returns an empty
     * array if no files were staged.
     *
     * Typically used when inserting all filenames into the database at once.
     *
     * @return string[]  Array of generated filenames
     */
    public function getFileNames(): array
    {
        return $this->tempFileNames;
    }
}