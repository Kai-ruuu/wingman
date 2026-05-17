<?php

namespace Wingman\Core\App;

/**
 * Uploadable
 *
 * Contract for all upload types handled by UploadHandler.
 * Both single-file (Upload) and multi-file (MultiUpload) uploads
 * implement this interface so they can be managed uniformly.
 */
interface Uploadable
{
    /**
     * Validates the incoming file(s) and moves them to a temporary directory.
     *
     * Should be called before any dependent operations (e.g. database inserts).
     * Check hasError() after calling this.
     *
     * @return void
     */
    public function stage(): void;

    /**
     * Moves staged file(s) from the temp directory to their final destination.
     *
     * Should only be called after stage() has succeeded and all dependent
     * operations have also succeeded. Check hasError() after calling this.
     *
     * @return void
     */
    public function commit(): void;

    /**
     * Deletes any staged temp file(s).
     *
     * Safe to call at any point — no-op if no files were staged.
     *
     * @return void
     */
    public function rollback(): void;

    /**
     * Returns whether an error occurred during stage() or commit().
     *
     * @return bool
     */
    public function hasError(): bool;

    /**
     * Returns the first error message captured, or null if no error occurred.
     *
     * @return string|null
     */
    public function getError(): ?string;

    /**
     * Returns the $_FILES field name this upload is registered under.
     *
     * @return string|null
     */
    public function getFieldName(): ?string;
}