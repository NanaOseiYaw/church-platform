<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Sermon;
use App\Models\SermonSeries;
use Inertia\Inertia;
use Inertia\Response;

class SeriesController extends Controller
{
    /**
     * GET /series — list all active series for the church
     */
    public function index(): Response
    {
        $churchId = app('church.id');

        $seriesList = SermonSeries::where('church_id', $churchId)
            ->where('is_active', true)
            ->withCount(['sermons' => fn ($q) => $q->where('is_public', true)])
            ->orderByDesc('started_at')
            ->get()
            ->map(fn ($s) => [
                'id'           => $s->id,
                'title'        => $s->title,
                'description'  => $s->description,
                'started_at'   => $s->started_at?->format('M Y'),
                'ended_at'     => $s->ended_at?->format('M Y'),
                'sermon_count' => $s->sermons_count,
            ]);

        return Inertia::render('Public/Series', [
            'seriesList' => $seriesList,
        ]);
    }

    /**
     * GET /series/{series} — list public sermons in a series
     */
    public function show(SermonSeries $series): Response
    {
        abort_unless($series->church_id === app('church.id'), 404);
        abort_unless($series->is_active, 404);

        // Use the shared publiclyVisible() contract rather than the raw legacy
        // is_public column: a sermon explicitly marked visibility='members_only'
        // can still carry is_public=true from before the visibility field
        // existed, and would otherwise leak onto the public series page.
        $sermons = Sermon::where('church_id', $series->church_id)
            ->where('series_id', $series->id)
            ->publiclyVisible()
            ->orderByDesc('preached_at')
            ->get()
            ->map(fn ($s) => [
                'id'          => $s->id,
                'title'       => $s->title,
                'slug'        => $s->slug,
                'speaker'     => $s->speaker,
                'preached_at' => $s->preached_at?->format('j M Y'),
                'thumbnail'   => $s->thumbnail,
                'duration'    => $s->duration,
                'video_url'   => $s->video_url,
            ]);

        return Inertia::render('Public/SeriesShow', [
            'series'  => [
                'id'          => $series->id,
                'title'       => $series->title,
                'description' => $series->description,
                'cover_image' => $series->cover_image,
                'started_at'  => $series->started_at?->format('M Y'),
                'ended_at'    => $series->ended_at?->format('M Y'),
            ],
            'sermons' => $sermons,
        ]);
    }
}
