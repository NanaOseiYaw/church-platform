<?php

namespace App\Listeners\Broadcast;

use App\Events\Broadcast\NewNotification;
use App\Models\User;
use Illuminate\Notifications\Events\NotificationSent;

/**
 * Fires a WebSocket broadcast whenever a database notification is persisted.
 *
 * Laravel fires `NotificationSent` after each notification channel is processed.
 * We only care about the 'database' channel — that's what the frontend displays.
 *
 * The payload includes a fresh unread count so the badge stays accurate without
 * a full Inertia reload.
 */
class BroadcastOnNotificationSent
{
    public function handle(NotificationSent $event): void
    {
        // Only broadcast for database notifications (not mail, push, etc.)
        if ($event->channel !== 'database') {
            return;
        }

        /** @var User $notifiable */
        $notifiable = $event->notifiable;

        if (! $notifiable instanceof User) {
            return;
        }

        // Get a fresh unread count now that the notification is stored
        $unreadCount = $notifiable->unreadNotifications()->count();

        // Build the lightweight notification payload from the raw response
        // $event->response is the DatabaseNotification model that was created
        $notification = $event->response;

        $data = is_array($notification) ? $notification : ($notification->data ?? []);

        try {
            NewNotification::dispatch(
                userId:       $notifiable->id,
                unreadCount:  $unreadCount,
                notification: [
                    'id'         => is_object($notification) ? $notification->id : null,
                    'type'       => $data['type'] ?? 'general',
                    'title'      => $data['title'] ?? '',
                    'body'       => $data['body'] ?? '',
                    'action_url' => $data['action_url'] ?? null,
                    'actor'      => $data['actor'] ?? null,
                    'created_at' => now()->toISOString(),
                ],
            );
        } catch (\Illuminate\Broadcasting\BroadcastException) {
            // WebSocket server unavailable — real-time push skipped, no data loss
        }
    }
}
