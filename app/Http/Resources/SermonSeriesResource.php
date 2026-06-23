<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SermonSeriesResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'slug'        => $this->slug,
            'description' => $this->description,
            'cover_image' => $this->cover_image
                ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->cover_image)
                : null,
            'is_active'   => (bool) $this->is_active,
            'sort_order'  => $this->sort_order,
            'started_at'  => $this->started_at?->format('j M Y'),
            'ended_at'    => $this->ended_at?->format('j M Y'),
            'sermon_count'=> $this->when(
                isset($this->sermons_count),
                $this->sermons_count,
            ),
        ];
    }
}
