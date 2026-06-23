<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Normalises a single Event model into a stable frontend data contract.
 *
 * Rules enforced here so Vue never has to:
 *   – All date fields are emitted as ISO 8601 strings (nullable).
 *   – Pre-formatted display strings are included so Vue never calls new Date().
 *   – Every boolean is explicitly cast so SQLite "0"/"1" strings never leak.
 *   – Relations are included only when already loaded (no N+1 risk).
 */
class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // ── Identity ────────────────────────────────────────────────────────
            'id'          => $this->id,
            'title'       => $this->title,
            'description' => $this->description,
            'location'    => $this->location,
            'cover_image' => $this->cover_image,
            'category'    => $this->category,

            // ── Scalar flags (explicit bool cast — SQLite stores as 0/1) ────────
            'all_day'       => (bool) $this->all_day,
            'is_recurring'  => (bool) $this->is_recurring,
            'is_public'     => (bool) $this->is_public,
            'is_cancelled'  => (bool) $this->is_cancelled,
            'is_featured'   => (bool) $this->is_featured,
            'rsvp_enabled'  => (bool) $this->rsvp_enabled,
            'capacity'      => $this->capacity,
            'visibility'    => $this->visibility,
            'department_id' => $this->department_id,

            // ── Computed status (from model accessor) ────────────────────────────
            'status' => $this->status,

            // ── Dates — ISO 8601 for any JS logic that truly needs a Date object ─
            'start_at'    => $this->start_at?->toJSON(),
            'end_at'      => $this->end_at?->toJSON(),
            'published_at'=> $this->published_at?->toJSON(),
            'created_at'  => $this->created_at?->toJSON(),

            // ── Dates — pre-formatted so Vue never calls new Date() ──────────────
            // Calendar column in EventCard
            'start_month'   => $this->start_at?->format('M'),          // "May"
            'start_day'     => $this->start_at?->day,                  // 31
            'start_weekday' => $this->start_at?->format('D'),          // "Sat"
            // Human-readable full strings
            'start_at_formatted' => $this->start_at?->format('D, M j, Y'),  // "Sat, May 31, 2026"
            'start_time_formatted' => $this->start_at?->format('g:i A'),    // "1:00 PM"
            'end_at_formatted'   => $this->end_at?->format('D, M j, Y'),
            'end_time_formatted' => $this->end_at?->format('g:i A'),
            // Composite strings used directly in templates
            'time_range' => $this->buildTimeRange(),
            'date_range' => $this->buildDateRange(),

            // ── RSVP aggregate counts ─────────────────────────────────────────────
            'going_count' => (int) ($this->going_count ?? 0),
            'maybe_count' => (int) ($this->maybe_count ?? 0),

            // ── Relations (only when already eager-loaded) ────────────────────────
            'department' => $this->whenLoaded('department', fn () => $this->department ? [
                'id'    => $this->department->id,
                'name'  => $this->department->name,
                'icon'  => $this->department->icon,
                'color' => $this->department->color,
            ] : null),

            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id'     => $this->creator->id,
                'name'   => $this->creator->name,
                'avatar' => $this->creator->avatar,
            ] : null),

            // File attachments — key omitted unless eager-loaded (see AnnouncementResource)
            'files' => $this->when(
                $this->relationLoaded('files'),
                fn () => FileResource::collection($this->files),
            ),
        ];
    }

    // ── Private helpers ───────────────────────────────────────────────────────────

    private function buildTimeRange(): string
    {
        if ($this->all_day) {
            return 'All day';
        }

        $start = $this->start_at?->format('g:i A') ?? '';

        if (! $this->end_at) {
            return $start;
        }

        return $start . ' – ' . $this->end_at->format('g:i A');
    }

    private function buildDateRange(): string
    {
        if (! $this->start_at) {
            return '';
        }

        $start = $this->start_at->format('D, M j, Y');

        if (! $this->end_at) {
            return $start;
        }

        if ($this->start_at->isSameDay($this->end_at)) {
            return $start;
        }

        // Cross-day: "May 31 – Jun 1, 2026" (omit year on start if same year)
        $startFmt = $this->start_at->isSameYear($this->end_at) ? 'M j' : 'M j, Y';

        return $this->start_at->format($startFmt) . ' – ' . $this->end_at->format('M j, Y');
    }
}
