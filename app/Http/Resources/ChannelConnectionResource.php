<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChannelConnectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'provider'            => $this->provider,
            'channel_id'          => $this->channel_id,
            'channel_title'       => $this->channel_title,
            'channel_thumbnail'   => $this->channel_thumbnail,
            'channel_description' => $this->channel_description,
            'is_active'           => (bool) $this->is_active,
            'subscriber_count'    => $this->subscriber_count,
            'video_count'         => $this->video_count,
            'sync_frequency_hours'=> $this->sync_frequency_hours,

            // Pre-formatted dates
            'last_synced_at'           => $this->last_synced_at?->toISOString(),
            'last_synced_at_formatted' => $this->last_synced_at
                ? $this->last_synced_at->diffForHumans()
                : null,
            'next_sync_at'             => $this->next_sync_at?->toISOString(),
            'next_sync_at_formatted'   => $this->next_sync_at
                ? $this->next_sync_at->diffForHumans()
                : null,

            'created_at'           => $this->created_at?->toISOString(),
            'created_at_formatted' => $this->created_at?->format('j M Y'),

            // Channel URL for linking
            'channel_url' => match ($this->provider) {
                'youtube' => "https://www.youtube.com/channel/{$this->channel_id}",
                default   => null,
            },
        ];
    }
}
