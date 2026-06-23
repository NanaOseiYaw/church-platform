<?php

namespace App\Listeners\Broadcast;

use App\Events\AnnouncementWasPublished;
use App\Events\Broadcast\AnnouncementWentLive;
use Illuminate\Events\Dispatcher;

/**
 * Listens to AnnouncementWasPublished and fires a WebSocket broadcast
 * to the church channel so connected users get a live feed update.
 */
class BroadcastAnnouncementSubscriber
{
    public function handleAnnouncementPublished(AnnouncementWasPublished $event): void
    {
        $ann = $event->announcement;

        if (! $ann->church_id) {
            return;
        }

        try {
            AnnouncementWentLive::dispatch(
                announcementId: $ann->id,
                churchId:       $ann->church_id,
                title:          $ann->title,
                isPinned:       (bool) $ann->is_pinned,
                publishedAt:    $ann->published_at?->toISOString() ?? now()->toISOString(),
            );
        } catch (\Illuminate\Broadcasting\BroadcastException) {
            // WebSocket server unavailable — real-time push skipped, no data loss
        }
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            AnnouncementWasPublished::class => 'handleAnnouncementPublished',
        ];
    }
}
