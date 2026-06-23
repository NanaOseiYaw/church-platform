<?php

namespace App\Services;

use App\Models\Event;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class EventService
{
    /**
     * Return a paginated, tenant-safe event feed for the given user.
     *
     * Visibility rules:
     *   – Users with events.edit see ALL events (incl. cancelled & dept-only).
     *   – All others see only events visible to them via scopeVisibleTo.
     *
     * @param  string|null  $filter   'upcoming' | 'ongoing' | 'past' | 'mine' | 'cancelled' | null (all)
     * @param  string|null  $search   Full-text search against title / location / description
     */
    public function paginate(
        int $churchId,
        User $user,
        ?string $filter  = null,
        ?string $search  = null,
        int $perPage     = 20,
    ): LengthAwarePaginator {
        $query = Event::forChurch($churchId)
            ->with(['creator:id,name,avatar', 'department:id,name,icon,color'])
            ->withCount(Event::rsvpCountConstraints());

        // ── Visibility ─────────────────────────────────────────────────────────
        if (! $user->can('events.edit')) {
            $query->visibleTo($user);
        }

        // ── Tab filter ─────────────────────────────────────────────────────────
        match ($filter) {
            'upcoming'  => $query->upcoming()->notCancelled(),
            'ongoing'   => $query->ongoing(),
            'past'      => $query->past()->notCancelled(),
            'mine'      => $query->where('created_by', $user->id),
            'cancelled' => $query->where('is_cancelled', true),
            default     => null,
        };

        // ── Search ─────────────────────────────────────────────────────────────
        if ($search) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('title',       'like', "%{$search}%")
                  ->orWhere('location',  'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $query
            ->orderBy('start_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    // ── CRUD ───────────────────────────────────────────────────────────────────

    public function create(array $data, int $churchId, int $creatorId): Event
    {
        return Event::create(array_merge(
            $this->normalise($data),
            ['church_id' => $churchId, 'created_by' => $creatorId],
        ));
    }

    public function update(Event $event, array $data): Event
    {
        $event->update($this->normalise($data));
        return $event->refresh();
    }

    public function delete(Event $event): void
    {
        $event->delete();
    }

    public function cancel(Event $event): Event
    {
        $event->update(['is_cancelled' => true]);
        return $event->refresh();
    }

    public function restore(Event $event): Event
    {
        $event->update(['is_cancelled' => false]);
        return $event->refresh();
    }

    // ── RSVP ───────────────────────────────────────────────────────────────────

    /**
     * Upsert the user's RSVP for an event.
     * Status must be one of: going | maybe | not_going
     */
    public function upsertRsvp(Event $event, User $user, string $status): void
    {
        $existing = $event->rsvps()->where('users.id', $user->id)->exists();

        if ($existing) {
            $event->rsvps()->updateExistingPivot($user->id, ['status' => $status]);
        } else {
            $event->rsvps()->attach($user->id, ['status' => $status]);
        }
    }

    /** Remove a user's RSVP entirely. */
    public function removeRsvp(Event $event, User $user): void
    {
        $event->rsvps()->detach($user->id);
    }

    /** Return the given user's current RSVP status, or null if not RSVPed. */
    public function getUserRsvp(Event $event, User $user): ?string
    {
        return $event->userRsvp($user);
    }

    /**
     * Convenience wrapper used by the DashboardController and public site.
     * Returns upcoming published events without requiring a full User object.
     *
     * When $publicOnly = true, filters to visibility = 'public' (for the
     * public website). Otherwise returns all published upcoming events
     * (used by the dashboard overview widget).
     */
    public function upcoming(int $churchId, bool $publicOnly = false): LengthAwarePaginator
    {
        return Event::forChurch($churchId)
            ->published()
            ->upcoming()
            ->notCancelled()
            ->when($publicOnly, fn ($q) => $q->where('visibility', 'public'))
            ->orderBy('start_at')
            ->paginate(12)
            ->withQueryString();
    }

    // ── Public feed ────────────────────────────────────────────────────────────

    /**
     * Return events suitable for the public-facing website.
     * Only published, not-cancelled, 'public' visibility events are returned.
     *
     * @param  int   $churchId
     * @param  int   $limit
     * @param  bool  $upcomingOnly   When true, only start_at > now()
     * @param  bool  $featuredOnly   When true, only is_featured = true
     */
    public function publicFeed(
        int $churchId,
        int $limit        = 12,
        bool $upcomingOnly = true,
        bool $featuredOnly = false,
    ): \Illuminate\Database\Eloquent\Collection {
        return Event::forChurch($churchId)
            ->publiclyVisible()
            ->when($upcomingOnly,  fn ($q) => $q->upcoming())
            ->when($featuredOnly,  fn ($q) => $q->where('is_featured', true))
            ->orderBy('start_at')
            ->limit($limit)
            ->get(['id', 'title', 'description', 'location', 'cover_image', 'category',
                   'start_at', 'end_at', 'all_day', 'visibility', 'is_featured', 'published_at']);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Normalise form payload before persistence:
     *   – Sync is_public from visibility for backward compatibility
     *   – Normalise published_at: 'now' → timestamp, empty → null (draft)
     *   – Coerce empty strings to null on nullable fields
     *   – Strip time component on all-day events
     */
    private function normalise(array $data): array
    {
        // Keep is_public in sync with visibility for backward compat
        $data['is_public'] = in_array($data['visibility'] ?? 'public', ['public', 'members_only'], true);

        // Normalise published_at (same pattern as announcements)
        if (($data['published_at'] ?? null) === 'now') {
            $data['published_at'] = now();
        } elseif (array_key_exists('published_at', $data) && empty($data['published_at'])) {
            $data['published_at'] = null;
        }

        // Nullable fields: coerce empty string → null
        foreach (['end_at', 'location', 'description', 'category', 'department_id', 'capacity'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        // All-day events: strip time component
        if (! empty($data['all_day'])) {
            $data['start_at'] = date('Y-m-d', strtotime((string) $data['start_at']));
            if (! empty($data['end_at'])) {
                $data['end_at'] = date('Y-m-d', strtotime((string) $data['end_at']));
            }
        }

        return $data;
    }
}
