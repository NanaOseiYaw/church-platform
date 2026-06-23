<?php

namespace App\Listeners\Notifications;

use App\Events\AnnouncementWasPublished;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Notification;

class AnnouncementNotificationSubscriber
{
    /**
     * Notify relevant members when a notable announcement is published.
     *
     * "Notable" = pinned OR high/urgent priority.
     * Regular medium/low announcements rely on the sidebar badge counter
     * instead of individual per-user notifications to avoid flooding.
     *
     * Recipients are scoped by visibility:
     *   public / members_only  → all church members
     *   department_only        → that department's members
     *   private                → no notification
     */
    public function handleAnnouncementPublished(AnnouncementWasPublished $event): void
    {
        $ann   = $event->announcement;
        $actor = $event->actor;

        // Gate: only notify for notable content
        $notable = $ann->is_pinned || in_array($ann->priority, ['high', 'urgent'], true);
        if (! $notable) {
            return;
        }

        // Resolve recipients
        $recipients = $this->resolveRecipients($ann, $actor);
        if ($recipients->isEmpty()) {
            return;
        }

        $notification = new AppNotification(
            notifType: AppNotification::TYPE_ANNOUNCEMENT,
            title:     $ann->is_pinned ? "\U0001F4CC {$ann->title}" : $ann->title,
            body:      $this->excerpt($ann->body),
            actionUrl: "/dashboard/announcements/{$ann->id}",
            actor:     ['name' => $actor->name, 'avatar' => $actor->avatar],
        );

        // Use Notification::send() for efficient bulk dispatch
        Notification::send($recipients, $notification);
    }

    /** @return \Illuminate\Support\Collection<int, User> */
    private function resolveRecipients(\App\Models\Announcement $ann, User $actor): \Illuminate\Support\Collection
    {
        return match ($ann->visibility) {
            'public', 'members_only' => User::where('church_id', $ann->church_id)
                ->where('id', '!=', $actor->id)
                ->get(),

            'department_only' => $ann->department_id
                ? $ann->department->members()
                    ->where('users.id', '!=', $actor->id)
                    ->get()
                : collect(),

            default => collect(),   // private → no notification
        };
    }

    private function excerpt(string $body, int $maxLength = 120): string
    {
        $plain = strip_tags($body);
        return mb_strlen($plain) > $maxLength
            ? mb_substr($plain, 0, $maxLength) . '…'
            : $plain;
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            AnnouncementWasPublished::class => 'handleAnnouncementPublished',
        ];
    }
}
