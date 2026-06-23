<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class AnnouncementService
{
    /**
     * Return a paginated, tenant-safe announcement feed for the given user.
     *
     * Visibility rules:
     *   – Users with announcements.edit see ALL (including drafts).
     *   – Coordinators see published + visible + their own drafts.
     *   – Members see published + (church-wide OR their departments).
     */
    public function paginate(
        int $churchId,
        User $user,
        ?string $filter = null,
        ?string $search  = null,
        int $perPage     = 20,
    ): LengthAwarePaginator {
        $query = Announcement::forChurch($churchId)
            ->with([
                'creator:id,name,avatar',
                'department:id,name,icon,color',
            ])
            ->withCount('reads as reads_count');

        // ── Visibility ─────────────────────────────────────────────────────
        if ($user->can('announcements.edit')) {
            // Admins see everything — no additional filter
        } elseif ($user->can('announcements.create')) {
            // Coordinators: published + visible + their own drafts
            $query->where(function (Builder $q) use ($user) {
                $q->where(fn ($pub) => $pub->published()->visibleTo($user))
                  ->orWhere('created_by', $user->id);
            });
        } else {
            // Members: only published and visible
            $query->published()->visibleTo($user);
        }

        // ── Tab filter ─────────────────────────────────────────────────────
        match ($filter) {
            'pinned'     => $query->where('is_pinned', true),
            'church'     => $query->where('visibility', 'public'),
            'department' => $query->where('visibility', 'department_only'),
            'mine'       => $query->where('created_by', $user->id),
            'drafts'     => $query->whereNull('published_at'),
            default      => null,
        };

        // ── Search ─────────────────────────────────────────────────────────
        if ($search) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('body',  'like', "%{$search}%");
            });
        }

        return $query
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    // ── CRUD ───────────────────────────────────────────────────────────────────

    public function create(array $data, int $churchId, int $creatorId): Announcement
    {
        return Announcement::create(array_merge(
            $this->normalisePublishedAt($data),
            ['church_id' => $churchId, 'created_by' => $creatorId],
        ));
    }

    public function update(Announcement $announcement, array $data): Announcement
    {
        $announcement->update($this->normalisePublishedAt($data));
        return $announcement->refresh();
    }

    public function delete(Announcement $announcement): void
    {
        $announcement->delete();
    }

    // ── Publishing ─────────────────────────────────────────────────────────────

    public function publish(Announcement $announcement): Announcement
    {
        $announcement->update(['published_at' => now()]);
        return $announcement->refresh();
    }

    public function unpublish(Announcement $announcement): Announcement
    {
        $announcement->update(['published_at' => null]);
        return $announcement->refresh();
    }

    public function togglePin(Announcement $announcement): Announcement
    {
        $announcement->update(['is_pinned' => ! $announcement->is_pinned]);
        return $announcement->refresh();
    }

    // ── Read tracking ──────────────────────────────────────────────────────────

    /**
     * Record that the given user has read this announcement.
     * Safe to call multiple times — uses firstOrCreate.
     */
    public function markRead(Announcement $announcement, User $user): void
    {
        AnnouncementRead::firstOrCreate(
            ['announcement_id' => $announcement->id, 'user_id' => $user->id],
            ['read_at' => now()],
        );
    }

    /**
     * Count unread published announcements visible to the given user.
     * Used for the sidebar badge in HandleInertiaRequests.
     */
    public function unreadCount(int $churchId, User $user): int
    {
        try {
            return Announcement::forChurch($churchId)
                ->published()
                ->visibleTo($user)
                ->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $user->id))
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    // ── Public feed ────────────────────────────────────────────────────────────

    /**
     * Return announcements suitable for the public-facing website.
     * Only published, non-expired, 'public' visibility announcements are returned.
     * Results are tenant-scoped via the global BelongsToChurch scope.
     *
     * @param  int  $churchId
     * @param  int  $limit
     * @param  bool $featuredOnly  When true, only is_featured = true records
     */
    public function publicFeed(int $churchId, int $limit = 10, bool $featuredOnly = false): \Illuminate\Database\Eloquent\Collection
    {
        return Announcement::forChurch($churchId)
            ->publiclyVisible()
            ->when($featuredOnly, fn ($q) => $q->where('is_featured', true))
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get(['id', 'title', 'body', 'category', 'priority', 'is_pinned', 'is_featured', 'published_at']);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Normalise the form payload before persisting:
     *   published_at:
     *     'now'         → current timestamp (publish immediately)
     *     empty/null    → null (save as draft)
     *     anything else → pass through (ISO-8601 scheduled datetime)
     *
     *   visibility → sync is_church_wide for backward compatibility:
     *     'public'          → is_church_wide = true
     *     anything else     → is_church_wide = false
     */
    private function normalisePublishedAt(array $data): array
    {
        if (($data['published_at'] ?? null) === 'now') {
            $data['published_at'] = now();
        } elseif (empty($data['published_at'])) {
            $data['published_at'] = null;
        }

        // Keep legacy is_church_wide in sync so existing queries that
        // haven't been migrated yet continue to work correctly.
        if (isset($data['visibility'])) {
            $data['is_church_wide'] = $data['visibility'] === 'public';
        }

        return $data;
    }
}
