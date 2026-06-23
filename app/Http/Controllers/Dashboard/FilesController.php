<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Media\UploadFileRequest;
use App\Traits\LogsAuditEvents;
use App\Http\Resources\FileResource;
use App\Models\File;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FilesController extends Controller
{
    use LogsAuditEvents;

    public function __construct(
        private readonly MediaService $media,
    ) {}

    // ── Upload ─────────────────────────────────────────────────────────────────

    /** POST /dashboard/files */
    public function store(UploadFileRequest $request): JsonResponse
    {
        $this->authorize('create', File::class);

        $attachable = $request->resolveAttachable();

        if (! $attachable) {
            return response()->json(['message' => 'Attachable record not found.'], 422);
        }

        $user = $request->user();

        // Belt-and-suspenders multi-tenant guard.  The BelongsToChurch global
        // scope already filters queries, but an explicit check here prevents
        // edge cases (e.g. a super_admin bypassing the scope who resolves a
        // record that belongs to a different tenant).
        if ($attachable->church_id !== $user->church_id) {
            abort(403, 'Cross-tenant file upload is not allowed.');
        }

        // Ensure the user can view (and therefore attach to) the parent record
        $this->authorize('view', $attachable);

        $file = $this->media->upload(
            uploadedFile: $request->file('file'),
            attachable:   $attachable,
            churchId:     $user->church_id,
            uploadedBy:   $user->id,
            isPublic:     (bool) $request->boolean('is_public', false),
        );

        $file->load('uploader:id,name,avatar');

        $this->auditLog('file.uploaded', $file);

        return response()->json(FileResource::make($file), 201);
    }

    // ── Download ───────────────────────────────────────────────────────────────

    /** GET /dashboard/files/{file}/download */
    public function download(Request $request, File $file): StreamedResponse
    {
        $this->authorize('view', $file);

        // Public files should be fetched directly from storage — stream only private
        if ($file->is_public) {
            abort(404, 'Use the direct storage URL for public files.');
        }

        return $this->media->streamDownload($file);
    }

    // ── Delete ─────────────────────────────────────────────────────────────────

    /** DELETE /dashboard/files/{file} */
    public function destroy(Request $request, File $file): JsonResponse
    {
        $this->authorize('delete', $file);

        $this->auditLog('file.deleted', $file);

        $this->media->delete($file);

        return response()->json(['message' => 'File deleted.']);
    }
}
