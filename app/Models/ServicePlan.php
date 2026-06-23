<?php

namespace App\Models;

use App\Traits\BelongsToChurch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServicePlan extends Model
{
    use BelongsToChurch, SoftDeletes;

    protected $fillable = [
        'church_id', 'created_by', 'published_by',
        'title', 'description', 'scheduled_at', 'location',
        'status', 'notes', 'published_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function planPositions(): HasMany
    {
        return $this->hasMany(ServicePlanPosition::class)->orderBy('sort_order');
    }

    /** The single attendance session linked to this plan (if any). */
    public function attendanceSession(): HasOne
    {
        return $this->hasOne(AttendanceSession::class);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('scheduled_at', '>=', now())->orderBy('scheduled_at');
    }

    public function scopePast(Builder $query): Builder
    {
        return $query->where('scheduled_at', '<', now())->orderByDesc('scheduled_at');
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', 'archived');
    }

    public function isDraft(): bool     { return $this->status === 'draft'; }
    public function isPublished(): bool { return $this->status === 'published'; }
    public function isArchived(): bool  { return $this->status === 'archived'; }
}
