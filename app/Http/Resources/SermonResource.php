<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full dashboard-side sermon data contract.
 * All dates are pre-formatted here — Vue never calls new Date().
 */
class SermonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'slug'        => $this->slug,
            'speaker'     => $this->speaker,
            'description' => $this->description,
            'series'      => $this->series,
            'series_id'   => $this->series_id,

            // Provider / source
            'provider'          => $this->provider ?? 'manual',
            'provider_video_id' => $this->provider_video_id,

            // Media
            'video_url'   => $this->video_url,
            'audio_url'   => $this->audio_url,
            'embed_url'   => $this->embed_url_display,     // computed (stored or derived)
            'thumbnail'   => $this->thumbnail_display,     // computed (CDN URL or Storage URL)

            // Duration
            'duration'         => $this->duration,          // "42 min" accessor
            'duration_seconds' => $this->duration_seconds,

            // Status
            'is_public'   => (bool) $this->is_public,
            'visibility'  => $this->resolved_visibility,   // 'public' | 'members_only' | 'unlisted'
            'is_featured' => (bool) $this->is_featured,

            // Dates — pre-formatted
            'preached_at'           => $this->preached_at?->toISOString(),
            'preached_at_formatted' => $this->preached_at?->format('j M Y'),
            'preached_at_long'      => $this->preached_at?->format('l, j F Y'),
            'preached_month'        => $this->preached_at?->format('M'),
            'preached_day'          => $this->preached_at?->day,
            'preached_year'         => $this->preached_at?->year,
            'synced_at'             => $this->synced_at?->toISOString(),
            'synced_at_formatted'   => $this->synced_at?->diffForHumans(),
            'created_at'            => $this->created_at?->toISOString(),
            'created_at_formatted'  => $this->created_at?->format('j M Y'),

            // Relations — loaded on demand
            'uploader' => $this->whenLoaded('uploader', fn () => $this->uploader ? [
                'id'     => $this->uploader->id,
                'name'   => $this->uploader->name,
                'avatar' => $this->uploader->avatar,
            ] : null),

            'channel_connection' => $this->whenLoaded('channelConnection', fn () => $this->channelConnection ? [
                'id'            => $this->channelConnection->id,
                'provider'      => $this->channelConnection->provider,
                'channel_title' => $this->channelConnection->channel_title,
                'channel_id'    => $this->channelConnection->channel_id,
            ] : null),
        ];
    }
}
