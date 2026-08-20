<?php

namespace App\Services;

use App\Http\Requests\Media\UploadFileRequest;
use App\Models\File;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaService
{
    /**
     * Upload a file and create a File record attached to $attachable.
     *
     * Path scheme: churches/{church_id}/{module}/{uuid}.{ext}
     * Disk: 'public' when $isPublic, otherwise 'local'.
     */
    public function upload(
        UploadedFile $uploadedFile,
        Model        $attachable,
        int          $churchId,
        int          $uploadedBy,
        bool         $isPublic = false,
    ): File {
        $ext      = $this->safeExtension($uploadedFile);
        $uuid     = (string) Str::uuid();
        $module   = $this->resolveModule($attachable);
        $disk     = $isPublic ? 'public' : 'local';

        // Guard against an empty extension producing a trailing dot (e.g. "{uuid}.")
        $filename = $ext !== '' ? "{$uuid}.{$ext}" : $uuid;
        $dir      = "churches/{$churchId}/{$module}";
        $path     = "{$dir}/{$filename}";

        // Stream directly from the temp file — avoids loading the entire
        // payload into memory with file_get_contents() for large uploads.
        Storage::disk($disk)->putFileAs($dir, $uploadedFile, $filename);

        // Use the morph alias if registered, otherwise fall back to the full class name.
        // This must match what Eloquent uses in morphMany() queries — otherwise
        // eager-loading files on the parent model would return empty results.
        $morphMap  = Relation::morphMap();
        $morphType = array_search(get_class($attachable), $morphMap) ?: get_class($attachable);

        return File::create([
            'church_id'     => $churchId,
            'fileable_id'   => $attachable->getKey(),
            'fileable_type' => $morphType,
            'uploaded_by'   => $uploadedBy,
            'name'          => $filename,
            'original_name' => $uploadedFile->getClientOriginalName(),
            'path'          => $path,
            'mime_type'     => $uploadedFile->getMimeType(),
            'extension'     => $ext ?: null,
            'size'          => $uploadedFile->getSize(),
            'disk'          => $disk,
            'is_public'     => $isPublic,
        ]);
    }

    /**
     * Delete the stored file and remove the database record.
     */
    public function delete(File $file): void
    {
        Storage::disk($file->disk)->delete($file->path);
        $file->delete();
    }

    /**
     * Stream a private file for download.
     * For public files the caller should redirect to $file->url instead.
     */
    public function streamDownload(File $file): StreamedResponse
    {
        return Storage::disk($file->disk)->download(
            $file->path,
            $file->original_name,
        );
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Resolve the extension the file will be *stored* under.
     *
     * The client-supplied extension is never trusted directly: it is accepted
     * only when it appears in UploadFileRequest::ALLOWED_EXTENSIONS. Anything
     * else falls back to the extension implied by the file's detected MIME
     * type, and finally to no extension at all. This keeps a hostile name such
     * as `payload.php` from ever reaching disk even if a future caller skips
     * the form request's validation.
     */
    private function safeExtension(UploadedFile $uploadedFile): string
    {
        $claimed = strtolower($uploadedFile->getClientOriginalExtension());

        if ($claimed !== '' && in_array($claimed, UploadFileRequest::ALLOWED_EXTENSIONS, true)) {
            return $claimed;
        }

        $guessed = strtolower((string) $uploadedFile->guessExtension());

        if ($guessed !== '' && in_array($guessed, UploadFileRequest::ALLOWED_EXTENSIONS, true)) {
            return $guessed;
        }

        return '';
    }

    /**
     * Return a short directory name based on the model class.
     * Keeps storage paths human-readable: .../announcements/...
     */
    private function resolveModule(Model $model): string
    {
        $map = [
            \App\Models\Announcement::class => 'announcements',
            \App\Models\Event::class        => 'events',
            \App\Models\Task::class         => 'tasks',
            \App\Models\Department::class   => 'departments',
        ];

        return $map[get_class($model)] ?? 'files';
    }
}
