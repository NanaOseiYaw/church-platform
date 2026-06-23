<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\FileResource;
use App\Traits\LogsAuditEvents;
use App\Models\File;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MediaController extends Controller
{
    use LogsAuditEvents;

    /**
     * GET /dashboard/media
     *
     * Church-wide media library — every file uploaded to any resource
     * within this tenant, with search + type filters.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', File::class);

        $churchId = $this->resolvedChurchId();
        $user     = $request->user();

        // ── Department-visibility constraint ─────────────────────────────────
        // Non-admins must not see files uploaded directly to department workspaces
        // they are no longer a member of. Church admins (departments.edit) see every
        // file. Files attached to events / announcements / tasks are always visible
        // here — the parent entity's own policy governs download access.
        //
        // This closure is applied to BOTH the paginated query and the type-count
        // queries so the filter pills reflect the user's visible file set.
        $deptScope = null;
        if (! $user->can('departments.edit')) {
            $myDeptIds = $user->departments()->pluck('departments.id');
            $deptScope = fn ($q) => $q->where(function ($inner) use ($myDeptIds) {
                $inner->where('fileable_type', '!=', 'department')
                      ->orWhereNull('fileable_type')
                      ->orWhereIn('fileable_id', $myDeptIds);
            });
        }

        $base = fn () => File::forChurch($churchId)->when($deptScope, $deptScope);

        $query = $base()->with('uploader:id,name,avatar')->orderByDesc('created_at');

        // ── Type filter ─────────────────────────────────────────────────────
        if ($type = $request->get('type')) {
            match ($type) {
                'image'    => $query->where('mime_type', 'like', 'image/%'),
                'audio'    => $query->where('mime_type', 'like', 'audio/%'),
                'video'    => $query->where('mime_type', 'like', 'video/%'),
                'document' => $query->where(function ($q) {
                    $q->where('mime_type', 'like', 'application/%')
                      ->orWhere('mime_type', 'like', 'text/%');
                }),
                default    => null,
            };
        }

        // ── Name search ─────────────────────────────────────────────────────
        if ($search = trim((string) $request->get('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('original_name', 'like', "%{$search}%")
                  ->orWhere('name',         'like', "%{$search}%");
            });
        }

        $files = $query->paginate(24)->withQueryString();

        // Counts per type for the filter pills — scoped to the same visibility as
        // the main query so counts reflect what the user can actually see.
        $typeCounts = [
            'all'      => $base()->count(),
            'image'    => $base()->where('mime_type', 'like', 'image/%')->count(),
            'audio'    => $base()->where('mime_type', 'like', 'audio/%')->count(),
            'video'    => $base()->where('mime_type', 'like', 'video/%')->count(),
            'document' => $base()->where(function ($q) {
                $q->where('mime_type', 'like', 'application/%')
                  ->orWhere('mime_type', 'like', 'text/%');
            })->count(),
        ];

        return Inertia::render('Dashboard/Media/Index', [
            'files'      => FileResource::collection($files),
            'typeCounts' => $typeCounts,
            'filters'    => $request->only('search', 'type'),
            'canDelete'  => $user->can('media.delete'),
        ]);
    }
}
