<?php

namespace App\Models;

use App\Enums\DepartmentVisibility;
use App\Models\Concerns\HasMedia;
use App\Traits\BelongsToChurch;
use App\Traits\HasUniqueSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use SoftDeletes, BelongsToChurch, HasUniqueSlug, HasMedia;

    protected $fillable = [
        'church_id', 'name', 'slug', 'description',
        'cover_image', 'coordinator_id', 'icon', 'color',
        'is_active', 'visibility', 'settings', 'created_by',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'settings'   => 'array',
        'visibility' => DepartmentVisibility::class,
    ];

    /**
     * Slugs are unique per church — two churches may share "media-team".
     * Collision handling: media-team → media-team-2 → media-team-3 …
     */
    protected function slugConfig(): array
    {
        return ['from' => 'name', 'to' => 'slug', 'scope' => 'church_id'];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinator_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class);
    }

    public function servingPositions(): HasMany
    {
        return $this->hasMany(ServingPosition::class);
    }
}
