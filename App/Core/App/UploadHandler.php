<?php

namespace Wingman\Core\App;

use Wingman\Config\Globals;

/**
 * UploadHandler
 *
 * Orchestrates one or more Uploadable instances (Upload or MultiUpload)
 * through a unified two-phase staging and commit workflow.
 *
 * Uploads are staged together first, then committed together only after
 * all dependent operations (e.g. database inserts) have succeeded. If any
 * upload fails at either phase, the handler captures the error and
 * automatically rolls back all staged files.
 *
 * Typical usage:
 * @example
 * $handler = UploadHandler::build()
 *     ->add(Upload::build()->withFieldName('avatar')->...)
 *     ->add(MultiUpload::build()->withFieldName('images')->...);
 *
 * $handler->stage();
 * if ($handler->hasError()) // respond with error
 *
 * // do your DB work, then:
 * $handler->commit();
 * if ($handler->hasError()) // respond with error
 */
class UploadHandler
{
    /** @var string|null The first error captured from any upload during stage() or commit() */
    private ?string $error = null;

    /** @var Uploadable[] Registered uploads to be processed in the order they were added */
    private array $uploads = [];

    /**
     * Private constructor — use UploadHandler::build() to create an instance.
     */
    private function __construct(array $uploads = [])
    {
    }

    /**
     * Creates a new UploadHandler instance.
     *
     * @return self
     */
    public static function build(): self
    {
        return new self();
    }

    /**
     * Registers an Uploadable instance to be handled.
     *
     * Accepts both Upload (single file) and MultiUpload (multiple files).
     * Uploads are staged and committed in the order they are added.
     *
     * @param  Uploadable $upload
     * @return self
     */
    public function add(Uploadable $upload): self
    {
        $this->uploads[] = $upload;
        return $this;
    }

    /**
     * Deletes a file from the specified upload destination if it exists.
     *
     * Useful for removing old files (e.g. avatars) after a successful update.
     * Safe to call at any point — no-op if the file does not exist.
     *
     * @param  string $destination  The subdirectory under APP_UPLOADS (e.g. 'avatars')
     * @param  string $filename     The filename to delete
     * @return void
     */
    public function deleteIfExists(string $destination, string $filename): void
    {
        $filePath = Globals::getConcatDir('APP_UPLOADS', $destination, $filename);

        if (file_exists($filePath))
            unlink($filePath);
    }

    /**
     * Stages all registered uploads sequentially.
     *
     * Each upload is validated and moved to the temp directory. If any upload
     * fails, the error is captured, all staged files are rolled back, and
     * processing stops immediately. Check hasError() after calling this.
     *
     * @return void
     */
    public function stage(): void
    {
        foreach ($this->uploads as $upload)
        {
            $upload->stage();
            if ($upload->hasError())
            {
                $this->error = $upload->getError();
                $this->rollback();
                break;
            }
        }
    }

    /**
     * Commits all staged uploads to their final destinations sequentially.
     *
     * Should only be called after stage() has succeeded and all dependent
     * operations (e.g. database inserts) have also succeeded. If any upload
     * fails to commit, the error is captured, all staged files are rolled back,
     * and processing stops immediately. Check hasError() after calling this.
     *
     * @return void
     */
    public function commit(): void
    {
        foreach ($this->uploads as $upload)
        {
            $upload->commit();
            if ($upload->hasError())
            {
                $this->error = $upload->getError();
                $this->rollback();
                break;
            }
        }
    }

    /**
     * Rolls back all registered uploads by deleting any staged temp files.
     *
     * Safe to call at any point — each upload's rollback() is a no-op if
     * its file(s) were never staged.
     *
     * @return void
     */
    public function rollback(): void
    {
        foreach ($this->uploads as $upload)
        {
            $upload->rollback();
        }
    }

    /**
     * Returns whether any upload encountered an error during stage() or commit().
     *
     * @return bool
     */
    public function hasError(): bool
    {
        return $this->error !== null;
    }

    /**
     * Returns the first error captured from any upload during stage() or commit().
     *
     * @return string|null  The error message, or null if no error occurred
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Retrieves the generated filename for a specific upload by its field name.
     *
     * Behavior differs by upload type:
     * - For Upload (single file): call without an index, or with $index = null.
     * - For MultiUpload: pass the zero-based index of the file to retrieve.
     *
     * Returns null if:
     * - The field name is not registered
     * - An index is passed for a single Upload field
     * - No index is passed for a MultiUpload field
     * - The index is out of range
     * - The file has not been staged yet
     *
     * @param  string   $fieldName  The $_FILES key to look up (e.g. 'avatar')
     * @param  int|null $index      Zero-based file index for MultiUpload fields (e.g. 0, 1, 2)
     * @return string|null          The generated filename, or null if not found
     */
    public function getFileNameByFieldName(string $fieldName, ?int $index = null): ?string
    {
        $fileName = null;

        foreach ($this->uploads as $upload)
        {
            if ($upload->getFieldName() === $fieldName)
            {
                if ($index === null && $upload instanceof Upload)
                    return $upload->getFileName();

                if ($index !== null && $upload instanceof MultiUpload)
                    return $upload->getFileName($index);
            }
        }

        return $fileName;
    }

    /**
     * Retrieves all generated filenames for a MultiUpload field.
     *
     * Useful when inserting all uploaded filenames into the database at once
     * without needing to know the exact file count upfront.
     *
     * Returns an empty array if:
     * - The field name is not registered
     * - The field is registered as a single Upload, not a MultiUpload
     * - No files were staged
     *
     * @param  string   $fieldName  The $_FILES key to look up (e.g. 'images')
     * @return string[]             Array of generated filenames, indexed by upload order
     */
    public function getFileNamesByFieldName(string $fieldName): array
    {
        foreach ($this->uploads as $upload)
        {
            if ($upload->getFieldName() === $fieldName && $upload instanceof MultiUpload)
                return $upload->getFileNames();
        }

        return [];
    }
}