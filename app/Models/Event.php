<?php

namespace App\Models;

use App\Models\Concerns\HasMedia;
use App\Traits\BelongsToChurch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use SoftDeletes, BelongsToChurch, HasMedia;

    protected $fillable = [
        'church_id', 'department_id', 'created_by',
        'title', 'description', 'location', 'cover_image', 'category',
        'start_at', 'end_at', 'all_day',
        'visibility', 'is_public', 'is_cancelled',
        'published_at', 'is_featured',
        'is_recurring', 'recurrence_rule',
        'rsvp_enabled', 'capacity',
    ];

    protected $casts = [
        'start_at'     => 'datetime',
        'end_at'       => 'datetime',
        'published_at' => 'datetime',
        'all_day'      => 'boolean',
        'is_recurring' => 'boolean',
        'is_public'    => 'boolean',
        'is_cancelled' => 'boolean',
        'is_featured'  => 'boolean',
        'rsvp_enabled' => 'boolean',
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

    /**
     * All users who have RSVPed.
     * Pivot columns: status (going | maybe | not_going), created_at, updated_at.
     */
    public function rsvps(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_rsvp')
            ->withPivot('status')
            ->withTimestamps();
    }

    /**
     * Attendance sessions created for this event (e.g. morning service check-in).
     * An event may have multiple sessions (morning + evening service).
     */
    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class);
    }

    // ── Query scopes ───────────────────────────────────────────────────────────

    /**
     * Only return events that have been published (published_at is set and in the past).
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * Scope for the public-facing website: published, not cancelled,
     * explicitly public visibility events.
     */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $this->scopePublished($query)
            ->where('is_cancelled', false)
            ->where('visibility', 'public');
    }

    /** Events whose start time is in the future (not yet begun). */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('start_at', '>', now());
    }

    /** Events currently in progress. */
    public function scopeOngoing(Builder $query): Builder
    {
        return $query->where('start_at', '<=', now())
                     ->where(fn ($q) => $q->whereNull('end_at')->orWhere('end_at', '>=', now()))
                     ->where('is_cancelled', false);
    }

    /** Events that have already finished. */
    public function scopePast(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNotNull('end_at')->where('end_at', '<', now())
              ->orWhere(fn ($inner) => $inner->whereNull('end_at')->where('start_at', '<', now()));
        });
    }

    /** Only non-cancelled events. */
    public function scopeNotCancelled(Builder $query): Builder
    {
        return $query->where('is_cancelled', false);
    }

    /**
     * Filter events the given user is allowed to see.
     *
     * Visibility rules:
     *   – 'public'          → all church members (public site: unauthenticated too)
     *   – 'members_only'    → any authenticated church member
     *   – 'department_only' → only members of the event's department
     *   – 'private'         → admins / creator only
     *
     * Admins/coordinators with events.edit bypass visibility.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('events.edit')) {
            return $query; // admins see everything, including drafts and private
        }

        return $query->where(function (Builder $q) use ($user) {
            // public or members_only → all church members can see
            $q->whereIn('visibility', ['public', 'members_only'])
              // department_only → must be a member of that department
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

    // ── RSVP aggregation ───────────────────────────────────────────────────────

    /**
     * Single source of truth for the going/maybe count constraints.
     *
     * Used in both EventService::paginate() (->withCount()) and
     * EventsController::show() (->loadCount()) so the two query paths
     * always count by identical rules.
     *
     * We reference the pivot table by its full name ('event_rsvp.status')
     * instead of calling wherePivot() because the closure in withCount/loadCount
     * receives an Eloquent Builder, not a BelongsToMany instance.
     *
     * @return array<string, \Closure>
     */
    public static function rsvpCountConstraints(): array
    {
        return [
            'rsvps as going_count' => fn ($q) => $q->where('event_rsvp.status', 'going'),
            'rsvps as maybe_count' => fn ($q) => $q->where('event_rsvp.status', 'maybe'),
        ];
    }

    // ── Accessors ──────────────────────────────────────────────────────────────

    /**
     * Derive event status from timestamps + cancellation flag.
     * Computed; never stored.
     */
    public function getStatusAttribute(): string
    {
        if ($this->is_cancelled) {
            return 'cancelled';
        }

        if ($this->start_at->isFuture()) {
            return 'upcoming';
        }

        if (
            $this->end_at === null ||
            $this->end_at->isFuture() ||
            $this->end_at->isCurrentDay()
        ) {
            return 'ongoing';
        }

        return 'completed';
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /** Return the given user's RSVP status for this event, or null if none. */
    public function userRsvp(User $user): ?string
    {
        return $this->rsvps()
            ->where('users.id', $user->id)
            ->first()
            ?->pivot->status;
    }
}
