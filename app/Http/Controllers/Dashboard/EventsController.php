<?php

namespace App\Http\Controllers\Dashboard;

use App\Events\EventWasCreated;
use App\Events\EventWasUpdated;
use App\Http\Controllers\Concerns\ResolvesChurchData;
use App\Notifications\AppNotification;
use App\Http\Controllers\Controller;
use App\Traits\LogsAuditEvents;
use App\Http\Requests\Event\StoreEventRequest;
use App\Http\Requests\Event\UpdateEventRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Services\EventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventsController extends Controller
{
    use ResolvesChurchData;
    use LogsAuditEvents;

    public function __construct(
        private readonly EventService $events,
    ) {}

    // ── Resource actions ───────────────────────────────────────────────────────

    /** GET /dashboard/events */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Event::class);

        $user     = $request->user();
        $churchId = $this->resolvedChurchId();

        $events = $this->events->paginate(
            churchId: $churchId,
            user:     $user,
            filter:   $request->filter,
            search:   $request->search,
        )->through(fn ($e) => EventResource::make($e)->toArray($request));

        return Inertia::render('Dashboard/Events/Index', [
            'events'      => $events,
            'departments' => $this->activeDepartments(),
            'filters'     => $request->only('filter', 'search'),
            'canCreate'   => $user->can('create', Event::class),
        ]);
    }

    /** GET /dashboard/events/create */
    public function create(Request $request): Response
    {
        $this->authorize('create', Event::class);

        return Inertia::render('Dashboard/Events/Create', [
            'departments' => $this->activeDepartments(),
        ]);
    }

    /** POST /dashboard/events */
    public function store(StoreEventRequest $request): RedirectResponse
    {
        $actor = $request->user();
        $event = $this->events->create(
            $request->validated(),
            $this->resolvedChurchId(),
            $actor->id,
        );

        // Load department relation needed by the subscriber
        EventWasCreated::dispatch($event->load('department'), $actor);

        $this->auditLog('event.created', $event, [], [], ['department_id' => $event->department_id]);

        return redirect()
            ->route('dashboard.events.show', $event)
            ->with('success', "\u{201c}{$event->title}\u{201d} created.");
    }

    /** GET /dashboard/events/{event} */
    public function show(Request $request, Event $event): Response
    {
        $this->authorize('view', $event);

        $user = $request->user();

        $event->load([
            'creator:id,name,avatar',
            'department:id,name,icon,color',
            'files.uploader:id,name,avatar',
        ]);
        $event->loadCount(Event::rsvpCountConstraints());

        return Inertia::render('Dashboard/Events/Show', [
            'event'      => EventResource::make($event),
            'myRsvp'     => $event->rsvp_enabled ? $this->events->getUserRsvp($event, $user) : null,
            'canEdit'    => $user->can('update', $event),
            'canDelete'  => $user->can('delete', $event),
            'canRsvp'    => $user->can('rsvp', $event),
            'canUpload'  => $user->can('create', \App\Models\File::class),
        ]);
    }

    /** GET /dashboard/events/{event}/edit */
    public function edit(Request $request, Event $event): Response
    {
        $this->authorize('update', $event);

        $event->load('department:id,name');

        return Inertia::render('Dashboard/Events/Edit', [
            'event'       => EventResource::make($event),
            'departments' => $this->activeDepartments(),
        ]);
    }

    /** PUT /dashboard/events/{event} */
    public function update(UpdateEventRequest $request, Event $event): RedirectResponse
    {
        $actor = $request->user();
        $this->events->update($event, $request->validated());

        $this->auditLog(
            'event.updated',
            $event,
            [],
            [],
            ['department_id' => $event->department_id],
        );

        EventWasUpdated::dispatch($event->fresh(), $actor);

        return redirect()
            ->route('dashboard.events.show', $event)
            ->with('success', 'Event updated.');
    }

    /** DELETE /dashboard/events/{event} */
    public function destroy(Event $event): RedirectResponse
    {
        $this->authorize('delete', $event);

        $deptId = $event->department_id;
        $title  = $event->title;
        $this->auditLog('event.deleted', $event, [], [], ['department_id' => $deptId]);
        $this->events->delete($event);

        return redirect()
            ->route('dashboard.events.index')
            ->with('success', "\u{201c}{$title}\u{201d} deleted.");
    }

    // ── RSVP ───────────────────────────────────────────────────────────────────

    /** POST /dashboard/events/{event}/rsvp */
    public function rsvp(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('rsvp', $event);

        $request->validate([
            'status' => ['required', 'in:going,maybe,not_going'],
        ]);

        $user       = $request->user();
        $newStatus  = $request->status;
        $currentRsvp = $this->events->getUserRsvp($event, $user);

        // Toggle: clicking the same status cancels the RSVP
        if ($currentRsvp === $newStatus) {
            $this->events->removeRsvp($event, $user);
            $this->auditLog('event.rsvp.cancelled', $event, [], [], ['department_id' => $event->department_id]);
            return back()->with('success', 'RSVP removed.');
        }

        $this->events->upsertRsvp($event, $user, $newStatus);
        $this->auditLog('event.rsvp.created', $event, [], ['status' => $newStatus], ['department_id' => $event->department_id]);

        // Notify the event creator if someone else RSVPs as "going"
        $creator = $event->created_by ? $event->creator : null;
        if ($creator && $creator->id !== $user->id && $newStatus === 'going') {
            $creator->notify(new AppNotification(
                AppNotification::TYPE_EVENT_RSVP,
                "{$user->name} is going to \"{$event->title}\"",
                'New RSVP confirmed for your event.',
                '/dashboard/events/' . $event->id,
                ['name' => $user->name, 'avatar' => $user->avatar],
            ));
        }

        return back()->with('success', 'RSVP updated.');
    }

    /** DELETE /dashboard/events/{event}/rsvp */
    public function cancelRsvp(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('rsvp', $event);

        $this->events->removeRsvp($event, $request->user());
        $this->auditLog('event.rsvp.cancelled', $event, [], [], ['department_id' => $event->department_id]);
        return back()->with('success', 'RSVP removed.');
    }
}
