<?php

namespace App\Models\Concerns;

use App\Models\File;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Adds a `files(): MorphMany` relation to any Eloquent model and registers
 * a cascade-delete hook to clean up attached files when the parent is
 * permanently removed.
 *
 * Usage: add `use HasMedia;` to Announcement, Event, Task, Department.
 * Models that already declared files() inline should remove that definition
 * and use this trait instead — the behaviour is identical.
 *
 * Soft-delete behaviour:
 *   – When a model is *soft-deleted* (trashed), its files are kept intact so
 *     they remain accessible if the record is restored.
 *   – When a model is *force-deleted* (permanently removed), all attached files
 *     are deleted from storage and the database.
 *   – Models without SoftDeletes always clean up files on every delete.
 */
trait HasMedia
{
    /**
     * Register the cascade-delete listener once per class boot.
     */
    protected static function bootHasMedia(): void
    {
        static::deleting(function (self $model): void {
            // Skip for soft-delete — only clean up on permanent removal.
            // `isForceDeleting()` exists only on models that use SoftDeletes.
            if (
                method_exists($model, 'isForceDeleting')
                && ! $model->isForceDeleting()
            ) {
                return;
            }

            // Resolve MediaService from the IoC container so it handles both
            // the Storage::delete() call and the File DB row removal.
            $media = resolve(\App\Services\MediaService::class);

            $model->files->each(fn ($file) => $media->delete($file));
        });
    }

    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'fileable');
    }
}
