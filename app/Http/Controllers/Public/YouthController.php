<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicEventResource;
use App\Models\Department;
use App\Services\EventService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dedicated, bespoke public page for the Youth Ministry.
 *
 * A *custom* ministry experience that proves the platform can give a single
 * ministry its own branded page while reusing the shared layout, navbar,
 * footer, and Church of Pentecost palette. All other ministries keep the
 * generic /ministries grid.
 *
 * The page is resilient by design: every prop is optional and the Vue page
 * falls back to curated content so it never looks broken in a demo.
 */
class YouthController extends Controller
{
    public function __construct(private readonly EventService $events) {}

    public function __invoke(): Response
    {
        $churchId = app('church.id');

        // BelongsToChurch global scope keeps this tenant-safe automatically.
        $youth = Department::query()
            ->where('is_active', true)
            ->where('name', 'like', '%youth%')
            ->with('coordinator:id,name')
            ->orderBy('id')
            ->first();

        // Real upcoming public events — reuses the same feed the public Events
        // page uses, so the section stays in sync with the rest of the site.
        $events = $churchId !== null
            ? $this->events->publicFeed(churchId: $churchId, limit: 4, upcomingOnly: true, featuredOnly: false)
            : collect();

        return Inertia::render('Public/Youth', [
            'leader'    => $youth?->coordinator?->name,
            'youthName' => $youth?->name,
            'events'    => $events->map(fn ($e) => PublicEventResource::make($e)->toArray(request()))->values(),
            // ISO override for the countdown. When null the page targets the
            // soonest real event, then falls back to the next Friday 6 PM.
            'nextGathering' => null,
        ]);
    }
}
