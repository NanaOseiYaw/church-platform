<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * The single polymorphic in-app notification class used across the platform.
 *
 * All notification types (task_assigned, event_created, etc.) share this one
 * class; the listener that creates the notification passes the right data.
 * This keeps the notification layer thin while remaining fully extensible.
 *
 * Adding email:    implement toMail() and add 'mail' to via()
 * Adding push:     implement toBroadcast() and add 'broadcast' to via()
 * Adding queue:    implement ShouldQueue and add the Queueable trait
 */
class AppNotification extends Notification
{
    /**
     * Machine-readable type keys — used by the frontend to pick icons / colours.
     *
     * Keep in sync with NOTIFICATION_ICONS in NotificationDropdown.vue.
     */
    public const TYPE_TASK_ASSIGNED      = 'task_assigned';
    public const TYPE_TASK_UPDATED       = 'task_updated';
    public const TYPE_TASK_COMPLETED     = 'task_completed';
    public const TYPE_ANNOUNCEMENT       = 'announcement_published';
    public const TYPE_EVENT_CREATED      = 'event_created';
    public const TYPE_EVENT_UPDATED      = 'event_updated';
    public const TYPE_DEPT_MEMBER_ADDED  = 'department_member_added';
    public const TYPE_DEPT_ROLE_CHANGED  = 'department_role_changed';
    public const TYPE_SCHEDULING_ASSIGNED  = 'scheduling.assigned';
    public const TYPE_SCHEDULING_PUBLISHED = 'scheduling.published';
    public const TYPE_SCHEDULING_REMOVED   = 'scheduling.removed';
    public const TYPE_SCHEDULING_DECLINED  = 'scheduling.declined';
    public const TYPE_SERMON_SYNC_DONE     = 'sermon.sync.done';
    public const TYPE_SERMON_SYNC_FAILED   = 'sermon.sync.failed';
    public const TYPE_SERMON_PUBLISHED     = 'sermon.published';
    public const TYPE_BROADCAST            = 'broadcast';
    public const TYPE_BROADCAST_SENT       = 'broadcast.sent';   // notifies sender on delivery completion
    public const TYPE_EVENT_RSVP           = 'event.rsvp';       // notifies event creator on new RSVP
    public const TYPE_ROLE_CHANGED         = 'role_changed';     // notifies member when church role changes
    public const TYPE_PRAYER_REQUEST       = 'prayer_request';   // notifies admins on new public prayer request

    public function __construct(
        public readonly string  $notifType,
        public readonly string  $title,
        public readonly string  $body,
        public readonly ?string $actionUrl = null,
        public readonly ?array  $actor     = null,
    ) {}

    // ── Channels ───────────────────────────────────────────────────────────────

    /**
     * Return the notification channels.
     * Extend this when adding email / push / broadcast support.
     *
     * @return list<string>
     */
    public function via(mixed $notifiable): array
    {
        return ['database'];
    }

    // ── Channel serialisers ────────────────────────────────────────────────────

    /** Stored in the notifications.data column as JSON. */
    public function toDatabase(mixed $notifiable): array
    {
        return [
            'type'       => $this->notifType,
            'title'      => $this->title,
            'body'       => $this->body,
            'action_url' => $this->actionUrl,
            'actor'      => $this->actor,
        ];
    }

    // ── Future channel stubs ───────────────────────────────────────────────────
    //
    // public function toMail(mixed $notifiable): \Illuminate\Notifications\Messages\MailMessage
    // {
    //     return (new \Illuminate\Notifications\Messages\MailMessage)
    //         ->subject($this->title)
    //         ->line($this->body)
    //         ->action('View', url($this->actionUrl ?? '/dashboard/notifications'));
    // }
    //
    // public function toBroadcast(mixed $notifiable): \Illuminate\Notifications\Messages\BroadcastMessage
    // {
    //     return new \Illuminate\Notifications\Messages\BroadcastMessage([
    //         'type'       => $this->notifType,
    //         'title'      => $this->title,
    //         'body'       => $this->body,
    //         'action_url' => $this->actionUrl,
    //     ]);
    // }
}
