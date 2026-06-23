<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicSermonResource;
use App\Http\Resources\SermonSeriesResource;
use App\Models\Sermon;
use App\Models\SermonSeries;
use Inertia\Inertia;
use Inertia\Response;

class SermonsController extends Controller
{
    public function __invoke(): Response
    {
        $church           = app('church');
        $homepageSettings = $church?->settings['homepage'] ?? [];

        // ── Featured sermon (first featured, or most recent) ──────────────────
        $featured = Sermon::publiclyVisible()
            ->with('sermonSeries:id,title,slug')
            ->orderByDesc('is_featured')
            ->orderByDesc('preached_at')
            ->first();

        // ── Recent sermons (excluding featured) ───────────────────────────────
        $recent = Sermon::publiclyVisible()
            ->with('sermonSeries:id,title,slug')
            ->when($featured, fn ($q) => $q->where('id', '!=', $featured->id))
            ->orderByDesc('preached_at')
            ->limit(12)
            ->get();

        // ── Series list ───────────────────────────────────────────────────────
        // Use the SermonSeries model when available, fall back to string series field
        $seriesList = SermonSeries::publiclyVisible()
            ->whereHas('sermons', fn ($q) => $q->publiclyVisible())
            ->withCount(['sermons' => fn ($q) => $q->publiclyVisible()])
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        // If no formal series records, build a list from the string field
        if ($seriesList->isEmpty()) {
            $seriesList = collect(
                Sermon::publiclyVisible()
                    ->whereNotNull('series')
                    ->where('series', '!=', '')
                    ->distinct()
                    ->orderBy('series')
                    ->pluck('series')
            )->map(fn ($s) => ['id' => null, 'title' => $s, 'slug' => null, 'sermon_count' => 0]);
        }

        return Inertia::render('Public/Sermons', [
            'featured'        => $featured ? PublicSermonResource::make($featured) : null,
            'recent'          => PublicSermonResource::collection($recent),
            'series'          => $seriesList,
            'sermonsSubtitle' => $homepageSettings['sermons_page_subtitle'] ?? null,
        ]);
    }
}
