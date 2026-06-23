<?php

namespace App\Models;

use App\Traits\BelongsToChurch;
use App\Traits\HasUniqueSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SermonSeries extends Model
{
    use BelongsToChurch, HasUniqueSlug;

    protected $table = 'sermon_series';

    protected $fillable = [
        'church_id', 'title', 'slug', 'description',
        'cover_image', 'is_active', 'sort_order',
        'started_at', 'ended_at',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'started_at' => 'date',
        'ended_at'   => 'date',
    ];

    protected function slugConfig(): array
    {
        return ['from' => 'title', 'to' => 'slug', 'scope' => 'church_id'];
    }

    // ── Relations ──────────────────────────────────────────────────────────────

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function sermons(): HasMany
    {
        return $this->hasMany(Sermon::class, 'series_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeActive($query): void
    {
        $query->where('is_active', true);
    }

    public function scopeOrdered($query): void
    {
        $query->orderBy('sort_order')->orderBy('title');
    }

    /** Series that should appear on the public website. */
    public function scopePubliclyVisible($query): void
    {
        $query->where('is_active', true);
    }
}
