<?php

namespace App\Search\Providers;

use App\Models\Announcement;
use App\Models\User;
use App\Search\Contracts\SearchProvider;
use App\Search\SearchResult;

class AnnouncementSearchProvider implements SearchProvider
{
    public function getType(): string  { return 'announcement'; }
    public function getLabel(): string { return 'Announcements'; }

    public function isAvailable(User $user): bool
    {
        return $user->can('announcements.view');
    }

    public function search(string $query, User $user, int $churchId, int $limit): array
    {
        $builder = Announcement::where('church_id', $churchId)
            ->published()
            ->where(function ($b) use ($query) {
                $b->where('title', 'like', "%{$query}%")
                  ->orWhere('body',  'like', "%{$query}%");
            });

        // Admins/editors see everything; regular members get visibility-filtered
        if (! $user->can('announcements.edit')) {
            $builder->where(function ($b) use ($user) {
                $b->whereIn('visibility', ['public', 'members_only'])
                  ->orWhere(function ($b2) use ($user) {
                      $b2->where('visibility', 'department_only')
                         ->whereHas('department', fn ($dq) =>
                             $dq->whereHas('members', fn ($mq) =>
                                 $mq->where('users.id', $user->id)
                             )
                         );
                  })
                  ->orWhere(function ($b2) use ($user) {
                      $b2->where('visibility', 'private')
                         ->where('created_by', $user->id);
                  });
            });
        }

        $results = $builder
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();

        return $results->map(function (Announcement $ann) {
            $plain   = strip_tags((string) ($ann->body ?? ''));
            $snippet = mb_strlen($plain) > 80
                ? mb_substr($plain, 0, 80) . '…'
                : ($plain ?: null);

            return new SearchResult(
                id:         $ann->id,
                type:       $this->getType(),
                title:      $ann->title,
                subtitle:   $snippet,
                meta:       $ann->published_at?->format('M j, Y'),
                url:        "/dashboard/announcements/{$ann->id}",
                icon:       'megaphone',
                badge:      $ann->is_pinned ? 'Pinned' : null,
                badgeColor: 'brand',
            );
        })->all();
    }
}
