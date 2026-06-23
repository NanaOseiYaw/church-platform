<?php

namespace App\Listeners\Notifications;

use App\Events\EventWasCreated;
use App\Events\EventWasUpdated;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Notification;

class EventNotificationSubscriber
{
    /**
     * Notify relevant members when an event is created.
     *
     * Gate rules (to avoid spamming large churches):
     *   – is_featured: notify all church members (only for public/members_only events)
     *   – department_only: notify department members regardless of is_featured
     *   – Other public events without is_featured: skip (rely on events page for discovery)
     *   – Draft events (no published_at): skip
     */
    public function handleEventCreated(EventWasCreated $event): void
    {
        $evt   = $event->event;
        $actor = $event->actor;

        // Skip unpublished drafts
        if (! $evt->published_at) {
            return;
        }

        $recipients = match ($evt->visibility) {
            'public', 'members_only' => $evt->is_featured
                ? User::where('church_id', $evt->church_id)
                    ->where('id', '!=', $actor->id)
                    ->get()
                : collect(),

            'department_only' => $evt->department_id
                ? $evt->department->members()
                    ->where('users.id', '!=', $actor->id)
                    ->get()
                : collect(),

            default => collect(),   // private → no notification
        };

        if ($recipients->isEmpty()) {
            return;
        }

        $when = $evt->start_at
            ? ' on ' . $evt->start_at->format('M j')
            : '';

        Notification::send($recipients, new AppNotification(
            notifType: AppNotification::TYPE_EVENT_CREATED,
            title:     'New event: ' . $evt->title,
            body:      ($evt->location ? "At {$evt->location}" : 'Event details inside') . $when,
            actionUrl: "/dashboard/events/{$evt->id}",
            actor:     ['name' => $actor->name, 'avatar' => $actor->avatar],
        ));
    }

    /**
     * Notify attendees (going + maybe RSVPs) when an event is updated.
     * Only fires for future events to avoid noise on past events.
     */
    public function handleEventUpdated(EventWasUpdated $event): void
    {
        $evt   = $event->event;
        $actor = $event->actor;

        // Only notify for events still in the future
        if ($evt->start_at->isPast()) {
            return;
        }

        // Load RSVPed users (going or maybe) — these are the "interested" users
        $attendees = $evt->rsvps()
            ->wherePivotIn('status', ['going', 'maybe'])
            ->where('users.id', '!=', $actor->id)
            ->get();

        if ($attendees->isEmpty()) {
            return;
        }

        Notification::send($attendees, new AppNotification(
            notifType: AppNotification::TYPE_EVENT_UPDATED,
            title:     'Event updated: ' . $evt->title,
            body:      "Details for \u{201c}{$evt->title}\u{201d} have been updated.",
            actionUrl: "/dashboard/events/{$evt->id}",
            actor:     ['name' => $actor->name, 'avatar' => $actor->avatar],
        ));
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            EventWasCreated::class => 'handleEventCreated',
            EventWasUpdated::class => 'handleEventUpdated',
        ];
    }
}
