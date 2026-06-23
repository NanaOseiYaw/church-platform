<?php

namespace App\Models;

use App\Traits\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChannelConnection extends Model
{
    use BelongsToChurch;

    protected $table = 'church_channel_connections';

    protected $fillable = [
        'church_id', 'provider', 'channel_id', 'channel_title',
        'channel_thumbnail', 'channel_description', 'uploads_playlist_id',
        'subscriber_count', 'video_count',
        'is_active', 'last_synced_at', 'next_sync_at',
        'sync_frequency_hours', 'settings',
    ];

    protected $casts = [
        'is_active'            => 'boolean',
        'last_synced_at'       => 'datetime',
        'next_sync_at'         => 'datetime',
        'sync_frequency_hours' => 'integer',
        'settings'             => 'array',
    ];

    // ── Relations ──────────────────────────────────────────────────────────────

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function sermons(): HasMany
    {
        return $this->hasMany(Sermon::class, 'provider_channel_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    /** Channels due for a sync right now. */
    public function scopeDueForSync($query): void
    {
        $query->where('is_active', true)
              ->where(function ($q) {
                  $q->whereNull('next_sync_at')
                    ->orWhere('next_sync_at', '<=', now());
              });
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /** Returns a custom API key from settings, if the church provided one. */
    public function customApiKey(): ?string
    {
        return data_get($this->settings, 'api_key') ?: null;
    }

    /** Convenience: mark sync as complete and schedule the next one. */
    public function markSynced(): static
    {
        $this->update([
            'last_synced_at' => now(),
            'next_sync_at'   => now()->addHours($this->sync_frequency_hours),
        ]);

        return $this;
    }
}
