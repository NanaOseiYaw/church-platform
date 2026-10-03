<?php

namespace App\Models;

use App\Models\Concerns\HasMedia;
use App\Traits\BelongsToChurch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Announcement extends Model
{
    use SoftDeletes, BelongsToChurch, HasMedia;

    protected $fillable = [
        'church_id', 'department_id', 'created_by', 'title', 'body',
        'category', 'is_pinned', 'is_church_wide', 'visibility', 'is_featured',
        'priority', 'published_at', 'expires_at', 'cover_image',
    ];

    protected $casts = [
        'is_pinned'      => 'boolean',
        'is_church_wide' => 'boolean',
        'is_featured'    => 'boolean',
        'published_at'   => 'datetime',
        'expires_at'     => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(AnnouncementRead::class);
    }

    // ── Query scopes ───────────────────────────────────────────────────────────

    /**
     * Only return announcements that are currently live:
     * published_at is set and in the past, and not yet expired.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function scopePinned(Builder $query): Builder
    {
        return $query->where('is_pinned', true);
    }

    /**
     * Scope to announcements visible to the given user, based on the
     * 4-level visibility field:
     *   'public'          → all members (and public site)
     *   'members_only'    → any authenticated church member
     *   'department_only' → only members of the linked department
     *   'private'         → admins / creator only (handled upstream by the service)
     *
     * Does NOT apply the published filter — call ->published() separately
     * when you want to hide drafts.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            // public or members_only → all church members can read
            $q->whereIn('visibility', ['public', 'members_only'])
              // department_only → user must belong to that department
              ->orWhere(function (Builder $inner) use ($user) {
                  $inner->where('visibility', 'department_only')
                        ->whereHas('department.members', fn ($m) => $m->where('users.id', $user->id));
              })
              // private → only the creator
              ->orWhere(function (Builder $inner) use ($user) {
                  $inner->where('visibility', 'private')
                        ->where('created_by', $user->id);
              });
        });
    }

    /**
     * Scope for the public-facing website: only published, non-expired,
     * explicitly public announcements (visibility = 'public').
     */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $this->scopePublished($query)->where('visibility', 'public');
    }

    // ── Accessors / helpers ────────────────────────────────────────────────────

    /** True when the given user has read this announcement. */
    public function isReadBy(User $user): bool
    {
        return $this->reads()->where('user_id', $user->id)->exists();
    }

    /** Human-readable publication status for admin UI. */
    public function getStatusAttribute(): string
    {
        if ($this->published_at === null) {
            return 'draft';
        }
        if ($this->published_at->isFuture()) {
            return 'scheduled';
        }
        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return 'expired';
        }
        return 'published';
    }
}
