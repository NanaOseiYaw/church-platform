<?php

namespace App\Models;

use App\Traits\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrayerRequest extends Model
{
    use BelongsToChurch;

    protected $fillable = [
        'church_id', 'name', 'email', 'request',
        'is_anonymous', 'is_private', 'is_answered',
        'answered_at', 'admin_notes',
    ];

    protected $casts = [
        'is_anonymous' => 'boolean',
        'is_private'   => 'boolean',
        'is_answered'  => 'boolean',
        'answered_at'  => 'datetime',
    ];

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    /** Display name: "Anonymous" when is_anonymous, else the provided name. */
    public function getDisplayNameAttribute(): string
    {
        return $this->is_anonymous ? 'Anonymous' : ($this->name ?? 'Anonymous');
    }
}
