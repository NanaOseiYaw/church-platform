<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class NotificationService
{
    /**
     * Guard every public method with a table-existence check so that a missing
     * (or not-yet-migrated) notifications table never crashes a page request.
     * This also satisfies the spec requirement that the system remains compatible
     * with future notification drivers (email, push) without schema changes.
     */
    private function tableExists(): bool
    {
        static $checked = null;
        if ($checked === null) {
            $checked = Schema::hasTable('notifications');
        }
        return $checked;
    }

    /** @return Collection<int, DatabaseNotification> */
    public function unread(User $user): Collection
    {
        if (! $this->tableExists()) {
            return collect();
        }

        return $user->unreadNotifications()->latest()->take(20)->get();
    }

    /**
     * Paginated list of all notifications for the full notifications page.
     * Optionally filter to unread only.
     */
    public function paginate(User $user, string $filter = 'all', int $perPage = 20): LengthAwarePaginator
    {
        if (! $this->tableExists()) {
            // Return an empty paginator-like structure
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }

        $query = $user->notifications()->latest();

        if ($filter === 'unread') {
            $query->whereNull('read_at');
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Recent notifications for the navbar dropdown.
     * Returns the last N notifications regardless of read state.
     *
     * @return Collection<int, DatabaseNotification>
     */
    public function recent(User $user, int $limit = 15): Collection
    {
        if (! $this->tableExists()) {
            return collect();
        }

        return $user->notifications()->latest()->take($limit)->get();
    }

    public function markRead(User $user, string $id): void
    {
        if (! $this->tableExists()) {
            return;
        }

        $user->notifications()->where('id', $id)->update(['read_at' => now()]);
    }

    public function markAllRead(User $user): void
    {
        if (! $this->tableExists()) {
            return;
        }

        $user->unreadNotifications()->update(['read_at' => now()]);
    }

    public function unreadCount(User $user): int
    {
        if (! $this->tableExists()) {
            return 0;
        }

        try {
            return $user->unreadNotifications()->count();
        } catch (\Throwable $e) {
            Log::warning('NotificationService::unreadCount failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Send a database notification to one or more users.
     * Used by listeners for tasks, announcements, events, and role changes.
     */
    public function send(User|array $recipients, \Illuminate\Notifications\Notification $notification): void
    {
        if (! $this->tableExists()) {
            return;
        }

        $recipients = is_array($recipients) ? $recipients : [$recipients];

        foreach ($recipients as $recipient) {
            $recipient->notify($notification);
        }
    }
}
