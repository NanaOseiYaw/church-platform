<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicEventResource;
use App\Models\Event;
use App\Services\EventService;
use Inertia\Inertia;
use Inertia\Response;

class EventsController extends Controller
{
    public function __construct(private readonly EventService $events) {}

    /** GET /events/{event} — public single-event detail page */
    public function show(Event $event): Response
    {
        $churchId = app('church.id');

        abort_unless($event->church_id === $churchId, 404);
        abort_unless($event->published_at !== null, 404);
        abort_if($event->visibility === 'private', 404);

        return Inertia::render('Public/EventShow', [
            'event' => PublicEventResource::make($event)->toArray(request()),
        ]);
    }

    public function __invoke(): Response
    {
        $churchId = app('church.id');

        $events = $churchId !== null
            ? $this->events->publicFeed(churchId: $churchId, limit: 50, upcomingOnly: true, featuredOnly: false)
            : collect();

        // Derive unique categories from the real data (plus 'All' sentinel)
        $categories = collect(['All'])
            ->merge(
                $events->pluck('category')
                    ->filter()
                    ->unique()
                    ->sort()
                    ->values()
            )
            ->all();

        return Inertia::render('Public/Events', [
            'events'     => $events->map(fn ($e) => PublicEventResource::make($e)->toArray(request()))->values(),
            'categories' => $categories,
        ]);
    }
}
