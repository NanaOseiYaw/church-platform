<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Slim, public-safe representation of an Announcement model.
 *
 * Exposes only fields appropriate for the public-facing website.
 * Pre-formats dates and strips HTML body to plain-text excerpt so Vue
 * components never call new Date() on raw strings or render raw HTML.
 */
class PublicAnnouncementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Strip tags once; reuse for both excerpt and content.
        $plainText = strip_tags($this->body ?? '');

        return [
            'id'        => $this->id,
            'title'     => $this->title,
            'category'  => $this->category,
            'priority'  => $this->priority,
            'is_pinned' => (bool) $this->is_pinned,

            // ── Pre-formatted date string (no new Date() needed in Vue) ─────
            'date'    => $this->published_at?->format('M j, Y') ?? '—',

            // ── Plain-text body variants ────────────────────────────────────
            'excerpt' => Str::limit($plainText, 160),
            'content' => $plainText,

            // ISO string for any JS logic that genuinely needs it
            'published_at' => $this->published_at?->toJSON(),
        ];
    }
}
