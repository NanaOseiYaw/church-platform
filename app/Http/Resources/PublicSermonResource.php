<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight sermon data contract for the public website.
 * Only exposes fields safe for anonymous visitors.
 */
class PublicSermonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'slug'        => $this->slug ?? (string) $this->id,
            'speaker'     => $this->speaker,
            'description' => $this->description,
            'series'      => $this->series,
            'series_id'   => $this->series_id,

            // Provider
            'provider'          => $this->provider ?? 'manual',
            'provider_video_id' => $this->provider_video_id,

            // Media — resolved for public display
            'thumbnail' => $this->thumbnail_display,
            'embed_url' => $this->embed_url_display,
            'audio_url' => $this->audio_url,

            'duration'         => $this->duration,
            'duration_seconds' => $this->duration_seconds,

            'is_featured' => (bool) $this->is_featured,

            // Pre-formatted dates
            'preached_at'           => $this->preached_at?->toISOString(),
            'preached_at_formatted' => $this->preached_at?->format('j M Y'),
            'preached_month'        => $this->preached_at?->format('M'),
            'preached_day'          => $this->preached_at?->day,
            'preached_year'         => $this->preached_at?->year,

            // Series relation
            'sermon_series' => $this->whenLoaded('sermonSeries', fn () => $this->sermonSeries ? [
                'id'    => $this->sermonSeries->id,
                'title' => $this->sermonSeries->title,
                'slug'  => $this->sermonSeries->slug,
            ] : null),
        ];
    }
}
