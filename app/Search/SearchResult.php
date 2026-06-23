<?php

namespace App\Search;

/**
 * Normalized search result DTO.
 *
 * Every search provider returns an array of these. The frontend consumes
 * ONE unified structure regardless of which module produced the result.
 *
 * Icon values map to Lucide icon names on the frontend:
 *   user | building | megaphone | calendar | check-square |
 *   file | file-text | image | music | video | calendar-check
 *
 * Badge color values map to Tailwind color names:
 *   brand | violet | blue | emerald | amber | orange | rose | neutral
 */
final class SearchResult
{
    public function __construct(
        public readonly string|int $id,
        public readonly string     $type,
        public readonly string     $title,
        public readonly ?string    $subtitle   = null,
        public readonly ?string    $meta       = null,
        public readonly string     $url        = '#',
        public readonly string     $icon       = 'file',
        public readonly ?string    $badge      = null,
        public readonly ?string    $badgeColor = null,
    ) {}

    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'type'        => $this->type,
            'title'       => $this->title,
            'subtitle'    => $this->subtitle,
            'meta'        => $this->meta,
            'url'         => $this->url,
            'icon'        => $this->icon,
            'badge'       => $this->badge,
            'badge_color' => $this->badgeColor,
        ];
    }
}
