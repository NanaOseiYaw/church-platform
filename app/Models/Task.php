<?php

namespace App\Models;

use App\Models\Concerns\HasMedia;
use App\Traits\BelongsToChurch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use SoftDeletes, BelongsToChurch, HasMedia;

    const STATUSES   = ['pending', 'in_progress', 'completed', 'overdue', 'cancelled'];
    const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    protected $fillable = [
        'church_id', 'department_id', 'assigned_to', 'assigned_by',
        'title', 'description', 'priority', 'status', 'due_at', 'completed_at',
    ];

    protected $casts = [
        'due_at'       => 'datetime',
        'completed_at' => 'datetime',
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

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->latest();
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('status', 'in_progress');
    }

    /**
     * A task is "overdue" when its due date is in the past and it has not
     * been completed or cancelled — regardless of whether the scheduler has
     * stamped the DB status column as 'overdue' yet.
     *
     * This definition intentionally matches the is_overdue field computed
     * by TaskResource so that the Overdue filter tab and the red visual
     * indicators in TaskCard always show the same set of tasks.
     *
     * The scheduler's markOverdue() command remains useful for ordering and
     * external integrations, but it is not the authoritative "is overdue" signal.
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->whereNotIn('status', ['completed', 'cancelled']);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Active = not completed, not cancelled (pending + in_progress + overdue)
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['completed', 'cancelled']);
    }

    /**
     * Visibility scope: admins see all; members see only assigned-to-them tasks.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('tasks.view_all')) {
            return $query;
        }

        return $query->where('assigned_to', $user->id);
    }
}
