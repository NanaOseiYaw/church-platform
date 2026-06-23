<?php

namespace App\Search\Providers;

use App\Models\User;
use App\Search\Contracts\SearchProvider;
use App\Search\SearchResult;

class MemberSearchProvider implements SearchProvider
{
    public function getType(): string  { return 'member'; }
    public function getLabel(): string { return 'Members'; }

    public function isAvailable(User $user): bool
    {
        return $user->can('members.view');
    }

    public function search(string $query, User $user, int $churchId, int $limit): array
    {
        $members = User::where('church_id', $churchId)
            ->where(function ($b) use ($query) {
                $b->where('name',  'like', "%{$query}%")
                  ->orWhere('email', 'like', "%{$query}%");
            })
            ->with('roles')
            ->limit($limit)
            ->get();

        return $members->map(function (User $member) {
            $roleName = $member->getRoleNames()->first() ?? 'member';

            $badge = match ($roleName) {
                'church_admin' => 'Admin',
                'coordinator'  => 'Coordinator',
                'super_admin'  => 'Super Admin',
                default        => null,
            };

            $badgeColor = match ($roleName) {
                'church_admin' => 'violet',
                'coordinator'  => 'blue',
                'super_admin'  => 'rose',
                default        => null,
            };

            return new SearchResult(
                id:         $member->id,
                type:       $this->getType(),
                title:      $member->name,
                subtitle:   $member->email,
                meta:       ucwords(str_replace('_', ' ', $roleName)),
                url:        "/dashboard/members/{$member->id}",
                icon:       'user',
                badge:      $badge,
                badgeColor: $badgeColor,
            );
        })->all();
    }
}
