<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Normalises a single Announcement model into a stable frontend data contract.
 *
 * – Date fields are emitted as ISO 8601 (for any JS logic) AND as
 *   pre-formatted display strings (so Vue never calls new Date()).
 * – status is derived from the model accessor, so the controller
 *   does not need ->append('status').
 * – is_church_wide kept for legacy badge logic; visibility is the
 *   authoritative field.
 */
class AnnouncementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // ── Identity ─────────────────────────────────────────────────────────
            'id'             => $this->id,
            'title'          => $this->title,
            'body'           => $this->body,
            'cover_image'    => $this->cover_image,
            'category'       => $this->category,
            'priority'       => $this->priority,
            'visibility'     => $this->visibility,
            'department_id'  => $this->department_id,
            'is_pinned'      => (bool) $this->is_pinned,
            'is_church_wide' => (bool) $this->is_church_wide,
            'is_featured'    => (bool) $this->is_featured,

            // ── Computed status (from model accessor) ────────────────────────────
            'status' => $this->status,

            // ── Dates — ISO 8601 ─────────────────────────────────────────────────
            'published_at' => $this->published_at?->toJSON(),
            'expires_at'   => $this->expires_at?->toJSON(),
            'created_at'   => $this->created_at?->toJSON(),

            // ── Dates — pre-formatted for display ────────────────────────────────
            // "28 May 2026"
            'published_at_formatted' => $this->published_at?->format('j M Y'),
            // "28 May 2026"
            'expires_at_formatted'   => $this->expires_at?->format('j M Y'),
            // "28 May 2026, 10:30 AM"
            'published_at_long'      => $this->published_at?->format('j M Y, g:i A'),
            'created_at_formatted'   => $this->created_at?->format('j M Y'),
            // Convenience: first available date label (published_at or created_at)
            // Used by AnnouncementFeedCard so it never needs ?. chains.
            'date_label' => ($this->published_at ?? $this->created_at)?->format('j M Y') ?? '',

            // ── Read count ────────────────────────────────────────────────────────
            'reads_count' => (int) ($this->reads_count ?? 0),

            // ── Relations ────────────────────────────────────────────────────────
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

            // File attachments — key is omitted entirely unless eager-loaded.
            // Using $this->when() avoids creating a ResourceCollection backed by
            // a MissingValue, which leaves $collection null and crashes toArray().
            'files' => $this->when(
                $this->relationLoaded('files'),
                fn () => FileResource::collection($this->files),
            ),
        ];
    }
}
