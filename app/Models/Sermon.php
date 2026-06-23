<?php

namespace App\Models;

use App\Traits\BelongsToChurch;
use App\Traits\HasUniqueSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sermon extends Model
{
    use SoftDeletes, BelongsToChurch, HasUniqueSlug;

    protected $fillable = [
        'church_id', 'uploaded_by',
        // Provider
        'provider', 'provider_video_id', 'provider_channel_id',
        // Core content
        'title', 'slug', 'speaker', 'description',
        // Media
        'video_url', 'audio_url', 'embed_url', 'thumbnail', 'thumbnail_url',
        'duration_seconds',
        // Organisation
        'series', 'series_id',
        // Visibility
        'is_public', 'visibility', 'is_featured',
        // Dates
        'preached_at', 'synced_at',
        // Extensible
        'metadata',
    ];

    protected $casts = [
        'is_public'   => 'boolean',
        'is_featured' => 'boolean',
        'preached_at' => 'datetime',
        'synced_at'   => 'datetime',
        'metadata'    => 'array',
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

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function sermonSeries(): BelongsTo
    {
        return $this->belongsTo(SermonSeries::class, 'series_id');
    }

    public function channelConnection(): BelongsTo
    {
        return $this->belongsTo(ChannelConnection::class, 'provider_channel_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    /** Sermons that should appear on the public website. */
    public function scopePubliclyVisible($query): void
    {
        $query->where(function ($q) {
            $q->where('visibility', 'public')
              ->orWhere(function ($q2) {
                  $q2->whereNull('visibility')->where('is_public', true);
              });
        });
    }

    public function scopeFeatured($query): void
    {
        $query->where('is_featured', true);
    }

    public function scopeByProvider($query, string $provider): void
    {
        $query->where('provider', $provider);
    }

    /** Sermons from a specific series (by string OR series_id). */
    public function scopeInSeries($query, string $series): void
    {
        $query->where(function ($q) use ($series) {
            $q->where('series', $series)
              ->orWhereHas('sermonSeries', fn ($q2) => $q2->where('title', $series));
        });
    }

    // ── Computed attributes ────────────────────────────────────────────────────

    /**
     * Human-readable duration derived from duration_seconds.
     * e.g. "42 min" | "1 h 2 min" | null
     */
    public function getDurationAttribute(): ?string
    {
        if (! $this->duration_seconds) return null;

        $h = intdiv($this->duration_seconds, 3600);
        $m = intdiv($this->duration_seconds % 3600, 60);
        $s = $this->duration_seconds % 60;

        if ($h > 0) {
            return $h . ' h' . ($m > 0 ? " {$m} min" : '');
        }

        return $m . ' min' . ($s > 0 ? " {$s} sec" : '');
    }

    /**
     * Best-available thumbnail URL:
     *   1. thumbnail_url (provider CDN — full URL)
     *   2. thumbnail (local storage path → resolve via Storage)
     *   3. null
     */
    public function getThumbnailDisplayAttribute(): ?string
    {
        if ($this->thumbnail_url) {
            return $this->thumbnail_url;
        }

        if ($this->thumbnail) {
            return \Illuminate\Support\Facades\Storage::disk('public')->url($this->thumbnail);
        }

        return null;
    }

    /**
     * Best-available embed URL:
     *   1. embed_url (stored by provider)
     *   2. derived from video_url (YouTube/Vimeo detection)
     *   3. null → use <video> tag or direct link
     */
    public function getEmbedUrlDisplayAttribute(): ?string
    {
        if ($this->embed_url) {
            return $this->embed_url;
        }

        if (! $this->video_url) {
            return null;
        }

        // YouTube
        if (preg_match(
            '/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([A-Za-z0-9_-]{11})/',
            $this->video_url,
            $m,
        )) {
            return "https://www.youtube.com/embed/{$m[1]}?rel=0&modestbranding=1";
        }

        // Vimeo
        if (preg_match('/vimeo\.com\/(\d+)/', $this->video_url, $m)) {
            return "https://player.vimeo.com/video/{$m[1]}";
        }

        return null;
    }

    /**
     * Resolved visibility:
     *   - Use 'visibility' column when set
     *   - Fall back to is_public → 'public' | 'members_only'
     */
    public function getResolvedVisibilityAttribute(): string
    {
        return $this->visibility ?? ($this->is_public ? 'public' : 'members_only');
    }

    // ── Statics ────────────────────────────────────────────────────────────────

    /**
     * Parse an ISO 8601 duration string (e.g. "PT42M30S") to total seconds.
     * Used when ingesting YouTube API responses.
     */
    public static function parseIsoDuration(string $iso): int
    {
        if (! preg_match('/^PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/', $iso, $m)) {
            return 0;
        }

        return (int) ($m[1] ?? 0) * 3600
             + (int) ($m[2] ?? 0) * 60
             + (int) ($m[3] ?? 0);
    }
}
