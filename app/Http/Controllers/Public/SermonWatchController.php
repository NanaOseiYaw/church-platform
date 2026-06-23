<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicSermonResource;
use App\Models\Sermon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * GET /sermons/{slug}
 *
 * Full-page sermon watch/listen experience.
 */
class SermonWatchController extends Controller
{
    public function __invoke(string $slug): Response
    {
        // Resolve by slug first; fall back to numeric ID for backward compat
        $sermon = Sermon::publiclyVisible()
            ->where(function ($q) use ($slug) {
                $q->where('slug', $slug);
                if (is_numeric($slug)) {
                    $q->orWhere('id', (int) $slug);
                }
            })
            ->with('sermonSeries:id,title,slug')
            ->firstOrFail();

        // Related sermons: same series → same speaker → latest
        $related = Sermon::publiclyVisible()
            ->where('id', '!=', $sermon->id)
            ->when(
                $sermon->series_id ?? $sermon->series,
                function ($q) use ($sermon) {
                    $q->where(function ($q2) use ($sermon) {
                        if ($sermon->series_id) $q2->where('series_id', $sermon->series_id);
                        elseif ($sermon->series) $q2->where('series', $sermon->series);
                    });
                },
                fn ($q) => $q->where('speaker', $sermon->speaker)
            )
            ->orderByDesc('preached_at')
            ->limit(4)
            ->get();

        return Inertia::render('Public/SermonWatch', [
            'sermon'  => PublicSermonResource::make($sermon)->resolve(),
            'related' => PublicSermonResource::collection($related)->resolve(),
        ]);
    }
}
