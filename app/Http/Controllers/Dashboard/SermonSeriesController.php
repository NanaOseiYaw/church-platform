<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sermons\StoreSeriesRequest;
use App\Http\Resources\SermonSeriesResource;
use App\Models\SermonSeries;
use App\Traits\LogsAuditEvents;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manages sermon series (teaching series / message series).
 *
 * Routes:
 *   GET    /dashboard/sermons/series               → index  (list all series)
 *   POST   /dashboard/sermons/series               → store  (create series)
 *   PUT    /dashboard/sermons/series/{series}      → update (edit series)
 *   DELETE /dashboard/sermons/series/{series}      → destroy
 *
 * IMPORTANT: declared before /{sermon} in the route group to avoid collision.
 */
class SermonSeriesController extends Controller
{
    use LogsAuditEvents;

    /**
     * GET /dashboard/sermons/series
     */
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('sermons.edit'), 403);

        $churchId = $this->resolvedChurchId();

        $seriesList = SermonSeries::forChurch($churchId)
            ->withCount('sermons')
            ->ordered()
            ->get();

        return Inertia::render('Dashboard/Sermons/Series', [
            'seriesList' => SermonSeriesResource::collection($seriesList),
        ]);
    }

    /**
     * POST /dashboard/sermons/series
     */
    public function store(StoreSeriesRequest $request): RedirectResponse
    {
        $churchId = $this->resolvedChurchId();
        $data     = $request->validated();

        $series = SermonSeries::create([
            'church_id'   => $churchId,
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'started_at'  => $data['started_at'] ?? null,
            'ended_at'    => $data['ended_at'] ?? null,
            'sort_order'  => $data['sort_order'] ?? 0,
            'is_active'   => (bool) ($data['is_active'] ?? true),
        ]);

        $this->auditLog('sermon.series_created', $series);

        return back()->with('success', "Series \"{$series->title}\" created.");
    }

    /**
     * PUT /dashboard/sermons/series/{series}
     */
    public function update(StoreSeriesRequest $request, SermonSeries $series): RedirectResponse
    {
        abort_unless(
            $request->user()->can('sermons.edit') &&
            $series->church_id === $this->resolvedChurchId(),
            403,
        );

        $data = $request->validated();

        $series->update([
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'started_at'  => $data['started_at'] ?? null,
            'ended_at'    => $data['ended_at'] ?? null,
            'sort_order'  => $data['sort_order'] ?? 0,
            'is_active'   => (bool) ($data['is_active'] ?? true),
        ]);

        $this->auditLog('sermon.series_updated', $series);

        return back()->with('success', "Series updated.");
    }

    /**
     * DELETE /dashboard/sermons/series/{series}
     *
     * Linked sermons have their series_id set to NULL automatically
     * (nullOnDelete foreign key constraint — see the provider fields migration).
     */
    public function destroy(Request $request, SermonSeries $series): RedirectResponse
    {
        abort_unless(
            $request->user()->can('sermons.edit') &&
            $series->church_id === $this->resolvedChurchId(),
            403,
        );

        $title = $series->title;
        $this->auditLog('sermon.series_deleted', $series);
        $series->delete();

        return back()->with('success', "Series \"{$title}\" deleted.");
    }
}
