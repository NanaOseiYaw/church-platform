<?php

namespace App\Search\Providers;

use App\Models\Event;
use App\Models\User;
use App\Search\Contracts\SearchProvider;
use App\Search\SearchResult;

class EventSearchProvider implements SearchProvider
{
    public function getType(): string  { return 'event'; }
    public function getLabel(): string { return 'Events'; }

    public function isAvailable(User $user): bool
    {
        return $user->can('events.view');
    }

    public function search(string $query, User $user, int $churchId, int $limit): array
    {
        $builder = Event::where('church_id', $churchId)
            ->where(function ($b) use ($query) {
                $b->where('title',       'like', "%{$query}%")
                  ->orWhere('location',    'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%");
            });

        // Admins/editors bypass visibility; members get filtered
        if (! $user->can('events.edit')) {
            $builder->where(function ($b) use ($user) {
                $b->whereIn('visibility', ['public', 'members_only'])
                  ->orWhere(function ($b2) use ($user) {
                      $b2->where('visibility', 'department_only')
                         ->whereHas('department', fn ($dq) =>
                             $dq->whereHas('members', fn ($mq) =>
                                 $mq->where('users.id', $user->id)
                             )
                         );
                  });
            });
        }

        $results = $builder
            ->orderByDesc('start_at')
            ->limit($limit)
            ->get();

        return $results->map(function (Event $event) {
            $date     = $event->start_at?->format('M j, Y');
            $subtitle = collect([$date, $event->location])->filter()->implode(' · ');

            $badgeColor = match ($event->status) {
                'cancelled'  => 'rose',
                'completed'  => 'neutral',
                'ongoing'    => 'emerald',
                default      => null,
            };

            return new SearchResult(
                id:         $event->id,
                type:       $this->getType(),
                title:      $event->title,
                subtitle:   $subtitle ?: null,
                meta:       null,
                url:        "/dashboard/events/{$event->id}",
                icon:       'calendar',
                badge:      $event->status !== 'scheduled' && $event->status !== 'upcoming'
                                ? ucfirst($event->status)
                                : null,
                badgeColor: $badgeColor,
            );
        })->all();
    }
}
