<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Slim, public-safe representation of an Event model.
 *
 * Exposes only fields appropriate for the public-facing website.
 * Pre-formats date/time so Vue components never call new Date() on raw strings.
 */
class PublicEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $time = match (true) {
            (bool) $this->all_day       => 'All day',
            $this->start_at !== null    => $this->start_at->format('g:i A'),
            default                     => '',
        };

        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'description' => $this->description,
            'category'    => $this->category,
            'location'    => $this->location,
            'image'       => $this->cover_image,
            'is_featured' => (bool) $this->is_featured,
            'visibility'  => $this->visibility,

            // ── Pre-formatted date strings (no new Date() needed in Vue) ────
            'date'        => $this->start_at?->format('D, M j, Y') ?? '—',
            'time'        => $time,
            'date_range'  => $this->buildDateRange(),
            'time_range'  => $this->buildTimeRange(),

            // ISO strings for any JS logic that genuinely needs them
            'start_at'    => $this->start_at?->toJSON(),
            'end_at'      => $this->end_at?->toJSON(),
        ];
    }

    private function buildTimeRange(): string
    {
        if ($this->all_day) return 'All day';
        if (! $this->start_at) return '';
        if (! $this->end_at)   return $this->start_at->format('g:i A');
        return $this->start_at->format('g:i A') . ' – ' . $this->end_at->format('g:i A');
    }

    private function buildDateRange(): string
    {
        if (! $this->start_at) return '';
        $start = $this->start_at->format('D, M j, Y');
        if (! $this->end_at)                           return $start;
        if ($this->start_at->isSameDay($this->end_at)) return $start;
        $fmt = $this->start_at->isSameYear($this->end_at) ? 'M j' : 'M j, Y';
        return $this->start_at->format($fmt) . ' – ' . $this->end_at->format('M j, Y');
    }
}
