<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Traits\LogsAuditEvents;
use App\Http\Requests\Sermons\StoreSermonRequest;
use App\Http\Requests\Sermons\UpdateSermonRequest;
use App\Http\Resources\ChannelConnectionResource;
use App\Http\Resources\SermonResource;
use App\Http\Resources\SermonSeriesResource;
use App\Models\ChannelConnection;
use App\Models\Sermon;
use App\Models\SermonSeries;
use App\Models\User;
use App\Notifications\SermonPublished;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class SermonsController extends Controller
{
    use LogsAuditEvents;
    // ── Sermon list ────────────────────────────────────────────────────────────

    /**
     * GET /dashboard/sermons
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Sermon::class);

        $churchId = $this->resolvedChurchId();
        $user     = $request->user();

        $query = Sermon::forChurch($churchId)
            ->with(['uploader:id,name,avatar', 'channelConnection:id,channel_title,provider'])
            ->orderByDesc('preached_at')
            ->orderByDesc('created_at');

        // Full-text search
        if ($search = trim((string) $request->get('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('title',       'like', "%{$search}%")
                  ->orWhere('speaker',   'like', "%{$search}%")
                  ->orWhere('series',    'like', "%{$search}%")
                  ->orWhere('description','like', "%{$search}%");
            });
        }

        // Series filter
        if ($series = $request->get('series')) {
            $query->where('series', $series);
        }

        // Provider filter
        if ($provider = $request->get('provider')) {
            $query->where('provider', $provider);
        }

        // Visibility filter
        if ($visibility = $request->get('visibility')) {
            if ($visibility === 'featured') {
                $query->where('is_featured', true);
            } else {
                $query->where('visibility', $visibility)
                      ->orWhere(function ($q) use ($visibility) {
                          if ($visibility === 'public') {
                              $q->whereNull('visibility')->where('is_public', true);
                          } elseif ($visibility === 'members_only') {
                              $q->whereNull('visibility')->where('is_public', false);
                          }
                      });
            }
        }

        $sermons = $query->paginate(12)->withQueryString();

        // Distinct series for filter dropdown
        $allSeries = Sermon::forChurch($churchId)
            ->whereNotNull('series')
            ->where('series', '!=', '')
            ->distinct()
            ->orderBy('series')
            ->pluck('series')
            ->values();

        // Channel connections for the header banner
        $connections = ChannelConnection::forChurch($churchId)
            ->where('is_active', true)
            ->orderByDesc('last_synced_at')
            ->get();

        return Inertia::render('Dashboard/Sermons/Index', [
            'sermons'     => SermonResource::collection($sermons),
            'series'      => $allSeries,
            'connections' => ChannelConnectionResource::collection($connections),
            'filters'     => $request->only('search', 'series', 'provider', 'visibility'),
            'canManage'   => $user->can('sermons.upload'),
            'canEdit'     => $user->can('sermons.edit'),
            'canDelete'   => $user->can('sermons.delete'),
            'canManageChannels' => $user->can('sermons.manage_channels'),
        ]);
    }

    // ── Create ─────────────────────────────────────────────────────────────────

    /**
     * GET /dashboard/sermons/create
     */
    public function create(Request $request): Response
    {
        $this->authorize('create', Sermon::class);
        $churchId = $this->resolvedChurchId();

        $seriesList = SermonSeries::forChurch($churchId)
            ->active()
            ->ordered()
            ->get(['id', 'title']);

        $speakers = Sermon::forChurch($churchId)
            ->whereNotNull('speaker')
            ->where('speaker', '!=', '')
            ->distinct()
            ->orderBy('speaker')
            ->pluck('speaker');

        return Inertia::render('Dashboard/Sermons/Create', [
            'seriesList' => SermonSeriesResource::collection($seriesList),
            'speakers'   => $speakers,
        ]);
    }

    /**
     * POST /dashboard/sermons
     */
    public function store(StoreSermonRequest $request): RedirectResponse
    {
        $churchId = $this->resolvedChurchId();
        $data     = $request->validated();

        $sermon = Sermon::create([
            'church_id'     => $churchId,
            'uploaded_by'   => $request->user()->id,
            'provider'      => 'manual',
            'title'         => $data['title'],
            'speaker'       => $data['speaker'] ?? null,
            'description'   => $data['description'] ?? null,
            'series'        => $data['series'] ?? null,
            'series_id'     => $data['series_id'] ?: null,
            'video_url'     => $data['video_url'] ?? null,
            'audio_url'     => $data['audio_url'] ?? null,
            'thumbnail_url' => $data['thumbnail_url'] ?? null,
            'preached_at'   => $data['preached_at'] ?? null,
            'visibility'    => $data['visibility'],
            'is_public'     => ($data['visibility'] ?? 'public') === 'public',
            'is_featured'   => (bool) ($data['is_featured'] ?? false),
        ]);

        $this->auditLog('sermon.created', $sermon);

        // Notify all church members when a sermon is published publicly
        if (($data['visibility'] ?? 'public') === 'public') {
            $actor = $request->user();
            $recipients = User::where('church_id', $churchId)
                ->where('id', '!=', $actor->id)
                ->get();
            Notification::send($recipients, new SermonPublished($sermon));
        }

        return redirect("/dashboard/sermons/{$sermon->id}")
            ->with('success', "\"{$sermon->title}\" created.");
    }

    // ── Edit ───────────────────────────────────────────────────────────────────

    /**
     * GET /dashboard/sermons/{sermon}/edit
     */
    public function edit(Request $request, Sermon $sermon): Response
    {
        $this->authorize('update', $sermon);
        $churchId = $this->resolvedChurchId();

        $seriesList = SermonSeries::forChurch($churchId)
            ->active()
            ->ordered()
            ->get(['id', 'title']);

        $speakers = Sermon::forChurch($churchId)
            ->whereNotNull('speaker')
            ->where('speaker', '!=', '')
            ->distinct()
            ->orderBy('speaker')
            ->pluck('speaker');

        return Inertia::render('Dashboard/Sermons/Edit', [
            'sermon'     => SermonResource::make($sermon),
            'seriesList' => SermonSeriesResource::collection($seriesList),
            'speakers'   => $speakers,
        ]);
    }

    /**
     * PATCH /dashboard/sermons/{sermon}
     */
    public function update(UpdateSermonRequest $request, Sermon $sermon): RedirectResponse
    {
        $this->authorize('update', $sermon);
        $data = $request->validated();

        $sermon->update([
            'title'         => $data['title'],
            'speaker'       => $data['speaker'] ?? null,
            'description'   => $data['description'] ?? null,
            'series'        => $data['series'] ?? null,
            'series_id'     => $data['series_id'] ?: null,
            'video_url'     => $data['video_url'] ?? null,
            'audio_url'     => $data['audio_url'] ?? null,
            'thumbnail_url' => $data['thumbnail_url'] ?? null,
            'preached_at'   => $data['preached_at'] ?? null,
            'visibility'    => $data['visibility'],
            'is_public'     => ($data['visibility'] ?? 'public') === 'public',
            'is_featured'   => (bool) ($data['is_featured'] ?? false),
        ]);

        $this->auditLog('sermon.updated', $sermon);

        return redirect("/dashboard/sermons/{$sermon->id}")
            ->with('success', 'Sermon updated.');
    }

    // ── Single sermon ──────────────────────────────────────────────────────────

    /**
     * GET /dashboard/sermons/{sermon}
     */
    public function show(Request $request, Sermon $sermon): Response
    {
        $this->authorize('view', $sermon);

        $sermon->load(['uploader:id,name,avatar', 'channelConnection:id,channel_title,provider,channel_id']);

        $related = Sermon::forChurch($sermon->church_id)
            ->where('id', '!=', $sermon->id)
            ->when($sermon->series_id,
                fn ($q) => $q->where('series_id', $sermon->series_id),
                fn ($q) => $sermon->series
                    ? $q->where('series', $sermon->series)
                    : $q->where('provider', $sermon->provider),
            )
            ->orderByDesc('preached_at')
            ->take(4)
            ->get();

        return Inertia::render('Dashboard/Sermons/Show', [
            'sermon'    => SermonResource::make($sermon),
            'related'   => SermonResource::collection($related),
            'canManage' => $request->user()->can('sermons.edit')
                           && $request->user()->church_id === $sermon->church_id,
            'canDelete' => $request->user()->can('sermons.delete')
                           && $request->user()->church_id === $sermon->church_id,
        ]);
    }

    // ── Toggle actions (JSON) ──────────────────────────────────────────────────

    /**
     * PATCH /dashboard/sermons/{sermon}/feature
     * Toggle the is_featured flag.
     */
    public function toggleFeature(Request $request, Sermon $sermon): JsonResponse
    {
        $this->authorize('update', $sermon);

        $sermon->update(['is_featured' => ! $sermon->is_featured]);
        $this->auditLog('sermon.updated', $sermon);

        return response()->json([
            'is_featured' => $sermon->is_featured,
            'message'     => $sermon->is_featured ? 'Sermon featured.' : 'Sermon unfeatured.',
        ]);
    }

    /**
     * PATCH /dashboard/sermons/{sermon}/visibility
     * Cycle: public → members_only → unlisted → public
     */
    public function cycleVisibility(Request $request, Sermon $sermon): JsonResponse
    {
        $this->authorize('update', $sermon);

        $cycle = ['public' => 'members_only', 'members_only' => 'unlisted', 'unlisted' => 'public'];
        $current  = $sermon->resolved_visibility;
        $next     = $cycle[$current] ?? 'public';

        $sermon->update([
            'visibility' => $next,
            'is_public'  => $next === 'public',
        ]);
        $this->auditLog('sermon.published', $sermon);

        // Notify all church members when a sermon becomes publicly visible
        if ($next === 'public') {
            $actor      = $request->user();
            $recipients = User::where('church_id', $sermon->church_id)
                ->where('id', '!=', $actor->id)
                ->get();
            Notification::send($recipients, new SermonPublished($sermon));
        }

        return response()->json(['visibility' => $next]);
    }

    /**
     * DELETE /dashboard/sermons/{sermon}
     */
    public function destroy(Request $request, Sermon $sermon): RedirectResponse
    {
        $this->authorize('delete', $sermon);

        $sermon->delete();

        return redirect('/dashboard/sermons')
            ->with('success', "\"{$sermon->title}\" removed.");
    }

    // ── Channel management page ────────────────────────────────────────────────

    /**
     * GET /dashboard/sermons/channel
     */
    public function channelPage(Request $request): Response
    {
        abort_unless($request->user()->can('sermons.manage_channels'), 403);

        $churchId = $this->resolvedChurchId();

        $connections = ChannelConnection::forChurch($churchId)
            ->orderByDesc('created_at')
            ->get();

        $stats = [
            'total_sermons'  => Sermon::forChurch($churchId)->count(),
            'synced_sermons' => Sermon::forChurch($churchId)->where('provider', '!=', 'manual')->count(),
            'featured'       => Sermon::forChurch($churchId)->where('is_featured', true)->count(),
        ];

        return Inertia::render('Dashboard/Sermons/Channel', [
            'connections' => ChannelConnectionResource::collection($connections),
            'stats'       => $stats,
        ]);
    }
}
