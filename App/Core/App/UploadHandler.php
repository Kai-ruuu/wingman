<?php

namespace Wingman\Core\App;

/**
 * UploadHandler
 *
 * Orchestrates multiple Upload instances through a two-phase staging
 * and commit workflow.
 *
 * Uploads are staged together first, then committed together only after
 * all dependent operations (e.g. database inserts) have succeeded. If any
 * upload fails at either phase, the handler captures the error and
 * automatically rolls back all staged files.
 *
 * Typical usage:
 * @example
 * $handler = UploadHandler::build()
 *     ->add(Upload::build()->asRequired()->withFieldName('image_seed')->...)
 *     ->add(Upload::build()->asRequired()->withFieldName('image_mature')->...);
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

    /** @var Upload[] The registered uploads to be processed */
    private array $uploads = [];

    /**
     * @param Upload[] $uploads
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
     * Registers an Upload instance to be handled.
     *
     * Uploads are staged and committed in the order they are added.
     *
     * @param  Upload $upload
     * @return self
     */
    public function add(Upload $upload): self
    {
        $this->uploads[] = $upload;
        return $this;
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
     * Safe to call at any point — each Upload's rollback() is a no-op if
     * its file was never staged.
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
     * Returns whether any upload has encountered an error.
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
     * @return string|null The error message, or null if no error occurred
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Retrieves the generated filename for a specific upload by its field name.
     *
     * Useful for reading back filenames after staging, typically to persist
     * them to the database before calling commit().
     *
     * Returns null if no upload is registered under the given field name,
     * or if that upload has not yet been successfully staged.
     *
     * @param  string      $fieldName  The $_FILES key to look up (e.g. 'image_seed')
     * @return string|null             The generated filename, or null if not found
     */
    public function getFileNameByFieldName(string $fieldName): ?string
    {
        $fileName = null;

        foreach ($this->uploads as $upload)
        {
            if ($upload->getFieldName() === $fieldName)
                return $upload->getFileName();
        }

        return $fileName;
    }
}